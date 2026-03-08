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
    
    // 1. التحقق من صلاحية الكلينت وحالة المشروع
    if ($user->id !== $project->client_id || $project->status !== 'completed') {
        return response()->json(['message' => 'Unauthorized or project not completed'], 403);
    }

    // 2. البحث عن العرض المقبول مع التحقق من وجوده
    $acceptedProposal = $project->proposals()
    ->whereIn('status', ['accepted', 'completed']) 
    ->first();

    if (!$acceptedProposal) {
        return response()->json(['message' => 'No accepted proposal found for this project.'], 404);
    }

    $this->saveReview($project->id, $user->id, $acceptedProposal->freelancer_id, $request);

    return response()->json(['status' => true, 'message' => 'Freelancer rated!']);
}



public function reviewClient(Request $request, Project $project) {
    $user = $request->user();
    
    // 1. البحث عن العرض المقبول أولاً (لتجنب خطأ الـ Null في الشرط التالي)
    $acceptedProposal = $project->proposals()
    ->whereIn('status', ['accepted', 'completed']) 
    ->first();

    if (!$acceptedProposal || $user->id !== $acceptedProposal->freelancer_id || $project->status !== 'completed') {
        return response()->json(['message' => 'Unauthorized or project not completed'], 403);
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
