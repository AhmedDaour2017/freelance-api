<?php


use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\WithdrawalController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProposalController;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Notification;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
});



//Client Routes
Route::middleware(['auth:sanctum', 'client'])->group(function () {
    Route::post('/projects', [ProjectController::class, 'store']);
    //Route::get('/client/projects', [ProjectController::class, 'clientProjects']);
    //accept proposal from client
    Route::post('/proposals/{proposal}/accept', [ProposalController::class, 'accept']);
    Route::post('/proposals/{proposal}/reject', [ProposalController::class, 'reject']);
        //completed project client
    Route::post('/projects/{project}/complete', [ProjectController::class, 'complete']);
});

    // Project details
    Route::get('/projects/{project}', [ProjectController::class, 'show']);
    
Route::middleware('auth:sanctum')->group(function () {
    // Update/Delete (Client only or Admin)
    Route::put('/projects/{project}', [ProjectController::class, 'update']);
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);

    //Rating
    Route::post('/projects/{project}/review-freelancer', [ReviewController::class, 'reviewFreelancer']);
    Route::post('/projects/{project}/review-client', [ReviewController::class, 'reviewClient']);

    
        // جلب الإشعارات (آخر 15 إشعار)
    Route::get('/notifications', function (Request $request) {
        return Notification::where('user_id', $request->user()->id)
        ->orderBy('created_at', 'desc')
        ->limit(10)
        ->get();
    });

    // تحديد كل الإشعارات كمقروءة
    Route::post('/notifications/read', function (Request $request) {
        $request->user()->unreadNotifications->markAsRead();
        return response()->json(['status' => true, 'message' => 'All marked as read']);
    });

});




//Freelancer Routes
Route::middleware(['auth:sanctum', 'freelancer'])->group(function () {
    Route::post('/projects/{project}/proposals', [ProposalController::class, 'store']);
    Route::post('/withdraw', [WithdrawalController::class, 'requestWithdrawal']);
});



//Admin Routes
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    //Route::get('/admin/projects', [ProjectController::class, 'adminProjects']);
    Route::post('/withdrawals/{id}/approve', [WithdrawalController::class, 'approve']);
    Route::post('/withdrawals/{id}/reject', [WithdrawalController::class, 'reject']);
});


Route::middleware('auth:sanctum')->get('/profile', function (Request $request) {
    return $request->user();
});


Route::middleware('auth:sanctum')->get('/users/{user}/profile', function (User $user) {
    // تحميل التقييمات مع التأكد من وجود الموديل
    return response()->json([
        'user' => $user->load(['reviews']) 
    ]);
});


Route::middleware('auth:sanctum')->get('/projects', [ProjectController::class, 'index']);

