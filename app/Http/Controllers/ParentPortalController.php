<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\ParentAccess;
use App\Models\School;
use App\Models\GradingSystem;
use App\Models\StudentAttendance;


use Illuminate\Support\Facades\Cookie;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\User;
use App\Models\AcademicYear;
use App\Models\Term;
use App\Models\Result;

use Tymon\JWTAuth\Claims\Factory as ClaimFactory;
use Tymon\JWTAuth\PayloadFactory;

class ParentPortalController extends Controller
{
    // public function verify(Request $request)
    // {
    //     $request->validate([
    //         'phoneNumber' => 'required|string',
    //         'pin' => 'required|string',
    //         'schoolId' => 'required|integer',
    //         'academicYearId' => 'required|integer',
    //         'termId' => 'required|integer',
    //     ]);

    //     $record = ParentAccess::where([
    //         'schoolId' => $request->schoolId,
    //         'phoneNumber' => $request->phoneNumber,
    //         'academicYearId' => $request->academicYearId,
    //         'termId' => $request->termId,
    //         'isActive' => true
    //     ])->first();

    //     if (!$record) {
    //         return response()->json(['message' => 'Access not found'], 404);
    //     }

    //     if ($record->lockedUntil && now()->lessThan($record->lockedUntil)) {
    //         return response()->json(['message' => 'Too many attempts. Try later.'], 429);
    //     }

    //     if (!Hash::check($request->pin, $record->pinHash)) {

    //         $record->increment('failedAttempts');

    //         if ($record->failedAttempts >= 5) {
    //             $record->update([
    //                 'lockedUntil' => now()->addMinutes(10)
    //             ]);
    //         }

    //         return response()->json(['message' => 'Invalid PIN'], 401);
    //     }

    //     // Reset attempts
    //     $record->update([
    //         'failedAttempts' => 0,
    //         'lockedUntil' => null
    //     ]);

    //     return response()->json([
    //         'message' => 'Access granted'
    //     ]);
    // }


public function verify(Request $request)
{
    // Validate inputs
    $request->validate([
        'phoneNumber' => 'required|string',
        'pin'         => 'required|string',
        'schoolId'    => 'required|integer',
    ]);

    $phone = trim($request->phoneNumber);
    $pin   = strtoupper(trim($request->pin));
    $schoolId = $request->schoolId;

    $user = User::where('phoneNumber', $phone)->first();
    if (!$user) {
        return response()->json(['message' => 'User not found'], 404);
    }

    // Lookup ParentAccess
    $pinLookup = hash('sha256', $pin);
    $access = ParentAccess::where('pinLookup', $pinLookup)->first();
    if (!$access || $access->schoolId != $schoolId || !$access->isActive) {
        return response()->json(['message' => 'Invalid PIN'], 401);
    }

    // Fetch active academic year & term
    $session = AcademicYear::where('schoolId', $schoolId)->where('isActive', true)->first();
    $term    = Term::where('schoolId', $schoolId)->where('isActive', true)->first();
    
    if (!$session || !$term) {
        return response()->json(['message' => 'No active session/term found'], 404);
    }

   

    // Verify bcrypt PIN
    if (!Hash::check($pin, $access->pinHash)) {
        $access->increment('failedAttempts');
        if ($access->failedAttempts >= 5) {
            $access->update(['lockedUntil' => now()->addMinutes(10)]);
        }
        return response()->json(['message' => 'Invalid PIN'], 401);
    }

    // Reset failed attempts
    $access->update([
        'failedAttempts' => 0,
        'lockedUntil'    => null,
        'phoneNumber'    => $phone,
        'userId'         => $user->id,
        'parentId' => $user->parent->parentId

    ]);

    // Generate JWT
$payload = JWTAuth::factory()->customClaims([
    'sub'              => $access->parentAccessId,
    'role'             => 'parent_access',
    'school_id'        => $schoolId,
    'academic_year_id' => $session->academicYearId,
    'term_id'          => $term->termId,
    'parent_access_id' => $access->parentAccessId,
    'parent_id' => $user->parent->parentId,
    ])->setTTL(120) // minutes
  ->make();

$token = JWTAuth::encode($payload)->get();

    // Store in secure cookie
    $secure = app()->environment('production');
    $cookie = cookie(
        'parent_token',
        $token,
        120, // minutes
        '/',
        null,
        $secure,
        true,
        false,
        $secure ? 'None' : 'Lax'
    );

    return response()->json(['message' => 'Access granted'])->withCookie($cookie);
}




public function logout(Request $request)
{
    $cookie = cookie()->forget('parent_token');
    return response()->json(['message' => 'Logged out'])->withCookie($cookie);
}


// public function resultChecker(Request $request)
// {
//     try {
//         // Get token from cookie
//         $token = $request->cookie('parent_token');
        
//         if (!$token) {
//             return response()->json(['message' => 'Token missing'], 401);
//         }
        
//         // Set token manually and get payload
//         JWTAuth::setToken($token);
//         $payload = JWTAuth::getPayload();
        
//         $schoolId = $payload->get('school_id');
        
//         $school = School::where('schoolId', $schoolId)->first();
        
//         if (!$school) {
//             return response()->json(['message' => 'School not found'], 404);
//         }
        
//         return response()->json([
//             'message' => 'School retrieved successfully',
//             'schoolName' => $school->schoolName,
//             'schoolLogo' => $school->schoolLogo
//         ]);
        
//     } catch (JWTException $e) {
//         return response()->json([
//             'message' => 'Token invalid or expired',
//             'error' => $e->getMessage()
//         ], 401);
//     }
// }


public function resultChecker(Request $request)
{
    try {
        // Get token from cookie and authenticate
        $token = $request->cookie('parent_token');
        if (!$token) {
            return response()->json(['message' => 'Token missing'], 401);
        }

        JWTAuth::setToken($token);
        $payload = JWTAuth::getPayload();

        $schoolId = $payload->get('school_id');
        $parentAccessId = $payload->get('parent_access_id');

        // Fetch grading system for this school
        $grades = GradingSystem::where('schoolId', $schoolId)
            ->orderByDesc('minScore')
            ->get();

        // Fetch school info
        $school = School::where('schoolId', $schoolId)->first();
        if (!$school) {
            return response()->json(['message' => 'School not found'], 404);
        }

        // Get parent access and linked user
        $parentAccess = ParentAccess::with('parent')->find($parentAccessId);
        if (!$parentAccess) {
            return response()->json(['message' => 'Parent access not found'], 404);
        }

        $user = User::find($parentAccess->parent->userId);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Get all children
        $children = $user->children()->get();
        if ($children->isEmpty()) {
            return response()->json(['message' => 'No child found for this parent'], 404);
        }

        $allChildrenResults = [];

        foreach ($children as $child) {
    $studentId = $child->studentId;
    $studentAdmissionNumber = $child->admissionNumber;
    $schoolAssignedAdmissionNumber = $child->schoolAssignedAdmissionNumber;

    // Fetch the student name
    $studentUser = User::find($child->userId);
    $studentName = $studentUser
        ? ($studentUser->firstName . ' ' . $studentUser->lastName)
        : 'N/A';

    $childResults = [];

    // --- Results ---
    $termsWithResults = Result::where([
        'studentId' => $studentId,
        'schoolId' => $schoolId
    ])
    ->with(['term.academic_year', 'subject'])
    ->get()
    ->groupBy('termId');

    foreach ($termsWithResults as $termId => $termResults) {
        $term = $termResults->first()->term;
        $academicYear = $term->academic_year;
        $classId = $termResults->first()->classId;

        $totalScore = 0;
        $subjectCount = 0;
        $subjects = [];

        foreach ($termResults as $termResult) {
            $total = $termResult->totalScore ?? 0;

            $subjects[] = [
                'subject' => $termResult->subject->subjectName ?? null,
                'total'   => $total,
                'grade'   => $this->calculateGradeFromCollection($total, $grades),
            ];

            $totalScore += $total;
            $subjectCount++;
        }

        $overallAverage = $subjectCount > 0
            ? round($totalScore / $subjectCount, 1)
            : 0;

        $classPosition = $this->getClassPosition(
            $studentId,
            $termId,
            $overallAverage,
            $classId,
            $schoolId
        );

        $isPublished = $termResults->first()->isPublished ?? true;
        $publishedAt = $termResults->first()->publishedAt ?? $termResults->first()->updated_at;

        // --- Attendance per term ---
$termIdValue = $term->termId;
$academicYearId = $term->academicYearId;

$attendanceData = StudentAttendance::where([
    'studentId' => $studentId,
    'termId' => $termIdValue,
    'academicYearId' => $academicYearId,
    'schoolId' => $schoolId
])->get();

$totalDays = $attendanceData->count();
$presentDays = $attendanceData->where('status', 'present')->count();
$lateDays = $attendanceData->where('status', 'late')->count();
$attendancePercentage = $totalDays > 0 ? round(($presentDays / $totalDays) * 100) : 0;


      $childResults[] = [
    'term'           => $term->termName,
    'academicYear'   => $academicYear->academicYearName ?? $academicYear->startYear . '/' . $academicYear->endYear,
    'overallAverage' => $overallAverage,
    'overallGrade'   => $this->calculateGradeFromCollection($overallAverage, $grades),
    'classPosition'  => $classPosition,
    'isPublished'    => $isPublished,
    'publishedAt'    => $publishedAt ? $publishedAt->format('Y-m-d') : null,
    'subjects'       => $subjects,
    'attendance'     => [
        'percentage'  => $attendancePercentage,
        'presentDays' => $presentDays,
        'totalDays'   => $totalDays,
        'lateDays'    => $lateDays
    ]
];
    }



    $allChildrenResults[] = [
        'studentId'    => $studentId,
        'studentName'  => $studentName,
        'admissionNumber' => $studentAdmissionNumber,
        'schoolAdmissionNumber' => $child->schoolAssignedAdmissionNumber,
        'results'      => $childResults,
        'attendance'   => [
            'percentage'  => $attendancePercentage,
            'presentDays' => $presentDays,
            'totalDays'   => $totalDays,
            'lateDays'    => $lateDays
        ]
    ];
}
        return response()->json([
            'message' => 'Results retrieved successfully',
            'currentTerm' => $term ? [
        'termId' => $term->termId,
        'termName' => $term->termName,
        'academicYear' => $term->academic_year->academicYearName ?? ($term->academic_year->startYear . '/' . $term->academic_year->endYear)
    ] : null,
            'children' => $allChildrenResults,
            'schoolName' => $school->schoolName,
            'schoolLogo' => $school->schoolLogo
        ]);

    } catch (JWTException $e) {
        return response()->json([
            'message' => 'Token invalid or expired',
            'error' => $e->getMessage()
        ], 401);
    }
}

/**
 * Calculate grade based on score
 */
private function calculateGradeFromCollection($score, $grades)
{
    $grade = $grades->first(function ($g) use ($score) {
        return $score >= $g->minScore && $score <= $g->maxScore;
    });

    return $grade ? $grade->grade : null;
}

/**
 * Get subject position (you'll need to implement this based on your database structure)
 */
private function getSubjectPosition($subjectResult, $termId, $studentId)
{
    // This is a placeholder - you'll need to implement actual logic
    // based on how you store positions in your database
    
    if (isset($subjectResult->position)) {
        $position = $subjectResult->position;
        $totalStudents = $subjectResult->totalStudents ?? 45;
        return $position . $this->getOrdinalSuffix($position) . ' / ' . $totalStudents;
    }
    
    // Return a default or null
    return 'N/A';
}

/**
 * Get class position
 */
private function getClassPosition(
    $studentId,
    $termId,
    $studentAverage,
    $classId,
    $schoolId
) {
    $allStudentAverages = Result::where([
        'termId'   => $termId,
        'classId'  => $classId,
        'schoolId' => $schoolId
    ])
    ->selectRaw('studentId, AVG(totalScore) as avgScore')
    ->groupBy('studentId')
    ->orderByDesc('avgScore')
    ->get();

    $position = $allStudentAverages
        ->where('avgScore', '>', $studentAverage)
        ->count() + 1;

    $totalStudents = $allStudentAverages->count();

    return $position . $this->getOrdinalSuffix($position) . ' / ' . $totalStudents;
}

/**
 * Get ordinal suffix for a number (1st, 2nd, 3rd, etc.)
 */
private function getOrdinalSuffix($number)
{
    if (!in_array(($number % 100), [11, 12, 13])) {
        switch ($number % 10) {
            case 1: return 'st';
            case 2: return 'nd';
            case 3: return 'rd';
        }
    }
    return 'th';
}

}