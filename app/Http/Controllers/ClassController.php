<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\ClassStudent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;


use Illuminate\Support\Facades\Mail;

class ClassController extends Controller
{
    public function index()
    {
        $classes = SchoolClass::with('class_teachers', 'school')->get();
        return response()->json($classes);

    }

public function getSchoolClasses(Request $request)
    {
        $schoolId = $request->header('X-School-ID');
        $classes = SchoolClass::with('class_teachers.user', 'school')->where('schoolId', $schoolId)
        ->get();
        return response()->json($classes);

    }

    public function getSchoolClassStudents(Request $request, $classId)
    {
        $schoolId = $request->header('X-School-ID');
        $classes = ClassStudent::with('student.user')->where('schoolId', $schoolId)
        ->where('classId', $classId)
        ->get();
        return response()->json($classes);

    }

    

    public function storeSchoolClass(Request $request)
    {
        // Directly get the data from the request
        $schoolId = $request->header('X-School-ID');
        $validated =  $request->validate([
            'className' => 'nullable|string|max:255',
        ]);

        $validated['schoolId'] = $schoolId;
        // Create a new user with the data (ensure that the fields are mass assignable in the model)
        $classes = SchoolClass::create($validated);
        $classes->load('school');

        // Return a response, typically JSON
        return response()->json($classes, 201); // HTTP status code 201: Created
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


public function assignTeacher(Request $request, int $classId): JsonResponse
{
    $schoolId = $request->header('X-School-ID');

    if (!$schoolId) {
        return response()->json([
            'message' => 'School ID header missing'
        ], 400);
    }

    $class = SchoolClass::where('classId', $classId)
        ->where('schoolId', $schoolId) // 🔥 important for multi-tenant safety
        ->firstOrFail();

    $validated = $request->validate([
        'teacherIds'   => 'required|array',
        'teacherIds.*' => 'required|integer|exists:teachers,teacherId',
    ]);

    $teacherIds = $validated['teacherIds'];

    try {

        $syncData = [];

        foreach ($teacherIds as $teacherId) {
            $syncData[$teacherId] = [
                'schoolId' => $schoolId
            ];
        }

        $class->class_teachers()->sync($syncData);

        return response()->json([
            'message' => 'Teachers assigned successfully',
            'class' => $class->load('class_teachers'),
            'assigned_teacher_count' => count($teacherIds),
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Failed to assign teachers',
            'error'   => $e->getMessage(),
        ], 500);
    }
}

}


