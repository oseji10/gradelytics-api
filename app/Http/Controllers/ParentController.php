<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use App\Models\Role;
use App\Models\StudentParent;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;



use Illuminate\Http\JsonResponse;


use Illuminate\Support\Facades\Mail;

class ParentController extends Controller
{
    public function index()
    {
        $parents = StudentParent::with('students', 'user', 'school')->get();
        return response()->json($parents);

    }



    public function index2(Request $request): JsonResponse
    {
        try {
            $schoolId = $request->header('X-School-ID');

            if (!$schoolId) {
                return response()->json([
                    'message' => 'School not found'
                ], 401);
            }

            $parents = DB::table('parents as p')
                ->join('users as pu', 'p.userId', '=', 'pu.id')
                ->leftJoin('student_parents as ps', 'p.parentId', '=', 'ps.parentId')
                ->leftJoin('students as s', function ($join) use ($schoolId) {
                    $join->on('ps.studentId', '=', 's.studentId')
                        ->where('s.schoolId', '=', $schoolId);
                })
                ->leftJoin('users as su', 's.userId', '=', 'su.id')
                ->leftJoin('class_students as sc', 's.studentId', '=', 'sc.studentId')
                ->leftJoin('classes as c', function ($join) use ($schoolId) {
                    $join->on('sc.classId', '=', 'c.classId')
                        ->where('c.schoolId', '=', $schoolId);
                })
                ->where('p.schoolId', $schoolId)
                ->select(
                    'p.parentId',
                    'p.userId',
                    'p.schoolId',

                    'pu.firstName',
                    'pu.lastName',
                    'pu.otherNames',
                    'pu.email',
                    'pu.phoneNumber',
                    // 'pu.gender',

                    DB::raw('COUNT(DISTINCT s.studentId) as childrenCount'),
                    DB::raw('GROUP_CONCAT(DISTINCT c.className ORDER BY c.className SEPARATOR ", ") as classNames'),
                    DB::raw("
                        GROUP_CONCAT(
                            DISTINCT CONCAT(
                                COALESCE(su.firstName, ''),
                                ' ',
                                COALESCE(su.lastName, ''),
                                CASE
                                    WHEN su.otherNames IS NOT NULL AND su.otherNames != ''
                                    THEN CONCAT(' ', su.otherNames)
                                    ELSE ''
                                END
                            )
                            ORDER BY su.firstName, su.lastName
                            SEPARATOR ' | '
                        ) as childrenNames
                    ")
                )
                ->groupBy(
                    'p.parentId',
                    'p.userId',
                    'p.schoolId',
                    'pu.firstName',
                    'pu.lastName',
                    'pu.otherNames',
                    'pu.email',
                    'pu.phoneNumber',
                    // 'pu.gender'
                )
                ->orderBy('pu.lastName')
                ->orderBy('pu.firstName')
                ->get();

            return response()->json($parents);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to load parents list',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }



public function getSchoolParents(Request $request)
    {
        $schoolId = $request->header('X-School-ID');
        $students = StudentParent::with('students', 'user', 'school')->where('schoolId', $schoolId)
        ->get();
        return response()->json($students);

    }

public function getParentChildren(Request $request, $parentId)
{
    $schoolId = $request->header('X-School-ID');

    $children = Student::where('schoolId', $schoolId)
        ->whereHas('parents', function ($q) use ($parentId) {
            $q->where('student_parents.parentId', $parentId);
        })
        ->with(['user', 'classes', 'club', 'house'])
        ->get();

    return response()->json([
        'childrenCount' => $children->count(),
        'children' => $children
    ]);
}


public function storeSchoolParent(Request $request)
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

        $parent = null;
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
            $teacher = StudentParent::create([
                'userId' => $user->id,
                'schoolId' => $schoolId,
                // 'className' => $validated['className'] ?? null,
            ]);
        });

        return response()->json([
            'message' => 'Parent created successfully',
            'parent' => $parent,
            'generated_password' => $plainPassword // ⚠ remove in production
        ], 201);

    } catch (\Exception $e) {

        return response()->json([
            'message' => 'Failed to create parent',
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


