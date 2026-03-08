<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CbtExam;
use App\Models\CbtExamAttempt;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CbtMonitorController extends Controller
{
    protected function resolveSchoolId(Request $request): int
    {
        $user = auth()->user();

        if (!$user) {
            abort(response()->json([
                'message' => 'Unauthenticated',
            ], 401));
        }

        $schoolId = $user->schoolId ?? $user->school?->schoolId ?? null;

        if (!$schoolId) {
            abort(response()->json([
                'message' => 'School context not found',
            ], 422));
        }

        return (int) $schoolId;
    }

    public function show(Request $request, int $examId): JsonResponse
{
    $schoolId = $request->header('X-School-ID');
    $now = Carbon::now();

    $exam = CbtExam::with([
        'subject:subjectId,subjectName',
    ])
        ->where('schoolId', $schoolId)
        ->where('examId', $examId)
        ->first();

    if (!$exam) {
        return response()->json([
            'message' => 'Exam not found',
        ], 404);
    }

    $eligibleCandidates = DB::table('class_students')
        ->where('classId', $exam->classId)
        ->count();

    $attempts = CbtExamAttempt::query()
        ->where('cbt_exam_attempts.examId', $exam->examId)
        ->where('cbt_exam_attempts.schoolId', $schoolId)
        ->leftJoin('students', 'cbt_exam_attempts.studentId', '=', 'students.studentId')
        ->leftJoin('users', 'students.userId', '=', 'users.id')
        ->leftJoin('cbt_exam_answers', 'cbt_exam_attempts.attemptId', '=', 'cbt_exam_answers.attemptId')
        ->select([
            'cbt_exam_attempts.attemptId',
            'cbt_exam_attempts.studentId',
            'cbt_exam_attempts.startedAt',
            'cbt_exam_attempts.submittedAt',
            'cbt_exam_attempts.status',
            'cbt_exam_attempts.score',
            'cbt_exam_attempts.totalQuestions',
            'users.firstName',
            'users.lastName',
            'users.otherNames',
            'students.admissionNumber',
            DB::raw("
                COUNT(
                    DISTINCT CASE
                        WHEN cbt_exam_answers.selectedOptionId IS NOT NULL
                          OR cbt_exam_answers.answerText IS NOT NULL
                        THEN cbt_exam_answers.questionId
                        ELSE NULL
                    END
                ) as answeredQuestions
            "),
        ])
        ->groupBy(
            'cbt_exam_attempts.attemptId',
            'cbt_exam_attempts.studentId',
            'cbt_exam_attempts.startedAt',
            'cbt_exam_attempts.submittedAt',
            'cbt_exam_attempts.status',
            'cbt_exam_attempts.score',
            'cbt_exam_attempts.totalQuestions',
            'users.firstName',
            'users.lastName',
            'users.otherNames',
            'students.admissionNumber',
        )
        ->orderByRaw("
            CASE
                WHEN cbt_exam_attempts.status = 'in_progress' THEN 1
                WHEN cbt_exam_attempts.status = 'completed' THEN 2
                ELSE 3
            END
        ")
        ->orderBy('users.lastName')
        ->get();

    $startedCount = $attempts->whereIn('status', ['in_progress', 'completed'])->count();
    $completedCount = $attempts->where('status', 'completed')->count();
    $inProgressCount = $attempts->where('status', 'in_progress')->count();
    $notStartedCount = max(0, $eligibleCandidates - $startedCount);

    $examStatus = $this->determineExamStatus($exam, $now);

    $candidateRows = $attempts->map(function ($attempt) use ($exam, $now) {
        $studentName = trim(collect([
            $attempt->firstName,
            $attempt->lastName,
            $attempt->otherNames,
        ])->filter()->implode(' '));

        $remainingSeconds = null;

        if ($attempt->status === 'in_progress' && $attempt->startedAt) {
            $attemptEnd = Carbon::parse($attempt->startedAt)
                ->addMinutes((int) ($exam->durationMinutes ?? 0));

            $examEnd = $exam->endTime ? Carbon::parse($exam->endTime) : null;

            if ($examEnd && $examEnd->lt($attemptEnd)) {
                $attemptEnd = $examEnd;
            }

            $remainingSeconds = max(0, $now->diffInSeconds($attemptEnd, false));
        }

        return [
            'attemptId' => $attempt->attemptId,
            'studentId' => $attempt->studentId,
            'studentName' => $studentName ?: 'Unknown Student',
            'admissionNumber' => $attempt->admissionNumber,
            'status' => $attempt->status,
            'startedAt' => optional($attempt->startedAt)->toDateTimeString(),
            'submittedAt' => optional($attempt->submittedAt)->toDateTimeString(),
            'answeredQuestions' => (int) ($attempt->answeredQuestions ?? 0),
            'totalQuestions' => (int) ($attempt->totalQuestions ?? 0),
            'score' => (float) ($attempt->score ?? 0),
            'remainingSeconds' => $remainingSeconds,
        ];
    })->values();

    return response()->json([
        'message' => 'Exam monitor data retrieved successfully',
        'data' => [
            'exam' => [
                'examId' => $exam->examId,
                'title' => $exam->title,
                'subjectName' => optional($exam->subject)->subjectName,
                'classId' => $exam->classId,
                'durationMinutes' => (int) ($exam->durationMinutes ?? 0),
                'startsAt' => $exam->startsAt ? Carbon::parse($exam->startsAt)->toDateTimeString() : null,
                'endsAt' => $exam->endsAt ? Carbon::parse($exam->endsAt)->toDateTimeString() : null,
                'status' => $examStatus,
            ],
            'summary' => [
                'eligibleCandidates' => $eligibleCandidates,
                'startedCount' => $startedCount,
                'inProgressCount' => $inProgressCount,
                'completedCount' => $completedCount,
                'notStartedCount' => $notStartedCount,
            ],
            'candidates' => $candidateRows,
        ],
    ]);
}

    protected function determineExamStatus($exam, Carbon $now): string
    {
        $rawStatus = strtolower((string) ($exam->status ?? ''));

        if ($rawStatus === 'cancelled') {
            return 'Cancelled';
        }

        $startTime = $exam->startTime ? Carbon::parse($exam->startTime) : null;
        $endTime = $exam->endTime ? Carbon::parse($exam->endTime) : null;

        if ($startTime && $now->lt($startTime)) {
            return 'Upcoming';
        }

        if ($endTime && $now->gt($endTime)) {
            return 'Ended';
        }

        if (($startTime && $now->gte($startTime)) && (!$endTime || $now->lte($endTime))) {
            return 'Running';
        }

        return 'Upcoming';
    }
}