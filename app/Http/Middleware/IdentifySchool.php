<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class IdentifySchool
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $publicRoutes = [
            'signin',
            'signup',
            'refresh',
            'logout',
            'resend-otp',
            'verify-otp',
            'setup-password',
            'roles',
            'stripe/webhook',
            'learning',
        ];

        $schoolOptionalRoutes = [
            'schools', // allow creating first school
        ];

        $path = $request->path();

        // Skip public routes
        if (in_array($path, $publicRoutes)) {
            return $next($request);
        }

        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Allow school creation if user has no schools yet
        $userschoolsCount = $user->default_school()->count();
        if ($userschoolsCount === 0 && in_array($path, $schoolOptionalRoutes) && $request->isMethod('POST')) {
            return $next($request);
        }

        // -------------------------
        // Admins bypass school
        // -------------------------
        $role = $user->user_role->roleName ?? '';
        $isAdmin = in_array(strtoupper($role), ['ADMIN', 'SUPER_ADMIN', 'SUPERADMIN']);
        if ($isAdmin) {
            // Admin does not need school ID
            return $next($request);
        }

        // For non-admins, school ID is required
        $schoolId = $request->header('X-School-ID');

        if (!$schoolId) {
            return response()->json(['message' => 'school ID is required.'], 400);
        }

        // Validate that this school belongs to the user
         $school = $user->default_school()->first();
        //  $school = $user->default_school()->where('schools.schoolId', $schoolId)->first();

        if (!$school) {
            return response()->json(['message' => 'Invalid or unauthorized school.'], 403);
        }

        // Optional: Check if school is active
        if ($school->status !== 'active') {
            return response()->json(['message' => 'school is not active.'], 403);
        }

        // Bind school to request and container
        app()->instance('currentschool', $school);
        $request->merge(['school' => $school]);

        return $next($request);
    }
}
