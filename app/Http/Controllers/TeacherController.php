<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use App\Models\Role;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

use Illuminate\Support\Facades\Mail;

class TeacherController extends Controller
{
    public function index()
    {
        $teachers = Teacher::with('user', 'school')->get();
        return response()->json($teachers);

    }

public function getSchoolTeachers(Request $request)
    {
        $schoolId = $request->header('X-School-ID');
        $teacher = Teacher::with('user', 'school')->where('schoolId', $schoolId)
        ->get();
        return response()->json($teacher);

    }



public function storeSchoolTeacher(Request $request)
{
    $schoolId = $request->header('X-School-ID');

    if (!$schoolId) {
        return response()->json([
            'message' => 'School ID header missing'
        ], 400);
    }

    $validated = $request->validate([
        'firstName' => 'required|string|max:255',
        'lastName' => 'required|string|max:255',
        'otherNames' => 'nullable|string|max:255',
        'email' => 'required|email|unique:users,email',
        'phoneNumber' => 'required|string|max:20',
        'alternatePhoneNumber' => 'nullable|string|max:20',
        // 'className' => 'nullable|string|max:255',
    ]);

    try {

        $teacher = null;
        $plainPassword = null;

        DB::transaction(function () use ($validated, $schoolId, &$teacher, &$plainPassword) {

            // 🔐 Generate random password
            $plainPassword = Str::random(8);

            // ✅ Create User
            $user = User::create([
                'firstName' => $validated['firstName'],
                'lastName' => $validated['lastName'],
                'otherNames' => $validated['otherNames'] ?? null,
                'email' => $validated['email'],
                'phoneNumber' => $validated['phoneNumber'],
                'alternatePhoneNumber' => $validated['alternatePhoneNumber'] ?? null,
                'password' => Hash::make($plainPassword),
            ]);

            // ✅ Create Teacher
            $teacher = Teacher::create([
                'userId' => $user->id,
                'schoolId' => $schoolId,
                // 'className' => $validated['className'] ?? null,
            ]);
        });

        return response()->json([
            'message' => 'Teacher created successfully',
            'teacher' => $teacher,
            'generated_password' => $plainPassword // ⚠ remove in production
        ], 201);

    } catch (\Exception $e) {

        return response()->json([
            'message' => 'Failed to create teacher',
            'error' => $e->getMessage()
        ], 500);
    }

}
        public function destroyClass($classId)
    {
        $class = SchoolClass::where('classId', $classId);
        if (!$class) {
            return response()->json(['message' => 'Class not found'], 404);
        }

        $class->delete();
        return response()->json(['message' => 'Class deleted successfully']);
    }



public function updateClass(Request $request, $classId)
{
    // Handle method spoofing (for file uploads)
    // if ($request->has('_method') && strtoupper($request->_method) === 'PUT') {
    //     // Laravel will now parse files correctly because it's treated as POST
    // }

    $validated = $request->validate([
        'className' => 'required|string|max:255',
    ]);

    $user = auth()->user();
    $schoolId = $request->header('X-School-ID');

    $class = SchoolClass::where('schoolId', $schoolId)
        ->where('classId', $classId)
        ->firstOrFail();

    $updateData = $validated;

 

    $class->update($updateData);
    $class->load('school');

    return response()->json([
        'message' => 'Class updated successfully',
        'class' => $class,
    ]);
}

}


