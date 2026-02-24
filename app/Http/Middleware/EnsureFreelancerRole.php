<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureFreelancerRole
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated: Please login first.'
            ], 401);
        }

        if ($user->role !== 'freelancer') {
            return response()->json([
                'message' => 'Unauthorized: Only freelancers can send proposals.'
            ], 403);
        }

        return $next($request);
    }
}
