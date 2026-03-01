<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
// تقييم الفريلانسر (يستدعيها الكلينت)
    public function reviewFreelancer(Request $request, Project $project) {
        $user = $request->user();
        
        // الشرط: الكلينت صاحب المشروع والمشروع مكتمل
        if ($user->id !== $project->client_id || $project->status !== 'completed') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $freelancerId = $project->proposals()->where('status', 'accepted')->first()->freelancer_id;

        $this->saveReview($project->id, $user->id, $freelancerId, $request);

        return response()->json(['status' => true, 'message' => 'Freelancer rated!']);
    }

    // تقييم الكلينت (يستدعيها الفريلانسر الفائز)
    public function reviewClient(Request $request, Project $project) {
        $user = $request->user();
        $acceptedProposal = $project->proposals()->where('status', 'accepted')->first();

        // الشرط: الفريلانسر الفائز والمشروع مكتمل
        if ($user->id !== $acceptedProposal->freelancer_id || $project->status !== 'completed') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $this->saveReview($project->id, $user->id, $project->client_id, $request);

        return response()->json(['status' => true, 'message' => 'Client rated!']);
    }

    // دالة داخلية خاصة للحفظ (Private) لتجنب تكرار الكود
    private function saveReview($projectId, $reviewerId, $userId, $request) {
        $request->validate(['rating' => 'required|int|min:1|max:5', 'comment' => 'required|string']);
        return Review::create([
            'project_id' => $projectId,
            'reviewer_id' => $reviewerId,
            'user_id' => $userId,
            'rating' => $request->rating,
            'comment' => $request->comment
        ]);
    }
}
