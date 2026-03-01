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
use Illuminate\Support\Facades\Log;

use Illuminate\Http\JsonResponse;

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

        // Update
        $result->update([
            'classTeacherComment' => $validated['classTeacherComment'],
        ]);

        return response()->json([
            'message' => 'Class teacher comment updated successfully',
            'data' => $result
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

        // Update
        $result->update([
            'principalComment' => $validated['principalComment'],
        ]);

        return response()->json([
            'message' => 'Principal comment updated successfully',
            'data' => $result
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
        //  $school = School::where('schoolId', $schoolId)->firstOrFail();

    $session = AcademicYear::where('schoolId', $schoolId)
        ->where('isActive', true)
        ->firstOrFail();

    $term = Term::where('schoolId', $schoolId)
        ->where('isActive', true)
        ->firstOrFail();

        $class = SchoolClass::with(['students', 'subjects'])->findOrFail($classId);

        $results = Result::where('classId', $classId)
            ->where('termId', $term->termId)
            ->where('academicYearId', $session->academicYearId)
            ->where('schoolId', $schoolId)
            ->get();

        if ($results->isEmpty()) {
            return response()->json(['message' => 'No results found'], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | SUBJECT AVERAGES
        |--------------------------------------------------------------------------
        */
        $subjectAverages = $results
            ->groupBy('subjectId')
            ->map(function ($records) use ($class) {

                $avg = $records->avg('totalScore');

                $subject = $class->subjects
                    ->firstWhere('subjectId', $records->first()->subjectId);

                return [
                    'subjectId' => $subject->subjectId,
                    'subjectName' => $subject->subjectName,
                    'avg' => round($avg, 2),
                ];
            })
            ->values();

        $bestSubject = $subjectAverages->sortByDesc('avg')->first();
        $worstSubject = $subjectAverages->sortBy('avg')->first();

        /*
|--------------------------------------------------------------------------
| STUDENT AGGREGATION
|--------------------------------------------------------------------------
*/
$students = [];

foreach ($class->students as $student) {

    $studentResults = $results->where('studentId', $student->studentId);

    if ($studentResults->isEmpty()) {
        continue;
    }

    $scores = [];
    $totalScore = 0;

    // ✅ Extract comments once
    $firstResult = $studentResults->first();

    $classTeacherComment = $firstResult->classTeacherComment ?? null;
    $principalComment = $firstResult->principalComment ?? null;

    foreach ($studentResults as $res) {

        $totalScore += $res->totalScore;

        $scores[$res->subjectId] = [
            'total' => $res->totalScore,
            'grade' => $res->grade,
            'remark' => $res->remark,
        ];
    }

    $average = $totalScore / $studentResults->count();

    $students[] = [
        'studentId' => $student->studentId,
        'firstName' => $student->user->firstName,
        'lastName' => $student->user->lastName,
        'otherNames' => $student->user->otherNames,
        'admissionNumber' => $student->admissionNumber,
        'scores' => $scores,
        'total' => $totalScore,
        'average' => round($average, 2),

        // ✅ ADD THESE
        'teacherComment' => $classTeacherComment,
        'principalComment' => $principalComment,
    ];
}

        /*
        |--------------------------------------------------------------------------
        | RANKING (POSITION)
        |--------------------------------------------------------------------------
        */
        usort($students, fn ($a, $b) => $b['total'] <=> $a['total']);

        foreach ($students as $index => &$student) {
            $student['position'] = $index + 1;
        }

        /*
        |--------------------------------------------------------------------------
        | CLASS AVERAGE
        |--------------------------------------------------------------------------
        */
        $classAverage = round(
            collect($students)->avg('average'),
            2
        );

        return response()->json([
            'className' => $class->className,
            'term' => $term->termName,
            'session' => $session->academicYearName,
            'classAverage' => $classAverage,
            'bestSubject' => [
                'name' => $bestSubject['subjectName'],
                'avg' => $bestSubject['avg'],
            ],
            'worstSubject' => [
                'name' => $worstSubject['subjectName'],
                'avg' => $worstSubject['avg'],
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