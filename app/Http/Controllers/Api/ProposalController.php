<?php

namespace App\Http\Controllers\Api;

use App\Helpers\NotificationHelper;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Proposal;
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




    // public function accept(Request $request, Proposal $proposal)
    // {
    //     $user = $request->user();
    //     $project = $proposal->project;

    //     // التحقق من الصلاحيات والحالات (نفس اللي كتبناه قبل)
    //     if ($project->status !== 'open') {
    //         return response()->json([
    //             'message' => 'This project is not open for accepting proposals.'
    //         ], 400);
    //     }

    //     if ($proposal->status !== 'pending') {
    //         return response()->json([
    //             'message' => 'This proposal cannot be accepted.'
    //         ], 400);
    //     }

    //     if (
    //         $user->role !== 'admin' &&
    //         !($user->role === 'client' && $project->client_id === $user->id)
    //     ) {
    //         return response()->json([
    //             'message' => 'Unauthorized.'
    //         ], 403);
    //     }

    //     DB::transaction(function () use ($proposal, $project) {

    //         // قبول العرض
    //         $proposal->update([
    //             'status' => 'accepted'
    //         ]);

    //         // رفض الباقي
    //         $project->proposals()
    //             ->where('id', '!=', $proposal->id)
    //             ->update(['status' => 'rejected']);

    //         // تحديث المشروع
    //         $project->update([
    //             'status' => 'in_progress'
    //         ]);
    //     });


    //     NotificationHelper::sendNotification(
    //     $proposal->freelancer_id,
    //     'proposal_accepted',
    //     'Your proposal for project "' . $project->title . '" has been accepted!'
    //     );

    //     NotificationHelper::sendNotification(
    //     $project->client_id,
    //     'proposal_accepted',
    //     'You accepted a proposal for project "' . $project->title . '"'
    //     );

    //     return response()->json([
    //         'message' => 'Proposal accepted successfully.',
    //         'project_status' => 'in_progress'
    //     ]);
    // }


    public function accept($id)
    {
    $proposal = Proposal::with('project.client')->findOrFail($id);
    $project = $proposal->project;
    $user = Auth::user();

    // 🔴 فقط صاحب المشروع يقبل
    if ($user->id !== $project->client_id) {
        return response()->json([
            'message' => 'Only the project owner can accept proposals.'
        ], 403);
    }

    // 🔴 منع لو المشروع already in_progress
    if ($project->status === 'in_progress') {
        return response()->json([
            'message' => 'This project is already in progress.'
        ], 400);
    }

    // 🔴 منع لو العرض rejected
    if ($proposal->status === 'rejected') {
        return response()->json([
            'message' => 'You cannot accept a rejected proposal.'
        ], 400);
    }

    // 🔴 منع لو العرض accepted مسبقاً
    if ($proposal->status === 'accepted') {
        return response()->json([
            'message' => 'This proposal is already accepted.'
        ], 400);
    }

    DB::transaction(function () use ($proposal, $project) {

        $client = $project->client;

        if ($client->balance < $proposal->price) {
            abort(400, 'Insufficient balance.');
        }

        // 💸 نقل الأموال إلى Escrow
        $client->balance -= $proposal->price;
        $client->pending_balance += $proposal->price;
        $client->save();

        // تحديث المشروع
        $project->status = 'in_progress';
        $project->save();

        // قبول العرض
        $proposal->status = 'accepted';
        $proposal->save();

        // رفض باقي العروض
        Proposal::where('project_id', $project->id)
            ->where('id', '!=', $proposal->id)
            ->update(['status' => 'rejected']);
    });


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
        'message' => 'Proposal accepted and funds moved to escrow.'
    ]);
}






}
