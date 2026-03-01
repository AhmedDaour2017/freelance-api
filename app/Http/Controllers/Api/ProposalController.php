<?php

namespace App\Http\Controllers\Api;

use App\Helpers\NotificationHelper;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProposalController extends Controller
{
    public function store(Request $request, Project $project)
    {
        $user = $request->user();

        // تأكد المشروع مفتوح
        if ($project->status !== 'open') {
            return response()->json([
                'message' => 'You cannot send a proposal to a closed project.'
            ], 400);
        }

        // منع إرسال عرضين لنفس المشروع
        if ($project->proposals()->where('freelancer_id', $user->id)->exists()) {
            return response()->json([
                'message' => 'You have already sent a proposal to this project.'
            ], 400);
        }

        $request->validate([
            'price' => 'required|numeric|min:1',
            'message' => 'required|string',
            'delivery_days' => 'required|integer|min:1'
        ]);

        $proposal = $project->proposals()->create([
            'freelancer_id' => $user->id,
            'price' => $request->price,
            'message' => $request->message,
            'delivery_days' => $request->delivery_days,
        ]);


        // 🔥 Notification للـ Client
        NotificationHelper::sendNotification(
            $project->client_id,
            'proposal_sent',
            'New proposal submitted for your project: ' . $project->title
        );

        return response()->json([
            'status' => true,
            'message' => 'Proposal sent successfully.',
            'proposal' => $proposal
        ], 201);
    }



public function accept($id)
{
    // جلب العرض مع العلاقات والـ Client (قفل السجل للتعديل لمنع الـ Race Condition)
    $proposal = Proposal::with(['project.client', 'freelancer'])->findOrFail($id);
    $project = $proposal->project;
    $user = Auth::user();

    // 1. التحققات الأمنية (Validation)
    if ($user->id !== $project->client_id) {
        return response()->json(['message' => 'Only the project owner can accept proposals.'], 403);
    }

    if ($project->status === 'in_progress') {
        return response()->json(['message' => 'This project is already in progress.'], 400);
    }

    if ($proposal->status !== 'pending') {
        return response()->json(['message' => 'This proposal is already ' . $proposal->status], 400);
    }

    try {
        DB::transaction(function () use ($proposal, $project) {
            // جلب العميل مع قفل السجل (Database Lock) لضمان عدم تغير الرصيد أثناء العملية
            $client = User::where('id', $project->client_id)->lockForUpdate()->first();

            // التأكد من كفاية الرصيد
            if ($client->balance < $proposal->price) {
                throw new \Exception('Insufficient balance in your account.');
            }

            // 💸 العمليات المالية (استخدام decrement/increment أدق في قواعد البيانات)
            $client->decrement('balance', $proposal->price);
            $client->increment('pending_balance', $proposal->price);

            // 📝 توثيق الحركة المالية في جدول Transactions (مهم جداً للتدقيق)
            // إذا لم تنشئ الموديل بعد، يمكنك التعليق على هذا الجزء مؤقتاً
            
            Transaction::create([
                'user_id' => $client->id,
                'amount' => $proposal->price,
                'type' => 'project_payment', // دفع لمشروع
                'status' => 'completed',
                'description' => "Locked funds for project: " . $project->title,
                'trackable_id' => $project->id,
                'trackable_type' => get_class($project),
            ]);
            

            // 🛠 تحديث حالة المشروع والعرض
            $project->update(['status' => 'in_progress']);
            $proposal->update(['status' => 'accepted']);

            // رفض باقي العروض المقدمة على هذا المشروع تلقائياً
            Proposal::where('project_id', $project->id)
                ->where('id', '!=', $proposal->id)
                ->where('status', 'pending')
                ->update(['status' => 'rejected']);
        });

        // 🔔 إرسال الإشعارات (خارج الترانزاكشن لضمان السرعة)
        NotificationHelper::sendNotification(
            $proposal->freelancer_id,
            'proposal_accepted',
            'Your proposal for project "' . $project->title . '" has been accepted!'
        );

        NotificationHelper::sendNotification(
            $project->client_id,
            'proposal_accepted',
            'You accepted a proposal for project "' . $project->title . '"'
        );

        return response()->json([
            'status' => true,
            'message' => 'Proposal accepted. Funds moved to escrow and project started.'
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => $e->getMessage()
        ], 400);
    }
}









    public function reject($id)
    {
        $proposal = Proposal::findOrFail($id);
        $project = $proposal->project;
        $user = Auth::user();

        // 🔴 التأكد أن صاحب المشروع هو من يرفض
        if ($user->id !== $project->client_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // 🔴 لا يمكن رفض عرض مقبول مسبقاً أو مرفوض مسبقاً
        if ($proposal->status !== 'pending') {
            return response()->json(['message' => 'This proposal is already ' . $proposal->status], 400);
        }

        // تحديث الحالة
        $proposal->status = 'rejected';
        $proposal->save();

        // 🔥 إرسال إشعار للفريلانسر
        NotificationHelper::sendNotification(
            $proposal->freelancer_id,
            'proposal_rejected',
            'Your proposal for project "' . $project->title . '" has been rejected.'
        );

        return response()->json([
            'status' => true,
            'message' => 'Proposal rejected successfully.'
        ]);
    }



}
