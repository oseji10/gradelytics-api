<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\AcademicYear;
use App\Models\Term;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;


class AssessmentController extends Controller
{

    public function index(Request $request){
        $schoolId = $request->header('X-School-ID');
        $assessments = Assessment::where('schoolId', $schoolId)->get();
        return response()->json($assessments);
    }
    /*
    |--------------------------------------------------------------------------
    | Create Assessment Structure
    | Example: 1st CA, 2nd CA, Exam
    |--------------------------------------------------------------------------
    */
    public function createAssessment(Request $request): JsonResponse
    {
        $schoolId = $request->header('X-School-ID');

        $validated = $request->validate([
            // 'termId'    => 'required|integer',
            // 'sessionId' => 'required|integer',
            // 'classId'   => 'required|integer',
            // 'subjectId' => 'required|integer',
            'assessmentName'      => 'required|string',
            'maxScore'  => 'required|numeric',
            'weight'    => 'required|numeric'
        ]);

        $assessment = Assessment::create([
            'schoolId'  => $schoolId,
            'assessmentName' => $validated['assessmentName'],
            'maxScore'  => $validated['maxScore'],
            'weight'    => $validated['weight']
        ]);

        return response()->json([
            'message' => 'Assessment created successfully',
            'assessment' => $assessment
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Store Scores (Bulk)
    |--------------------------------------------------------------------------
    */
    public function storeScores(Request $request, int $assessmentId): JsonResponse
{
    $schoolId = $request->header('X-School-ID');

    $validated = $request->validate([
        'classId' => 'required|integer',
        'subjectId' => 'required|integer',
        'scores'  => 'required|array',
        'scores.*.studentId' => 'required|integer',
        'scores.*.score' => 'required|numeric|min:0'
    ]);

    $assessment = Assessment::findOrFail($assessmentId);

    $session = AcademicYear::where('schoolId', $schoolId)
        ->where('isActive', true)
        ->firstOrFail();

    $term = Term::where('schoolId', $schoolId)
        ->where('isActive', true)
        ->firstOrFail();

    foreach ($validated['scores'] as $entry) {

        if ($entry['score'] > $assessment->maxScore) {
            return response()->json([
                'message' => "Score cannot exceed max score of {$assessment->maxScore}"
            ], 400);
        }

        // ✅ Use actual score, not undefined variable
        $gradeData = $this->calculateGrade(
            $entry['score'],
            $schoolId,
            $session->academicYearId
        );

        $grade = $gradeData->grade ?? null;
        $remark = $gradeData->remark ?? null;

        AssessmentScore::updateOrCreate(
            [
                'assessmentId' => $assessmentId,
                'studentId'    => $entry['studentId'],
                'termId'       => $term->termId
            ],
            [
                'score'          => $entry['score'],
                'grade'          => $grade,
                'remark'         => $remark,
                'schoolId'       => $schoolId,
                'subjectId'      => $validated['subjectId'],
                'academicYearId' => $session->academicYearId,
                'classId'        => $validated['classId'],
            ]
        );
    }

    return response()->json([
        'message' => 'Scores saved successfully'
    ], 200);
}


    public function update(Request $request, $assessmentId)
    {
        // Find assessment
        $assessment = Assessment::where('assessmentId', $assessmentId)->first();

        if (!$assessment) {
            return response()->json([
                'message' => 'Assessment not found'
            ], 404);
        }

        // Optional: restrict to same school
        // Uncomment if needed
        /*
        if ($assessment->schoolId !== Auth::user()->schoolId) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }
        */

        // Validate
        $validated = $request->validate([
            'assessmentName' => 'required|string|max:255',
            'maxScore' => 'required|numeric|min:1',
            'weight' => 'nullable|numeric|min:0|max:100',
        ]);

        // Update
        $assessment->update([
            'assessmentName' => $validated['assessmentName'],
            'maxScore' => $validated['maxScore'],
            'weight' => $validated['weight'] ?? null,
        ]);

        return response()->json([
            'message' => 'Assessment updated successfully',
            'data' => $assessment
        ], 200);
    }


public function getSubjectAssessmentScores(Request $request): JsonResponse
{
    $schoolId = $request->header('X-School-ID');

    $validated = $request->validate([
        'subjectId' => 'required|integer',
        'classId'   => 'required|integer',
    ]);

    // Get active session and term
    $session = AcademicYear::where('schoolId', $schoolId)->where('isActive', true)->firstOrFail();
    $term = Term::where('schoolId', $schoolId)->where('isActive', true)->firstOrFail();

    // Get all students in the class via pivot
    $studentsInClass = Student::with('user')
        ->whereHas('classes', function ($q) use ($validated, $schoolId) {
            $q->where('class_students.classId', $validated['classId'])
              ->where('class_students.schoolId', $schoolId);
        })
        ->get();

    $studentIds = $studentsInClass->pluck('studentId')->toArray();

    // Get all assessment scores for this subject, school, term, session
    $scoresQuery = AssessmentScore::with('assessment')
        ->where('schoolId', $schoolId)
        ->where('termId', $term->termId)
        ->where('academicYearId', $session->academicYearId)
        ->where('subjectId', $validated['subjectId']); // subjectId only in scores

    if (!empty($studentIds)) {
        $scoresQuery->whereIn('studentId', $studentIds);
    }

    $allScores = $scoresQuery->get();

    // Get all unique assessments dynamically from scores
    $assessments = $allScores->pluck('assessment')->unique('assessmentId')->values();

    // Build student array
    $result = [];

    foreach ($studentsInClass as $student) {
        $name = trim(($student->user->firstName ?? '') . ' ' . ($student->user->lastName ?? ''));

        $studentScores = [];

        foreach ($assessments as $assessment) {
            $score = $allScores->firstWhere(function ($s) use ($student, $assessment) {
                return $s->studentId == $student->studentId && $s->assessmentId == $assessment->assessmentId;
            });

            $studentScores[] = [
                'assessmentId' => $assessment->assessmentId,
                'name'         => $assessment->assessmentName,
                'maxScore'     => $assessment->maxScore,
                'score'        => $score?->score ?? 0
            ];
        }

        $total = array_sum(array_column($studentScores, 'score'));

        $result[] = [
            'studentId'   => $student->studentId,
            'studentName' => $name,
            'assessments' => $studentScores,
            'total'       => $total
        ];
    }

    return response()->json([
        'subjectId' => $validated['subjectId'],
        'classId'   => $validated['classId'],
        'term'      => $term->termName,
        'session'   => $session->academicYearName,
        'students'  => $result
    ]);
}

}