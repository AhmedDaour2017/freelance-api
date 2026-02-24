<?php


use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WithdrawalController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProposalController;
use Illuminate\Http\Request;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
});



//Client Routes
Route::middleware(['auth:sanctum', 'client'])->group(function () {
    Route::post('/projects', [ProjectController::class, 'store']);
    //Route::get('/client/projects', [ProjectController::class, 'clientProjects']);
});

    // Project details
    Route::get('/projects/{project}', [ProjectController::class, 'show']);
    
Route::middleware('auth:sanctum')->group(function () {
    // Update/Delete (Client only or Admin)
    Route::put('/projects/{project}', [ProjectController::class, 'update']);
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);

    //accept proposal from admin or client
    Route::post('/proposals/{proposal}/accept', [ProposalController::class, 'accept']);
    //completed proposal from admin or client
    Route::post('/projects/{project}/complete', [ProjectController::class, 'complete']);
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


Route::middleware('auth:sanctum')->get('/projects', [ProjectController::class, 'index']);

