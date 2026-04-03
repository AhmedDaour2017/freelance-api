<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Portfolio;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

    class PortfolioController extends Controller
    {
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048', // نفس شروط صور البروفايل
            'link'  => 'nullable|url'
        ]);

        $user = $request->user();
        if ($user->role !== 'freelancer') {
            return response()->json([
                'message' => 'Unauthorized. Only freelancers can create a portfolio.'
            ], 403);
        }

        $request->validate([
        'title'=> 'required|string|max:100',
        'description'=> 'required|string|max:1000', // أضفنا الوصف هنا
        'image'=> 'required|image|mimes:jpeg,png,jpg|max:2048',
        'link'=> 'nullable|url'
        ]);

        // نفس منطق التخزين الخاص بك
        $imagePath = $request->file('image')->store('portfolios', 'public');

        // إنشاء سجل نموذج العمل الجديد
        $portfolio = Portfolio::create([
            'user_id' => $user->id,
            'title'   => $request->title,
            'description' => $request->description,
            'image'   => $imagePath,
            'link'    => $request->link,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Work added to portfolio!',
            'portfolio' => $portfolio
        ]);
    }







    public function update(Request $request, Portfolio $portfolio)
    {

        // الحماية
        if ($request->user()->id !== $portfolio->user_id) return response()->json(['message' => 'Unauthorized'], 403);

        $request->validate([
            'title' => 'required|string|max:100',
            'description' => 'required|string',
            'image'=> 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $portfolio->title = $request->title;
        $portfolio->description = $request->description;
        $portfolio->link = $request->link;

        // إذا رفع صورة جديدة، نحذف القديمة ونخزن الجديدة
        if ($request->hasFile('image')) {
            Storage::disk('public')->delete($portfolio->image);
            $portfolio->image = $request->file('image')->store('portfolios', 'public');
        }

        $portfolio->save();

        return response()->json([
        'status' => true,
        'message' => 'Updated successfully',
        'portfolio' => $portfolio
    ], 200);
    }










    public function destroy(Request $request, Portfolio $portfolio)
    {
        // 1. الحماية: التأكد أن الفريلانسر هو صاحب العمل فعلاً
        if ($request->user()->id  !== $portfolio->user_id) {
            return response()->json(['message' => 'Unauthorized! This is not your work.'], 403);
        }

        // 2. حذف الصورة الحقيقية من السيرفر (Storage)
        if ($portfolio->image) {
            Storage::disk('public')->delete($portfolio->image);
        }

        // 3. حذف السجل من قاعدة البيانات
        $portfolio->delete();

        return response()->json([
            'status' => true,
            'message' => 'Work deleted successfully from your portfolio.',
            'portfolio' => $portfolio
        ], 200);
    }



}
