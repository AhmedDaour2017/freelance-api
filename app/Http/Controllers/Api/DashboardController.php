<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\Transaction;
use App\Models\Review;
use App\Models\WithdrawalRequest;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
public function getStats(Request $request)
{
    $user = Auth::user();
    $role = $user->role;
    $stats = [];

    if ($role === 'freelancer') {
        // إحصائيات الفريلانسر بناءً على عروضه (Proposals)
        $stats = [
            'label1' => 'Completed Projects',
            'val1'   => Proposal::where('freelancer_id', $user->id)->where('status', 'completed')->count(),
            
            'label2' => 'Projects In Progress',
            'val2'   => Proposal::where('freelancer_id', $user->id)->where('status', 'accepted')->count(),
            
            'label3' => 'Open Projects',
            'val3'   => Proposal::where('freelancer_id', $user->id)->where('status', 'pending')->count(),
            
            'label4' => 'Average Rating',
            'val4'   => number_format(Review::where('user_id', $user->id)->avg('rating') ?? 0, 1) . ' ★',
        ];
    } 
    elseif ($role === 'client') {
        $stats = [
            'label1' => 'Completed Projects',
            'val1'   => Project::where('client_id', $user->id)->where('status', 'completed')->count(),
            
            'label2' => 'Projects In Progress',
            'val2'   => Project::where('client_id', $user->id)->where('status', 'in_progress')->count(),
            
            'label3' => 'Open Projects',
            'val3'   => Project::where('client_id', $user->id)->where('status', 'open')->count(),
            
            'label4' => 'Total Offers Received',
            'val4'   => Proposal::whereIn('project_id', Project::where('client_id', $user->id)->pluck('id'))->count(),
        ];
    } 
        elseif ($role === 'admin') {
            $stats = [
                'label1' => 'Total Users',
                'val1'   => \App\Models\User::count(),
                
                'label2' => 'Projects In Progress',
                'val2'   => Project::where('status', 'in_progress')->count(),
                
                'label3' => 'Total Balance',
                'val3'   => '$' . number_format($user->balance, 2),
                
                'label4' => 'Withdrawal Requests',
                'val4'   => WithdrawalRequest::where('status', 'pending')->count(),
            ];
        }

        // جلب آخر 5 عمليات مالية للمستخدم
        $recentTransactions = Transaction::where('user_id', $user->id)
            ->latest()
            ->take(5)
            ->get();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'role' => $user->role,
                'balance' => number_format($user->balance, 2),
                'pending_balance' => number_format($user->pending_balance, 2),
            ],
            'stats' => $stats,
            'transactions' => $recentTransactions
        ]);
    }
}