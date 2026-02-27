<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use App\Models\Role;
use App\Models\Subject;
use App\Models\SubjectTeacher;
use App\Models\SchoolClass;
use App\Models\ClassSubject;
use App\Models\Teacher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

use Illuminate\Support\Facades\Mail;

class SubjectController extends Controller
{
    public function index()
    {
        $classes = Subject::with('subject_teachers', 'school')->get();
        return response()->json($classes);

    }

public function getSchoolSubjects(Request $request)
    {
        $schoolId = $request->header('X-School-ID');
        $subjects = Subject::with('teachers.user', 'subject_teachers.class', 'school')->where('schoolId', $schoolId)
        ->get();
        return response()->json($subjects);

    }

 public function getTeachersSubjects(Request $request)
{
    $schoolId = $request->header('X-School-ID');

    // Find the logged-in teacher record
    $teacher = SubjectTeacher::where('userId', auth()->id())
        ->where('schoolId', $schoolId)
        ->first();

    // If teacher is not assigned to any subject, return empty
    if (!$teacher) {
        return response()->json([
            'message' => 'No subjects assigned to this teacher',
            'subjects' => [],
        ], 200);
    }

    $subjects = Subject::with(['subject_teachers.user', 'school'])
        ->where('schoolId', $schoolId)
        ->whereHas('subject_teachers', function ($query) use ($teacher) {
            $query->where('subject_teachers.teacherId', $teacher->teacherId);
        })
        ->get();

    return response()->json($subjects);
}

    


    public function storeSchoolSubject(Request $request)
    {
        // Directly get the data from the request
        $schoolId = $request->header('X-School-ID');
        $validated =  $request->validate([
            'subjectName' => 'nullable|string|max:255',
        ]);

        $validated['schoolId'] = $schoolId;
        // Create a new user with the data (ensure that the fields are mass assignable in the model)
        $subjects = Subject::create($validated);
        $subjects->load('school');
        // Return a response, typically JSON
        return response()->json($subjects, 201); // HTTP status code 201: Created
    }

        public function destroySubject($subjectId)
    {
        $subject = Subject::where('subjectId', $subjectId);
        if (!$subject) {
            return response()->json(['message' => 'Subject not found'], 404);
        }

        $subject->delete();
        return response()->json(['message' => 'Subject deleted successfully']);
    }



public function updateSubject(Request $request, $subjectId)
{
    // Handle method spoofing (for file uploads)
    // if ($request->has('_method') && strtoupper($request->_method) === 'PUT') {
    //     // Laravel will now parse files correctly because it's treated as POST
    // }

    $validated = $request->validate([
        'subjectName' => 'required|string|max:255',
    ]);

    $user = auth()->user();
    $schoolId = $request->header('X-School-ID');

    $subject = Subject::where('schoolId', $schoolId)
        ->where('subjectId', $subjectId)
        ->firstOrFail();

    $updateData = $validated;

 

    $subject->update($updateData);
    $subject->load('subject_teacher', 'school');

    return response()->json([
        'message' => 'Subject updated successfully',
        'subject' => $subject,
    ]);
}


public function assignTeacher(Request $request, int $subjectId): JsonResponse
{
    $schoolId = $request->header('X-School-ID');

    if (!$schoolId) {
        return response()->json([
            'message' => 'School ID header missing'
        ], 400);
    }

    $validated = $request->validate([
        'teacherIds'   => 'required|array',
        'teacherIds.*' => 'required|integer|exists:teachers,teacherId',
    ]);

    // Confirm subject exists in this school
    $subject = Subject::where('subjectId', $subjectId)
        ->where('schoolId', $schoolId)
        ->firstOrFail();

    // Pull all classes offering this subject
    $classSubjects = ClassSubject::where('schoolId', $schoolId)
        ->where('subjectId', $subjectId)
        ->get();

    if ($classSubjects->isEmpty()) {
        return response()->json([
            'message' => 'Subject is not assigned to any class'
        ], 400);
    }

    try {
        foreach ($validated['teacherIds'] as $teacherId) {

            // Fetch teacher for this school
            $teacher = Teacher::where('teacherId', $teacherId)
                ->where('schoolId', $schoolId)
                ->firstOrFail();

            foreach ($classSubjects as $classSubject) {
                // Insert or update each teacher-class-subject record
                SubjectTeacher::updateOrCreate(
                    [
                        'subjectId' => $subjectId,
                        'teacherId' => $teacherId,
                        'classId'   => $classSubject->classId,
                    ],
                    [
                        'schoolId' => $schoolId,
                        'userId'   => $teacher->userId,
                    ]
                );
            }
        }

        return response()->json([
            'message' => 'Teachers assigned to all classes successfully',
            'subject' => $subject->load('subject_teachers.user', 'teachers'),
            'assigned_teacher_count' => count($validated['teacherIds']),
            'class_count' => $classSubjects->count(),
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Failed to assign teachers',
            'error'   => $e->getMessage(),
        ], 500);
    }
}

public function assignClassToSubject(Request $request, int $subjectId): JsonResponse
{
    $schoolId = $request->header('X-School-ID');

    if (!$schoolId) {
        return response()->json([
            'message' => 'School ID header missing'
        ], 400);
    }

    $validated = $request->validate([
        'classId' => 'required|integer|exists:classes,classId',
    ]);

    try {

        // Confirm subject belongs to this school
        $subject = Subject::where('subjectId', $subjectId)
            ->where('schoolId', $schoolId)
            ->firstOrFail();

        // Confirm class belongs to this school
        $class = SchoolClass::where('classId', $validated['classId'])
            ->where('schoolId', $schoolId)
            ->firstOrFail();

        // Assign class to subject (prevent duplicates)
        ClassSubject::updateOrCreate(
            [
                'classId'   => $validated['classId'],
                'subjectId' => $subjectId,
                'schoolId'  => $schoolId,
            ],
            [] // nothing to update
        );

        // $subject->load(['class']);

        return response()->json([
            'message' => 'Class assigned to subject successfully',
            'subject' => $subject,
            'classId' => $validated['classId'],
        ], 200);

    } catch (\Exception $e) {

        return response()->json([
            'message' => 'Failed to assign class to subject',
            'error'   => $e->getMessage(),
        ], 500);
    }
}

}


