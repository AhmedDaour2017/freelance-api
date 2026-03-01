<?php

namespace App\Http\Controllers\Api;

use App\Helpers\NotificationHelper;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{

    public function index(Request $request)
    {
        $user = $request->user();

        $query = Project::orderBy('id', 'desc');

        if ($user->role === 'freelancer') {
            // الفريلانسر يشوف المشاريع المفتوحة (للتقديم) 
            // "أو" المشاريع التي يشارك فيها فعلياً (للمتابعة والتقييم)
            $query->where(function($q) use ($user) {
                $q->where('status', 'open')
                ->orWhereHas('proposals', function($p) use ($user) {
                    $p->where('freelancer_id', $user->id)
                        ->whereIn('status', ['accepted']); // العروض التي تم قبولها
                });
            });
        }

        if ($user->role === 'client') {
            $query->where('client_id', $user->id);
        }

        // admin يشوف كل شيء بدون فلترة

        $projects = $query->paginate(3);

        return response()->json([
            'status' => true,
            'projects' => $projects // الـ Paginate يرجع كائن يحتوي على data و current_page وغيرها
        ]);

        
    }


    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'budget' => 'required|numeric|min:0',
            'deadline' => 'nullable|date|after_or_equal:today'
        ]);

        $project = $request->user()->projects()->create([
            'title' => $request->title,
            'description' => $request->description,
            'budget' => $request->budget,
            'deadline' => $request->deadline,
        ]);

        // بعد إنشاء المشروع
        $freelancers = User::where('role', 'freelancer')->get();

        foreach ($freelancers as $freelancer) {
            NotificationHelper::sendNotification(
                $freelancer->id,
                'project_created',
                'New project "' . $project->title . '" has been posted!'
            );
        }


        return response()->json([
            'status' => true,
            'message' => 'Project created successfully',
            'project' => $project
        ], 201);
    }





    public function show(Project $project)
    {
        // return response()->json([
        // 'project' => $project->load(['client:id,name,email', 'proposals'])
        // ]);
        return response()->json([
        'project' => $project->load(['client', 'proposals.freelancer:id,name', 'reviews'])
    ]);
    }



    public function update(Request $request, Project $project)
    {
        $user = $request->user();

        if (!in_array($user->role, ['client','admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($user->role === 'client' && $project->client_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($user->role === 'client' && $project->status !== 'open') {
        return response()->json([
            'message' => 'Cannot modify project details after it has been started or closed.'
        ], 422);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'budget' => 'sometimes|numeric|min:0',
            'deadline' => 'sometimes|date|after_or_equal:today',
            'status' => 'sometimes|in:open,in_progress,completed'
        ]);

        $project->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Project updated successfully',
            'project' => $project
        ]);
    }





    public function destroy(Request $request, Project $project)
    {
        $user = $request->user();

        if (!in_array($user->role, ['client','admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($user->role === 'client' && $project->client_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $project->delete();

        return response()->json(['status' => true,'message' => 'Project deleted successfully']);
    }











    public function complete($id)
    {
        // 1. جلب المشروع مع العرض المقبول والفريلانسر (Eager Loading)
        $project = Project::with(['proposals' => function($q) {
            $q->where('status', 'accepted');
        }, 'proposals.freelancer'])->findOrFail($id);

        $acceptedProposal = $project->proposals->first();
        $user = Auth::user();

        // 2. التحققات الأمنية والمنطقية
        if ($user->id !== $project->client_id) {
            return response()->json(['message' => 'Only the project owner can complete this project.'], 403);
        }

        if ($project->status !== 'in_progress' || !$acceptedProposal) {
            return response()->json(['message' => 'Project must be in progress with an accepted proposal.'], 400);
        }

        try {
            DB::transaction(function () use ($project, $acceptedProposal) {
                // 3. قفل السجلات المالية (Lock for Update) لمنع التلاعب بالرصيد
                $client = User::where('id', $project->client_id)->lockForUpdate()->first();
                $freelancer = User::where('id', $acceptedProposal->freelancer_id)->lockForUpdate()->first();
                $admin = User::where('role', 'admin')->lockForUpdate()->first(); // جلب الأدمن

                $totalAmount = $acceptedProposal->price;

                // 4. حساب العمولة (مثلاً 10% للمنصة)
                $commission = $totalAmount * 0.10; 
                $netToFreelancer = $totalAmount - $commission;

                // التحقق من أن الرصيد المعلق كافٍ (Double Check)
                if ($client->pending_balance < $totalAmount) {
                    throw new \Exception('Escrow balance mismatch. Please contact support.');
                }

                // 5. العمليات المالية (التحويلات)
                // أ- خصم المبلغ كاملاً من الرصيد المعلق للعميل
                $client->decrement('pending_balance', $totalAmount);
                // ب- إضافة المبلغ الصافي لرصيد الفريلانسر المتاح
                $freelancer->increment('balance', $netToFreelancer);

                // ج- إضافة العمولة لرصيد الأدمن (ربح المنصة)
                if ($admin) {
                    $admin->increment('balance', $commission);
                }

                // 6. توثيق العمليات في جدول Transactions (اختياري ولكن ينصح به بشدة)
                
                // سجل للفريلانسر (دخل مشروع)
                Transaction::create([
                    'user_id' => $freelancer->id,
                    'amount' => $netToFreelancer,
                    'type' => 'project_revenue',
                    'description' => "Revenue from project: {$project->title} (Net)",
                    'trackable_id' => $project->id,
                    'trackable_type' => Project::class
                ]);

                // سجل للأدمن (عمولة المنصة)
                if ($admin) {
                    Transaction::create([
                        'user_id' => $admin->id,
                        'amount' => $commission,
                        'type' => 'platform_commission',
                        'description' => "Commission from project #{$project->id}",
                    ]);
                }
                

                // 7. تحديث حالات المشروع والعرض
                $project->update(['status' => 'completed']);
                $acceptedProposal->update(['status' => 'completed']);
            });

            // 8. إرسال الإشعارات للطرفين (الفريلانسر والعميل)
            NotificationHelper::sendNotification(
                $acceptedProposal->freelancer_id,
                'project_completed',
                'Project "' . $project->title . '" completed. Funds (after fees) added to your balance.'
            );

            NotificationHelper::sendNotification(
                $project->client_id,
                'project_completed',
                'You marked project "' . $project->title . '" as completed. Thanks for using our platform!'
            );

            return response()->json([
                'status' => true,
                'message' => 'Project completed. Freelancer paid and commission transferred to platform.',
                'project_status' => 'completed'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 400);
        }
    }

}
