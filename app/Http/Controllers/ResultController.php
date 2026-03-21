<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\AcademicYear;
use App\Models\Term;
use App\Models\Result;
use App\Models\Student;
use App\Models\SchoolClass;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\School;
use App\Models\AffectiveScore;
use App\Models\PsychomotorScore;
use App\Models\StudentAttendance;
use App\Models\ClassTeacher;
use App\Models\ResultComment;
use Illuminate\Support\Facades\Log;

use Illuminate\Http\JsonResponse;
use Tymon\JWTAuth\Claims\Factory as ClaimFactory;
use Tymon\JWTAuth\PayloadFactory;
use Tymon\JWTAuth\Facades\JWTAuth;


class ResultController extends Controller
{

 public function classTeacherComment(Request $request, $resultId)
{
    $schoolId = $request->header('X-School-ID');

    // Find result
    $result = Result::where('resultId', $resultId)
        ->where('schoolId', $schoolId)
        ->first();

    if (!$result) {
        return response()->json([
            'message' => 'Result not found'
        ], 404);
    }

    // Validate
    $validated = $request->validate([
        'classTeacherComment' => 'required|string|max:255',
    ]);

    // Create or Update Principal Comment
    ResultComment::updateOrCreate(
        [
            // 'resultId' => $result->resultId,
            'studentId' => $result->studentId,
            'termId' => $result->termId,
            'academicYearId' => $result->academicYearId,
            'schoolId' => $schoolId,
            'commentType' => 'class_teacher',
        ],
        [
            'classId' => $result->classId,
            'commentedBy' => auth()->id(),
            'comment' => $validated['classTeacherComment'],
        ]
    );

    return response()->json([
        'message' => 'Class teacher comment saved successfully'
    ], 200);
}



 public function principalComment(Request $request, $resultId)
{
    $schoolId = $request->header('X-School-ID');

    // Find result
    $result = Result::where('resultId', $resultId)
        ->where('schoolId', $schoolId)
        ->first();

    if (!$result) {
        return response()->json([
            'message' => 'Result not found'
        ], 404);
    }

    // Validate
    $validated = $request->validate([
        'principalComment' => 'required|string|max:255',
    ]);

    // Create or Update Principal Comment
    ResultComment::updateOrCreate(
        [
            // 'resultId' => $result->resultId,
            'studentId' => $result->studentId,
            'termId' => $result->termId,
            'academicYearId' => $result->academicYearId,
            'schoolId' => $schoolId,
            'commentType' => 'principal',
        ],
        [
            'classId' => $result->classId,
            'commentedBy' => auth()->id(),
            'comment' => $validated['principalComment'],
        ]
    );

    return response()->json([
        'message' => 'Principal comment saved successfully'
    ], 200);
}



private function computeAndStoreResult(
    int $studentId,
    int $classId,
    int $subjectId,
    int $schoolId
) {
    $session = AcademicYear::where('schoolId', $schoolId)
        ->where('isActive', true)
        ->firstOrFail();

    $term = Term::where('schoolId', $schoolId)
        ->where('isActive', true)
        ->firstOrFail();

    // 🔥 Get all assessment scores for this subject
    $total = AssessmentScore::where('studentId', $studentId)
        ->where('subjectId', $subjectId)
        ->where('termId', $term->termId)
        ->sum('score');

    // 🔥 Apply grading
    $gradeData = $this->calculateGrade(
        $total,
        $schoolId,
        $session->academicYearId
    );

    $grade = $gradeData->grade ?? null;
    $remark = $gradeData->remark ?? null;

    // 🔥 Store in results table
    Result::updateOrCreate(
        [
            'studentId' => $studentId,
            'subjectId' => $subjectId,
            'termId' => $term->termId,
        ],
        [
            'classId' => $classId,
            'schoolId' => $schoolId,
            'academicYearId' => $session->academicYearId,
            'totalScore' => $total,
            'grade' => $grade,
            'remark' => $remark,
        ]
    );
}



public function getStudentReportCard(Request $request, int $studentId): JsonResponse
{
    $token = $request->cookie('parent_token');
    if (!$token) {
        return response()->json(['message' => 'Token missing'], 401);
    }

    JWTAuth::setToken($token);
    $payload = JWTAuth::getPayload();

    $parentId = $payload->get('parent_id');
    $schoolId = $payload->get('school_id');

    \Log::info('Decoded JWT Payload', ['payload' => $payload->toArray()]);

    $school = School::where('schoolId', $schoolId)->firstOrFail();

    $session = AcademicYear::where('schoolId', $schoolId)
        ->where('isActive', true)
        ->firstOrFail();

    $term = Term::where('schoolId', $schoolId)
        ->where('isActive', true)
        ->firstOrFail();

    $student = Student::with(['classes', 'user', 'house', 'club'])
        ->where('schoolId', $schoolId)
        ->where('studentId', $studentId)
        ->whereHas('parents2', function ($query) use ($parentId) {
            $query->where('parents.parentId', $parentId);
        })
        ->first();

    if (!$student) {
        return response()->json([
            'message' => 'Unauthorized access'
        ], 403);
    }

    $class = $student->classes->first();

    $classPopulation = 0;

if ($class) {
    $classPopulation = Student::where('schoolId', $schoolId)
        ->whereHas('classes', function ($query) use ($class) {
            $query->where('classes.classId', $class->classId);
        })
        ->count();
}

    $results = Result::with(['subject'])
        ->where('schoolId', $schoolId)
        ->where('academicYearId', $session->academicYearId)
        ->where('termId', $term->termId)
        ->whereHas('student', function ($query) use ($parentId, $studentId) {
            $query->where('studentId', $studentId)
                ->whereHas('parents2', function ($q) use ($parentId) {
                    $q->where('parents.parentId', $parentId);
                });
        })
        ->get();

    $classTeacherComment = null;
    $principalComment = null;

    if ($results->isNotEmpty()) {
        $classTeacherComment = $results->first()->classTeacherComment;
        $principalComment = $results->first()->principalComment;
    }

    // School-wide assessments only, as requested
    $assessments = Assessment::where('schoolId', $schoolId)
        ->orderBy('assessmentId')
        ->get();

    $subjects = [];

    foreach ($results as $result) {
        $scores = AssessmentScore::where('schoolId', $schoolId)
            ->where('studentId', $studentId)
            ->where('subjectId', $result->subjectId)
            ->where('academicYearId', $session->academicYearId)
            ->where('termId', $term->termId)
            ->get()
            ->keyBy('assessmentId');

        $assessmentBreakdown = [];
        $assessmentTotalWeight = 0;

        foreach ($assessments as $assessment) {
            $weight = (float) ($assessment->percentageWeight ?? $assessment->weight ?? 0);
            $assessmentTotalWeight += $weight;

            $assessmentBreakdown[] = [
                'assessmentId' => (int) $assessment->assessmentId,
                'name' => (string) ($assessment->assessmentName ?? ''),
                'score' => isset($scores[$assessment->assessmentId])
                    ? (float) $scores[$assessment->assessmentId]->score
                    : 0,
                'weight' => $weight,
            ];
        }

        $rankings = Result::where('schoolId', $schoolId)
            ->where('academicYearId', $session->academicYearId)
            ->where('termId', $term->termId)
            ->where('subjectId', $result->subjectId)
            ->where('classId', optional($class)->classId)
            ->orderByDesc('totalScore')
            ->pluck('studentId')
            ->values();

        $positionIndex = $rankings->search($studentId);
        $position = $positionIndex !== false ? $this->ordinal($positionIndex + 1) : null;

        $classSubjectResults = Result::where('schoolId', $schoolId)
            ->where('academicYearId', $session->academicYearId)
            ->where('termId', $term->termId)
            ->where('subjectId', $result->subjectId)
            ->where('classId', optional($class)->classId);

        $classMinScore = (float) ($classSubjectResults->min('totalScore') ?? 0);
        $classMaxScore = (float) ($classSubjectResults->max('totalScore') ?? 0);
        $classAverageScore = round((float) ($classSubjectResults->avg('totalScore') ?? 0), 2);

        $subjects[] = [
            'subjectName' => $result->subject->subjectName ?? null,
            'assessments' => $assessmentBreakdown,
            'assessmentTotalWeight' => $assessmentTotalWeight > 0 ? $assessmentTotalWeight : 100,
            'total' => (float) ($result->totalScore ?? 0),
            'grade' => $result->grade ?? '',
            'position' => $position,
            'remark' => strtoupper($result->remark ?? ''),
            'classMinScore' => $classMinScore,
            'classMaxScore' => $classMaxScore,
            'classAverageScore' => $classAverageScore,
        ];
    }

    \Log::info('Report subjects payload', ['subjects' => $subjects]);

    $affective = AffectiveScore::with('domain')
        ->where('schoolId', $schoolId)
        ->where('studentId', $studentId)
        ->where('academicYearId', $session->academicYearId)
        ->where('termId', $term->termId)
        ->get()
        ->map(function ($item) {
            return [
                'name' => $item->domain->domainName ?? '',
                'rating' => $item->score
            ];
        });

    $psychomotor = PsychomotorScore::with('domain')
        ->where('schoolId', $schoolId)
        ->where('studentId', $studentId)
        ->where('academicYearId', $session->academicYearId)
        ->where('termId', $term->termId)
        ->get()
        ->map(function ($item) {
            return [
                'name' => $item->domain->domainName ?? '',
                'rating' => $item->score
            ];
        });

    $daysOpened = StudentAttendance::where('schoolId', $schoolId)
        ->where('academicYearId', $session->academicYearId)
        ->where('termId', $term->termId)
        ->distinct('attendanceDate')
        ->count('attendanceDate');

    $daysPresent = StudentAttendance::where('schoolId', $schoolId)
        ->where('academicYearId', $session->academicYearId)
        ->where('termId', $term->termId)
        ->where('studentId', $studentId)
        ->where('status', 'present')
        ->count();

    $attendancePercentage = $daysOpened > 0
        ? round(($daysPresent / $daysOpened) * 100, 2)
        : 0;

    $classTeacher = null;
    if ($class) {
        $classTeacher = ClassTeacher::with('teacher.user')
            ->where('schoolId', $schoolId)
            ->where('classId', $class->classId)
            ->first();
    }

    $classTeacherData = null;
    if ($classTeacher && $classTeacher->teacher && $classTeacher->teacher->user) {
        $teacherUser = $classTeacher->teacher->user;
        $teacher = $classTeacher->teacher;

        $classTeacherData = [
            'fullName' => strtoupper(
                trim(
                    ($teacherUser->lastName ?? '') . ', ' .
                    ($teacherUser->firstName ?? '') . ' ' .
                    ($teacherUser->otherNames ?? '')
                )
            ),
            'signature' => $teacher->signature ?? null,
        ];
    }

    return response()->json([
        'school' => [
            'schoolName' => $school->schoolName,
            'email' => $school->schoolEmail,
            'phoneNumber' => $school->schoolPhone,
            'schoolAddress' => $school->schoolAddress,
            'schoolLogo' => $school->schoolLogo,
            'authorizedSignature' => $school->authorizedSignature,
        ],

        'academicYear' => $session->academicYearName,
        'term' => $term->termName,

        'fullName' => strtoupper(
            trim(
                ($student->user->lastName ?? '') . ', ' .
                ($student->user->firstName ?? '') . ' ' .
                ($student->user->otherNames ?? '')
            )
        ),
        'className' => optional($class)->className ?? '',
        'classPopulation' => $classPopulation,
        'gender' => strtoupper($student->gender ?? ''),
        'admissionNo' => $student->admissionNo ?? '',
        'dob' => $student->dateOfBirth
            ? Carbon::parse($student->dateOfBirth)->format('D, d-M-Y')
            : '',
        'age' => $student->dateOfBirth
            ? Carbon::parse($student->dateOfBirth)->age
            : null,
        'house' => strtoupper($student->house->houseName ?? ''),
        'club' => strtoupper($student->club->clubName ?? ''),
        'passportUrl' => $student->passport ?? null,

        'subjects' => $subjects,

        'comments' => [
            'classTeacherComment' => $classTeacherComment,
            'principalComment' => $principalComment,
        ],

        'domains' => [
            'affective' => $affective,
            'psychomotor' => $psychomotor,
        ],

        'attendance' => [
            'timesSchoolOpened' => $daysOpened,
            'timesPresent' => $daysPresent,
            'percentage' => $attendancePercentage,
        ],

        'class_teacher' => $classTeacherData,
    ]);
}


public function getStudentResult(Request $request, int $studentId): JsonResponse
{
    $schoolId = $request->header('X-School-ID');

    $school = School::where('schoolId', $schoolId)->firstOrFail();

    $session = AcademicYear::where('schoolId', $schoolId)
        ->where('isActive', true)
        ->firstOrFail();

    $term = Term::where('schoolId', $schoolId)
        ->where('isActive', true)
        ->firstOrFail();

    $student = Student::with('classes', 'user')
        ->where('schoolId', $schoolId)
        ->findOrFail($studentId);
        

    // 🔥 Get subject results (final totals already computed)
    // $results = Result::with('class_subject.subject')
    $results = Result::with('subject')
        ->where('schoolId', $schoolId)
        ->where('studentId', $studentId)
        ->where('academicYearId', $session->academicYearId)
        ->where('termId', $term->termId)
        ->get();

        $classTeacherComment = null;
$principalComment = null;

if ($results->isNotEmpty()) {
    $classTeacherComment = $results->first()->classTeacherComment;
    $principalComment = $results->first()->principalComment;
}

    $subjects = [];

    foreach ($results as $result) {

        // 🔥 Get all assessments for this subject
        $assessments = Assessment::where('schoolId', $schoolId)
            // ->where('classId', $student->classId)
            // ->where('subjectId', $result->subjectId)
            // ->where('academicYearId', $session->academicYearId)
            // ->where('termId', $term->termId)
            ->get();

        // 🔥 Get student's assessment scores
        $scores = AssessmentScore::where('schoolId', $schoolId)
            ->where('studentId', $studentId)
            ->where('subjectId', $result->subjectId)
            ->where('academicYearId', $session->academicYearId)
            ->where('termId', $term->termId)
            ->get()
            ->keyBy('assessmentId');

        $assessmentBreakdown = [];

        foreach ($assessments as $assessment) {
            $assessmentBreakdown[$assessment->assessmentName] =
                $scores[$assessment->assessmentId]->score ?? 0;
        }

        // 🔥 Calculate Subject Position safely
        $rankings = Result::where('schoolId', $schoolId)
            ->where('classId', $student->classId)
            ->where('subjectId', $result->subjectId)
            ->where('academicYearId', $session->academicYearId)
            ->where('termId', $term->termId)
            ->orderByDesc('totalScore')
            ->pluck('studentId')
            ->values();

        $positionIndex = $rankings->search($studentId);
        $position = $positionIndex !== false ? $this->ordinal($positionIndex + 1) : null;

        $subjects[] = array_merge([
            // 'subjectName' => $result->class_subject->subject->subjectName ?? null,
            'subjectName' => $result->subject->subjectName ?? null,
            'total' => $result->totalScore ?? 0,
            'grade' => $result->grade ?? '',
            'position' => $position,
            'remark' => strtoupper($result->remark ?? '')
        ], $assessmentBreakdown);
    }

    $affective = AffectiveScore::with('domain')
    ->where('schoolId', $schoolId)
    ->where('studentId', $studentId)
    ->where('academicYearId', $session->academicYearId)
    ->where('termId', $term->termId)
    ->get()
    ->map(function ($item) {
        return [
            'name' => $item->domain->domainName,
            'rating' => $item->score
        ];
    });

    $psychomotor = PsychomotorScore::with('domain')
    ->where('schoolId', $schoolId)
    ->where('studentId', $studentId)
    ->where('academicYearId', $session->academicYearId)
    ->where('termId', $term->termId)
    ->get()
    ->map(function ($item) {
        return [
            'name' => $item->domain->domainName,
            'rating' => $item->score
        ];
    });

    $daysOpened = StudentAttendance::where('schoolId', $schoolId)
    ->where('academicYearId', $session->academicYearId)
    ->where('termId', $term->termId)
    ->distinct('attendanceDate')
    ->count('date');

    $daysPresent = StudentAttendance::where('schoolId', $schoolId)
    ->where('academicYearId', $session->academicYearId)
    ->where('termId', $term->termId)
    ->where('studentId', $studentId)
    ->where('status', 'present')
    ->count();

    $attendancePercentage = $daysOpened > 0
    ? round(($daysPresent / $daysOpened) * 100, 2)
    : 0;

$class = $student->classes->first();

$classTeacher = null;

if ($class) {
    $classTeacher = ClassTeacher::with('teacher.user')
        ->where('schoolId', $schoolId)
        ->where('classId', $class->classId)
        ->first();
        // Log::info('teacher'. $classTeacher);
}

$classTeacherData = null;

if ($classTeacher && $classTeacher->teacher && $classTeacher->teacher->user) {

    $teacherUser = $classTeacher->teacher->user;
    $teacher = $classTeacher->teacher;
    $classTeacherData = [
        'fullName' => strtoupper(
            $teacherUser->lastName . ', ' .
            $teacherUser->firstName . ' ' .
            $teacherUser->otherNames
        ),
        'signature' => $teacher->signature ?? null,
    ];
}

return response()->json([

    // 🔥 SCHOOL DETAILS
    'school' => [
        'schoolName'   => $school->schoolName,
        'email'        => $school->schoolEmail,
        'phoneNumber'  => $school->schoolPhone,
        'schoolAddress'=> $school->schoolAddress,
        'schoolLogo'=> $school->schoolLogo,
        'authorizedSignature'=> $school->authorizedSignature,
    ],

    // 🔥 ACTIVE SESSION + TERM
    'academicYear' => $session->academicYearName,
    'term'         => $term->termName,

    // 🔥 STUDENT DETAILS
    'fullName' => strtoupper(
        $student->user->lastName . ', ' .
        $student->user->firstName . ' ' .
        $student->user->otherNames
    ),
    'className' => optional($class)->className ?? '',
    'gender' => strtoupper($student->gender),
    'admissionNo' => $student->admissionNo ?? '',
    'dob' => Carbon::parse($student->dateOfBirth)->format('D, d-M-Y'),
    'age' => Carbon::parse($student->dateOfBirth)->age,
    'house' => strtoupper($student->house->houseName ?? ''),
    'club' => strtoupper($student->club->clubName ?? ''),
    'passportUrl' => $student->passport ?? null,

    // 🔥 SUBJECT RESULTS
    'subjects' => $subjects,

    'comments' => [
        'classTeacherComment' => $classTeacherComment,
        'principalComment' => $principalComment,
    ],

    'domains' => [
        'affective' => $affective,
        'psychomotor' => $psychomotor
    ],

    'attendance' => [
    'timesSchoolOpened' => $daysOpened,
    'timesPresent' => $daysPresent,
    'percentage' => $attendancePercentage
],
'class_teacher' => $classTeacherData,
]);
}


   public function classResultSummary(Request $request, $classId)
{
    $schoolId = $request->header('X-School-ID');

    $session = AcademicYear::where('schoolId', $schoolId)
        ->where('isActive', true)
        ->firstOrFail();

    $term = Term::where('schoolId', $schoolId)
        ->where('isActive', true)
        ->firstOrFail();

    // ✅ eager load student user to avoid N+1
    $class = SchoolClass::with(['students.user', 'subjects'])->findOrFail($classId);

    $results = Result::where('classId', $classId)
        ->where('termId', $term->termId)
        ->where('academicYearId', $session->academicYearId)
        ->where('schoolId', $schoolId)
        ->get();

    if ($results->isEmpty()) {
        return response()->json(['message' => 'No results found'], 404);
    }

    $comments = ResultComment::where('classId', $classId)
        ->where('termId', $term->termId)
        ->where('academicYearId', $session->academicYearId)
        ->where('schoolId', $schoolId)
        ->get()
        ->groupBy('studentId');

        \Log::info('Comments grouped by studentId', ['comments' => $comments->toArray()]);

    $subjectAverages = $results
        ->groupBy('subjectId')
        ->map(function ($records) use ($class) {
            $avg = $records->avg('totalScore');
            $subjectId = $records->first()->subjectId;

            $subject = $class->subjects->firstWhere('subjectId', $subjectId);

            return [
                'subjectId' => $subjectId,
                'subjectName' => $subject?->subjectName ?? 'Unknown Subject',
                'avg' => round($avg, 2),
            ];
        })
        ->values();

    $bestSubject = $subjectAverages->sortByDesc('avg')->first();
    $worstSubject = $subjectAverages->sortBy('avg')->first();

    $students = [];

    foreach ($class->students as $student) {

        $studentResults = $results->where('studentId', $student->studentId);

        if ($studentResults->isEmpty()) {
            continue;
        }

        $scores = [];
        $totalScore = 0;

        foreach ($studentResults as $res) {
            $totalScore += $res->totalScore;

            $scores[$res->subjectId] = [
                'total' => $res->totalScore,
                'grade' => $res->grade,
                'remark' => $res->remark,
            ];
        }

        $average = $totalScore / $studentResults->count();

        $studentComments = $comments->get($student->studentId);

        $classTeacherComment = optional(
            $studentComments?->firstWhere('commentType', 'class_teacher')
        )->comment;

        $principalComment = optional(
            $studentComments?->firstWhere('commentType', 'principal')
        )->comment;

        $students[] = [
            'studentId' => $student->studentId,
            'firstName' => $student->user->firstName,
            'lastName' => $student->user->lastName,
            'otherNames' => $student->user->otherNames,
            'admissionNumber' => $student->admissionNumber,
            'scores' => $scores,
            'total' => $totalScore,
            'average' => round($average, 2),
            'teacherComment' => $classTeacherComment,
            'principalComment' => $principalComment,
        ];
    }

    usort($students, fn ($a, $b) => $b['total'] <=> $a['total']);

    foreach ($students as $index => &$student) {
        $student['position'] = $index + 1;
    }

    $classAverage = round(collect($students)->avg('average'), 2);

    return response()->json([
        'className' => $class->className,
        'term' => $term->termName,
        'session' => $session->academicYearName,
        'classAverage' => $classAverage,
        'bestSubject' => [
            'name' => $bestSubject['subjectName'] ?? null,
            'avg' => $bestSubject['avg'] ?? null,
        ],
        'worstSubject' => [
            'name' => $worstSubject['subjectName'] ?? null,
            'avg' => $worstSubject['avg'] ?? null,
        ],
        'subjects' => $class->subjects->map(fn ($subject) => [
            'subjectId' => $subject->subjectId,
            'subjectName' => $subject->subjectName,
        ]),
        'students' => $students,
    ]);
}


    /**
     * Convert number to ordinal (1st, 2nd, 3rd, etc)
     */
    private function ordinal($number)
    {
        if (!in_array(($number % 100), [11,12,13])) {
            switch ($number % 10) {
                case 1: return $number.'st';
                case 2: return $number.'nd';
                case 3: return $number.'rd';
            }
        }
        return $number.'th';
    }


}