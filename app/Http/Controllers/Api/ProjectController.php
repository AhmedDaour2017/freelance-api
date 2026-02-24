<?php

namespace App\Http\Controllers\Api;

use App\Helpers\NotificationHelper;
use App\Http\Controllers\Controller;
use App\Models\Project;
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
            $query->where('status', 'open');
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
        return response()->json(['project' => $project->load('client:id,name,email')]);
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
    $project = Project::with('proposals')->findOrFail($id);
    $user = Auth::user();

    // 🔴 فقط صاحب المشروع يكمل
    if ($user->id !== $project->client_id) {
        return response()->json([
            'message' => 'Only the project owner can complete this project.'
        ], 403);
    }

    // 🔴 لازم يكون in_progress
    if ($project->status !== 'in_progress') {
        return response()->json([
            'message' => 'Project must be in progress to complete.'
        ], 400);
    }

    // 🔴 لازم يكون في عرض مقبول
    $acceptedProposal = $project->proposals()
        ->where('status', 'accepted')
        ->first();

    if (!$acceptedProposal) {
        return response()->json([
            'message' => 'No accepted proposal found.'
        ], 400);
    }

    DB::transaction(function () use ($project, $acceptedProposal) {

        $client = $project->client;
        $freelancer = User::find($acceptedProposal->freelancer_id);

        $amount = $acceptedProposal->price;

        // 🔴 حماية إضافية
        if ($client->pending_balance < $amount) {
            abort(400, 'Escrow balance mismatch.');
        }

        // 💰 تحرير الأموال
        $client->pending_balance -= $amount;
        $client->save();

        $freelancer->balance += $amount;
        $freelancer->save();

        // تحديث حالة المشروع
        $project->status = 'completed';
        $project->save();
    });

            NotificationHelper::sendNotification(
            $project->client_id,
            'project_completed',
            'You completed the project "' . $project->title . '" successfully!'
        );


        return response()->json([
            'message' => 'Project completed successfully.',
            'project_status' => $project->status
        ]);
    }



}
