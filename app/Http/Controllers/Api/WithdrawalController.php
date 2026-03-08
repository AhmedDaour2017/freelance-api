<?php

namespace App\Http\Controllers\Api;

use App\Helpers\NotificationHelper;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\WithdrawalRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WithdrawalController extends Controller
{



    public function allRequests(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // جلب الطلبات المعلقة مع بيانات المستخدم (اسم الفريلانسر)
        $requests = WithdrawalRequest::with('user')
            ->where('status', 'pending')
            ->latest()
            ->get();

        return response()->json($requests);
    }





    public function requestWithdrawal(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:50'
        ]);

        try {
            // نستخدم الترانزاكشن لضمان تنفيذ كل العمليات أو فشلها معاً
            $withdrawal = DB::transaction(function () use ($request) {
                
                $user = User::where('id', Auth::id())->lockForUpdate()->first();

                if ($user->role !== 'freelancer') {
                    throw new \Exception('Only freelancers can request withdrawals.');
                }

                if ($user->balance < $request->amount) {
                    throw new \Exception('Insufficient balance.');
                }

                // 1. تجميد الرصيد
                $user->decrement('balance', $request->amount);
                $user->increment('pending_balance', $request->amount);

                // 2. إنشاء طلب السحب
                $newWithdrawal = WithdrawalRequest::create([
                    'user_id' => $user->id,
                    'amount' => $request->amount,
                    'status' => 'pending',
                ]);

                // 3. تسجيل الحركة المالية (داخل الترانزاكشن لضمان الأمان)
                Transaction::create([
                    'user_id' => $user->id,
                    'amount' => -$request->amount, // 💡 بالسالب لأنه خصم من الرصيد المتاح
                    'type' => 'withdrawal', 
                    'description' => "Withdrawal request #{$newWithdrawal->id} (Pending Review)",
                    'trackable_id' => $newWithdrawal->id,
                    'trackable_type' => WithdrawalRequest::class
                ]);

                return $newWithdrawal;
            });

            return response()->json([
                'status' => true,
                'message' => 'Withdrawal request submitted. Funds are now frozen until admin approval.',
                'data' => $withdrawal
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }



//Approve Admin 
    public function approve($id)
    {
        $withdrawal = WithdrawalRequest::with('user')->findOrFail($id);

        // 1. التحقق من الحالة
        if ($withdrawal->status !== 'pending') {
            return response()->json(['message' => 'This request has already been processed.'], 400);
        }

        try {
            DB::transaction(function () use ($withdrawal) {
                $user = $withdrawal->user()->lockForUpdate()->first();

                // ⚠️ ملاحظة: بما أننا "جمدنا" المبلغ عند الطلب في pending_balance
                // فإننا الآن نخصمه نهائياً من المعلق (لأنه خرج من المنصة فعلياً)
                if ($user->pending_balance < $withdrawal->amount) {
                    throw new \Exception('Insufficient pending balance for this withdrawal.');
                }

                // 💸 الخصم النهائي من الرصيد المعلق
                $user->decrement('pending_balance', $withdrawal->amount);

                // تحديث حالة الطلب
                $withdrawal->update(['status' => 'approved']);
                
                // 📝 توثيق العملية (خروج مال من المنصة)
                
                Transaction::create([
                    'user_id' => $user->id,
                    'amount' => $withdrawal->amount,
                    'type' => 'withdrawal',
                    'description' => "Withdrawal approved and sent to user.",
                    'trackable_id' => $withdrawal->id,
                    'trackable_type' => get_class($withdrawal)
                ]);
                
            });

            // 🔔 إشعار للفريلانسر
            NotificationHelper::sendNotification(
                $withdrawal->user_id,
                'withdrawal_approved',
                'Your withdrawal request of $' . $withdrawal->amount . ' has been approved and processed.'
            );

            return response()->json([
                'status' => true,
                'message' => 'Withdrawal approved. Funds deducted from pending balance.'
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }




//Reject Admin

    public function reject(Request $request, $id)
    {
        // جلب الطلب مع بيانات المستخدم
        $withdrawal = WithdrawalRequest::with('user')->findOrFail($id);

        if ($withdrawal->status !== 'pending') {
            return response()->json([
                'message' => 'This request has already been processed.'
            ], 400);
        }

        try {
            DB::transaction(function () use ($withdrawal, $request) {
                // قفل سجل المستخدم لضمان دقة العملية الحسابية
                $user = User::where('id', $withdrawal->user_id)->lockForUpdate()->first();

                // 💸 فك تجميد الأموال: إعادة المبلغ من المعلق إلى المتاح
                if ($user->pending_balance >= $withdrawal->amount) {
                    $user->decrement('pending_balance', $withdrawal->amount);
                    $user->increment('balance', $withdrawal->amount);
                }

                // تحديث حالة الطلب وإضافة ملاحظة الأدمن
                $withdrawal->update([
                    'status' => 'rejected',
                    'admin_note' => $request->admin_note
                ]);

                // 📝 توثيق عملية "إرجاع الأموال" في جدول العمليات (اختياري)
                
                Transaction::create([
                    'user_id' => $user->id,
                    'amount' => $withdrawal->amount,
                    'type' => 'refund',
                    'description' => "Refunded withdrawal request #{$withdrawal->id} (Rejected by Admin)",
                    'trackable_id' => $withdrawal->id,
                    'trackable_type' => get_class($withdrawal)
                ]);
                
            });

            // 🔔 إرسال إشعار للفريلانسر بالرفض مع السبب
            NotificationHelper::sendNotification(
                $withdrawal->user_id,
                'withdrawal_rejected',
                'Your withdrawal request of $' . $withdrawal->amount . ' was rejected. Reason: ' . $request->admin_note
            );

            return response()->json([
                'status' => true,
                'message' => 'Withdrawal request rejected and funds returned to user balance.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 400);
        }
    }


}
