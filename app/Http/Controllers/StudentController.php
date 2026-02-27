<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

use Illuminate\Support\Facades\Mail;

class StudentController extends Controller
{
    public function index()
    {
        $parents = Student::with('parents', 'user', 'school')->get();
        return response()->json($parents);

    }

public function getSchoolStudents(Request $request)
    {
        $schoolId = $request->header('X-School-ID');
        $students = Student::with('parents.user', 'user', 'school', 'classes')->where('schoolId', $schoolId)
        ->get();
        return response()->json($students);

    }




public function storeSchoolStudent(Request $request)
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
        'dateOfBirth' => 'nullable|date',
        'gender' => 'nullable|string|max:255',
        'bloodGroup' => 'nullable|string|max:255',
        'classId' => 'nullable|string|max:255',
    ]);

    try {

        $plainPassword = Str::random(8);

        $student = DB::transaction(function () use ($validated, $schoolId, $plainPassword) {

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

            // ✅ Create Student
            $student = Student::create([
                'userId' => $user->id,
                'schoolId' => $schoolId,
                'gender' => $validated['gender'] ?? null,
                'dateOfBirth' => $validated['dob'] ?? null,
                'bloodGroup' => $validated['bloodGroup'] ?? null,
            ]);

            // ✅ Assign Class (pivot table)
            if (!empty($validated['classId'])) {
                $student->classes()->attach(
                    $validated['classId'],
                    ['schoolId' => $schoolId]
                );
            }

            return $student;
        });

        $student->load(['user', 'school', 'classes']);

        return response()->json([
            'message' => 'Student created successfully',
            'student' => $student,
            'generated_password' => $plainPassword // ⚠ Remove in production
        ], 201);

    } catch (\Exception $e) {

        return response()->json([
            'message' => 'Failed to create student',
            'error' => $e->getMessage()
        ], 500);
    }
}

        public function destroyClass($classId)
    {
        $class = Student::where('classId', $classId);
        if (!$class) {
            return response()->json(['message' => 'Class not found'], 404);
        }

        $class->delete();
        return response()->json(['message' => 'Class deleted successfully']);
    }



public function updateStudent(Request $request, $studentId)
{
    $schoolId = $request->header('X-School-ID');

    $student = Student::where('studentId', $studentId)
        ->where('schoolId', $schoolId)
        ->firstOrFail();

    $validated = $request->validate([
        'firstName' => 'required|string|max:255',
        'lastName' => 'required|string|max:255',
        'otherNames' => 'nullable|string|max:255',
        'email' => [
            'required',
            'email',
            Rule::unique('users', 'email')->ignore($student->userId, 'id'),
        ],
        'phoneNumber' => 'required|string|max:20',
        'alternatePhoneNumber' => 'nullable|string|max:20',
        'dateOfBirth' => 'nullable|date',
        'gender' => 'nullable|string|max:255',
        'bloodGroup' => 'nullable|string|max:255',
        'classId' => 'nullable|integer|max:255',
        'parentId' => 'nullable|integer|max:255',
    ]);

    DB::transaction(function () use ($student, $validated, $schoolId, $request) {

        // Update user
        $student->user->update([
            'firstName' => $validated['firstName'],
            'lastName' => $validated['lastName'],
            'otherNames' => $validated['otherNames'] ?? null,
            'email' => $validated['email'],
            'phoneNumber' => $validated['phoneNumber'],
            'alternatePhoneNumber' => $validated['alternatePhoneNumber'] ?? null,
        ]);

        // Update student
        $student->update([
            'dateOfBirth' => $validated['dateOfBirth'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'bloodGroup' => $validated['bloodGroup'] ?? null,
        ]);

        // Update class pivot
        if (!empty($validated['classId'])) {
            $student->classes()->sync([
                $validated['classId'] => ['schoolId' => $schoolId]
            ]);
        }

        if (!empty($validated['parentId'])) {

    $student->parents()->syncWithoutDetaching([
        $validated['parentId']
    ]);
}
    });

    $student->load(['user', 'school', 'classes', 'parents']);

    return response()->json([
        'message' => 'Student updated successfully',
        'student' => $student,
    ]);
}
}


