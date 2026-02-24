<?php

namespace App\Http\Controllers\Api;

use App\Helpers\NotificationHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WithdrawalRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WithdrawalController extends Controller
{


public function requestWithdrawal(Request $request)
{
    $request->validate([
        'amount' => 'required|numeric|min:1'
    ]);

    $user = Auth::user();

    // 🔴 فقط freelancer
    if ($user->role !== 'freelancer') {
        return response()->json([
            'message' => 'Only freelancers can request withdrawals.'
        ], 403);
    }

    // 🔴 تحقق من الرصيد
    if ($user->balance < $request->amount) {
        return response()->json([
            'message' => 'Insufficient balance.'
        ], 400);
    }

    $withdrawal = WithdrawalRequest::create([
        'user_id' => $user->id,
        'amount' => $request->amount,
        'status' => 'pending'
    ]);

    return response()->json([
        'message' => 'Withdrawal request submitted successfully.',
        'data' => $withdrawal
    ]);
}




//Approve Admin 
public function approve($id)
{
    $withdrawal = WithdrawalRequest::with('user')->findOrFail($id);

    if ($withdrawal->status !== 'pending') {
        return response()->json([
            'message' => 'This request has already been processed.'
        ], 400);
    }

    DB::transaction(function () use ($withdrawal) {

        $user = $withdrawal->user;

        if ($user->balance < $withdrawal->amount) {
            abort(400, 'User balance insufficient.');
        }

        // 💸 خصم الرصيد
        $user->balance -= $withdrawal->amount;
        $user->save();

        $withdrawal->status = 'approved';
        $withdrawal->save();
    });

        // 🔔 إشعار للفريلانسر
    NotificationHelper::sendNotification(
        $withdrawal->user_id,
        'withdrawal_approved',
        'Your withdrawal request of $' . $withdrawal->amount . ' has been approved.'
    );

    return response()->json([
        'message' => 'Withdrawal approved and balance deducted.'
    ]);
}



//Reject Admin

    public function reject(Request $request, $id)
    {
        $withdrawal = WithdrawalRequest::findOrFail($id);

        if ($withdrawal->status !== 'pending') {
            return response()->json([
                'message' => 'This request has already been processed.'
            ], 400);
        }

        $withdrawal->status = 'rejected';
        $withdrawal->admin_note = $request->admin_note;
        $withdrawal->save();

            // 🔔 إشعار للفريلانسر
        NotificationHelper::sendNotification(
            $withdrawal->user_id,
            'withdrawal_rejected',
            'Your withdrawal request of $' . $withdrawal->amount . ' was rejected. Reason: ' . $request->admin_note
        );

        return response()->json([
            'message' => 'Withdrawal request rejected.'
        ]);
    }


}
