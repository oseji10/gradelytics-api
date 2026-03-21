<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\StaffResource;
use App\Models\Teacher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $schoolId = $request->header('X-School-ID');

        if (!$schoolId) {
            return response()->json([
                'message' => 'School not found',
            ], 401);
        }

        $validated = $request->validate([
            'classId' => ['nullable', 'integer'],
            'role' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
        ]);

        $query = Teacher::query()
            ->with('assignedClass')
            ->with('user.user_role')
            ->where('schoolId', $schoolId);

        if (!empty($validated['classId'])) {
            $query->where('classId', $validated['classId']);
        }

        if (!empty($validated['role']) && strtolower($validated['role']) !== 'all') {
            $query->whereRaw('LOWER(role) = ?', [strtolower($validated['role'])]);
        }

        if (!empty($validated['status']) && strtolower($validated['status']) !== 'all') {
            $query->whereRaw('LOWER(status) = ?', [strtolower($validated['status'])]);
        }

        if (!empty($validated['search'])) {
            $search = trim($validated['search']);

            $query->where(function ($q) use ($search) {
                $q->where('firstName', 'like', "%{$search}%")
                    ->orWhere('lastName', 'like', "%{$search}%")
                    ->orWhere('otherNames', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phoneNumber', 'like', "%{$search}%")
                    ->orWhere('employeeId', 'like', "%{$search}%")
                    ->orWhere('department', 'like', "%{$search}%")
                    ->orWhere('role', 'like', "%{$search}%");
            });
        }

        $staff = $query
            // ->orderBy('lastName')
            // ->orderBy('firstName')
            ->get();

        return response()->json($staff);
    }
}