<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Models\StudentParentPivot;

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
        $parents = Student::with('parents', 'user', 'school', 'classes', 'club', 'house')->get();
        return response()->json($parents);

    }

public function getSchoolStudents(Request $request)
    {
        $schoolId = $request->header('X-School-ID');
        $students = Student::with('parents.user', 'user', 'school', 'classes', 'club', 'house')->where('schoolId', $schoolId)
        ->get();
        return response()->json($students);

    }


    public function generateAdmissionNumber($schoolId)
    {
        $latestStudent = Student::where('schoolId', $schoolId)
    ->orderByDesc('admissionNumber')
    ->first();

        if ($latestStudent && $latestStudent->admissionNumber){
            $lastNumber = (int) preg_replace('/\D/', '', $latestStudent->admissionNumber);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return "ADM-{$newNumber}";
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
        'classId' => 'nullable|integer|exists:classes,classId',
        'passport' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        'clubId' => 'nullable|integer|exists:clubs,clubId',
        'houseId' => 'nullable|integer|exists:houses,houseId',
        'admissionNumber' => 'nullable|string|max:255',
        'parentId' => 'nullable|integer|exists:parents,parentId',
    ]);

    if ($request->hasFile('passport')) {
            $logoFile = $request->file('passport');
            $logoPath = $logoFile->store('student-passport', 'public');
            $validated['passport'] = $logoPath;
        } else {
            $validated['passport'] = null;
        }

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
                'dateOfBirth' => $validated['dateOfBirth'] ?? null,
                'bloodGroup' => $validated['bloodGroup'] ?? null,
                'clubId' => $validated['clubId'] ?? null,
                'houseId' => $validated['houseId'] ?? null,
                'parentId' => $validated['parentId'] ?? null,
                'schoolAssignedAdmissionNumber' => $validated['admissionNumber'] ?? null,
                'admissionNumber' =>  $this->generateAdmissionNumber($schoolId),
                'passport' => $validated['passport'] ?? null,
            ]);

            $student_parent = StudentParentPivot::create([
                'schoolId' => $schoolId,
                'studentId' => $student->studentId,
                'parentId' => $validated['parentId'] ?? null,
                
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

        $student->load('user', 'school', 'parents.user', 'classes', 'club', 'house');
        
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
        'firstName' => 'sometimes|string|max:255',
        'lastName' => 'sometimes|string|max:255',
        'otherNames' => 'nullable|string|max:255',
        'email' => [
            'sometimes',
            'email',
            Rule::unique('users', 'email')->ignore($student->userId, 'id'),
        ],
        'phoneNumber' => 'sometimes|string|max:20',
        'alternatePhoneNumber' => 'nullable|string|max:20',
        'dateOfBirth' => 'nullable|date',
        'gender' => 'nullable|string|max:255',
        'bloodGroup' => 'nullable|string|max:255',
        'classId' => 'nullable|integer|max:255',
        'parentId' => 'nullable|integer|max:255',
        'clubId' => 'nullable|integer|exists:clubs,clubId',
        'houseId' => 'nullable|integer|exists:houses,houseId',
        'passport' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',

    ]);

    DB::transaction(function () use ($student, $validated, $schoolId, $request) {

    // Handle logo upload
        if ($request->hasFile('passport')) {
            $logoFile = $request->file('passport');
            $logoPath = $logoFile->store('student-passport', 'public');
            $validated['passport'] = $logoPath;
        } else {
            $validated['passport'] = null;
        }

        // Update user
        $userData = collect($validated)->only([
    'firstName',
    'lastName',
    'otherNames',
    'email',
    'phoneNumber',
    'alternatePhoneNumber',
])->toArray();

$student->user->update($userData);


        // Update student
        $studentData = collect($validated)->only([
    'dateOfBirth',
    'gender',
    'bloodGroup',
    'clubId',
    'houseId',
    'passport',
])->toArray();

$student->update($studentData);
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

    $student->load(['user', 'school', 'classes', 'parents.user', 'club', 'house']);

    return response()->json([
        'message' => 'Student updated successfully',
        'student' => $student,
    ]);
}
}


