<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class StudentJwtMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        try {
            // 1️⃣ Get token from cookie
            $token = $request->cookie('student_token');

            if (!$token) {
                return response()->json([
                    'message' => 'Unauthorized. Token missing.'
                ], 401);
            }

            // 2️⃣ Set token manually for JWTAuth
            JWTAuth::setToken($token);

            // 3️⃣ Authenticate token
            $payload = JWTAuth::getPayload();

            // 4️⃣ Optional: enforce role
            if ($payload->get('role') !== 'student_access') {
                return response()->json([
                    'message' => 'Forbidden. Invalid access role.'
                ], 403);
            }

        } catch (JWTException $e) {
            return response()->json([
                'message' => 'Token invalid or expired.',
                'error'   => $e->getMessage()
            ], 401);
        }

        return $next($request);
    }
}