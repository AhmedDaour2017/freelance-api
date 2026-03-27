<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Portfolio;
use Illuminate\Http\Request;

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
}
