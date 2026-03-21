<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CbtExam;
use App\Models\CbtExamAttempt;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


use App\Models\CbtExamAnswer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

class StudentCbtController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            // $user = auth()->user();

            // if (!$user) {
            //     return response()->json([
            //         'message' => 'Unauthenticated',
            //     ], 401);
            // }

            /**
             * Adjust this lookup to match your actual relationship.
             * Example possibilities:
             * - $user->student
             * - Student::where('userId', $user->id)->first()
             * - Student::where('studentId', $user->studentId)->first()
             */
            $student = Student::with(['schoolClass', 'school'])
                ->where('userId', $user->id)
                ->first();

            if (!$student) {
                return response()->json([
                    'message' => 'Student profile not found',
                ], 404);
            }

            $schoolId = $student->schoolId ?? null;
            $classId = $student->classId ?? null;

            if (!$schoolId || !$classId) {
                return response()->json([
                    'message' => 'Student school or class is not properly configured',
                ], 422);
            }

            $now = now();

            /**
             * Load only SCHOOL CBT exams for this student's class.
             *
             * Assumed columns on cbt_exams:
             * - cbtExamId
             * - schoolId
             * - classId
             * - subjectId
             * - title
             * - instruction
             * - examType   => 'school' | 'waec' | 'jamb'
             * - totalQuestions
             * - durationMinutes
             * - totalMarks
             * - availableFrom
             * - availableTo
             * - isPublished
             * - status (optional)
             */
            $exams = CbtExam::with(['subject', 'schoolClass'])
                ->where('schoolId', $schoolId)
                ->where('classId', $classId)
                ->where('examType', 'school')
                ->where('isPublished', true)
                ->orderByDesc('availableFrom')
                ->get();

            $payload = $exams->map(function ($exam) use ($student, $now) {
                $attempt = CbtExamAttempt::where('schoolId', $student->schoolId)
                    ->where('cbtExamId', $exam->cbtExamId)
                    ->where('studentId', $student->studentId)
                    ->latest('attemptId')
                    ->first();

                $availableFrom = $exam->availableFrom;
                $availableTo = $exam->availableTo;

                $hasStarted = $attempt && !empty($attempt->startedAt);
                $isSubmitted = $attempt && (bool) $attempt->isSubmitted;
                $isTimedOut = $attempt && (bool) $attempt->isTimedOut;

                $beforeWindow = $availableFrom ? $now->lt($availableFrom) : false;
                $afterWindow = $availableTo ? $now->gt($availableTo) : false;

                $status = 'not_started';
                $canStart = false;
                $canResume = false;
                $canReview = false;

                if ($isSubmitted) {
                    $status = 'completed';
                    $canReview = true;
                } elseif ($hasStarted && !$isSubmitted) {
                    if ($afterWindow || $isTimedOut) {
                        $status = 'missed';
                    } else {
                        $status = 'in_progress';
                        $canResume = true;
                    }
                } else {
                    if ($afterWindow) {
                        $status = 'missed';
                    } elseif (!$beforeWindow) {
                        $status = 'not_started';
                        $canStart = true;
                    } else {
                        $status = 'not_started';
                        $canStart = false;
                    }
                }

                return [
                    'cbtExamId' => $exam->cbtExamId,
                    'title' => $exam->title,
                    'instruction' => $exam->instruction,
                    'subjectName' => optional($exam->subject)->subjectName ?? 'Unknown Subject',
                    'className' => optional($exam->schoolClass)->className ?? null,
                    'totalQuestions' => (int) ($exam->totalQuestions ?? 0),
                    'durationMinutes' => (int) ($exam->durationMinutes ?? 0),
                    'totalMarks' => $exam->totalMarks !== null ? (float) $exam->totalMarks : null,

                    'status' => $status,

                    'availableFrom' => $availableFrom?->toDateTimeString(),
                    'availableTo' => $availableTo?->toDateTimeString(),

                    'startedAt' => $attempt?->startedAt?->toDateTimeString(),
                    'submittedAt' => $attempt?->submittedAt?->toDateTimeString(),

                    'score' => $attempt?->score !== null ? (float) $attempt->score : null,
                    'percentage' => $attempt?->percentage !== null ? (float) $attempt->percentage : null,

                    'canStart' => $canStart,
                    'canResume' => $canResume,
                    'canReview' => $canReview,
                ];
            })->values();

            return response()->json([
                'schoolName' => optional($student->school)->schoolName ?? 'Your School',
                'schoolLogo' => optional($student->school)->logo ?? null,
                'student' => [
                    'studentId' => $student->studentId,
                    'studentName' => trim(($student->firstName ?? '') . ' ' . ($student->lastName ?? '') . ' ' . ($student->otherNames ?? '')),
                    'firstName' => $student->firstName,
                    'className' => optional($student->schoolClass)->className ?? null,
                    'admissionNumber' => $student->schoolAssignedAdmissionNumber ?? $student->admissionNumber ?? null,
                ],
                'exams' => $payload,
            ]);
        } catch (\Throwable $e) {
            \Log::error('Failed to load student CBT exams', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to load student CBT exams',
            ], 500);
        }
    }
    protected function getAuthenticatedStudent(Request $request): array
    {
        $token = $request->cookie('student_token');

        if (!$token) {
            abort(response()->json(['message' => 'Student token missing'], 401));
        }

        JWTAuth::setToken($token);
        $payload = JWTAuth::getPayload();

        $studentId = $payload->get('student_id');
        $schoolId = $payload->get('school_id');

        $student = Student::find($studentId);

        if (!$student) {
            abort(response()->json(['message' => 'Student not found'], 404));
        }

        return [
            'student' => $student,
            'studentId' => $studentId,
            'schoolId' => $schoolId,
        ];
    }

    protected function getExamForStudent(int $examId, int $schoolId, Student $student): CbtExam
    {
        $exam = CbtExam::with([
            'sections.questions.options'
        ])
            ->where('examId', $examId)
            ->where('schoolId', $schoolId)
            ->first();

        if (!$exam) {
            abort(response()->json(['message' => 'Exam not found'], 404));
        }

        if ((int) $exam->classId !== (int) $student->classes->first()->classId) {
            abort(response()->json(['message' => 'You are not allowed to access this exam'], 403));
        }

        if (in_array($exam->status ?? null, ['draft', 'archived'])) {
            abort(response()->json(['message' => 'This exam is not available'], 403));
        }

        return $exam;
    }

    protected function examHasExpired(CbtExam $exam, CbtExamAttempt $attempt): bool
    {
        $now = Carbon::now();

        if ($exam->endTime && $now->gt(Carbon::parse($exam->endTime))) {
            return true;
        }

        if ($attempt->startedAt && $exam->durationMinutes) {
            $attemptEnd = Carbon::parse($attempt->startedAt)->addMinutes((int) $exam->durationMinutes);
            if ($now->gte($attemptEnd)) {
                return true;
            }
        }

        return false;
    }

   protected function buildAttemptResponse(CbtExam $exam, CbtExamAttempt $attempt, Student $student): array
{
    $answers = CbtExamAnswer::where('attemptId', $attempt->attemptId)
        ->get()
        ->keyBy('questionId');

    // purely for display only
    $displayEndsAt = null;
    if ($attempt->status === 'in_progress' && (int) $attempt->timeRemainingSeconds > 0) {
        $displayEndsAt = now()->copy()->addSeconds((int) $attempt->timeRemainingSeconds);
    }

    return [
        'exam' => [
            'examId' => $exam->examId,
            'title' => $exam->title,
            'subjectName' => optional($exam->subject)->subjectName,
            'durationMinutes' => (int) $exam->durationMinutes,
            'instruction' => $exam->instruction,
            'startedAt' => optional($attempt->startedAt)?->toDateTimeString(),
            'endsAt' => $displayEndsAt?->toDateTimeString(),
            'timeRemainingSeconds' => (int) $attempt->timeRemainingSeconds,
        ],
        'student' => [
            'studentId' => $student->studentId,
            'studentName' => trim(($student->user->firstName ?? '') . ' ' . ($student->user->lastName ?? '')),
            'className' => $student->classes()->first()?->className,
            'passportUrl' => $student->passport ? url($student->passport) : null,
        ],
        'attempt' => [
            'attemptId' => $attempt->attemptId,
            'status' => $attempt->status,
        ],
        'sections' => $exam->sections->map(function ($section) use ($answers) {
            return [
                'sectionId' => $section->sectionId,
                'title' => $section->title,
                'description' => $section->description,
                'instructions' => $section->instructions,
                'orderIndex' => (int) $section->orderIndex,
                'questions' => $section->questions->map(function ($question) use ($answers) {
                    $answer = $answers->get($question->questionId);

                    return [
                        'questionId' => $question->questionId,
                        'questionText' => $question->questionText,
                        'type' => $question->type,
                        'mark' => (float) ($question->mark ?? 1),
                        'orderIndex' => (int) $question->orderIndex,
                        'options' => $question->options->map(function ($option) {
                            return [
                                'optionId' => $option->optionId,
                                'optionText' => $option->optionText,
                                'optionLabel' => $option->optionLabel,
                            ];
                        })->values(),
                        'studentAnswer' => [
                            'selectedOptionId' => $answer?->selectedOptionId,
                            'answerText' => $answer?->answerText,
                            'isFlagged' => (bool) ($answer?->isFlagged ?? false),
                        ],
                    ];
                })->values(),
            ];
        })->values(),
    ];
}

    public function start(Request $request, int $examId): JsonResponse
{
    $auth = $this->getAuthenticatedStudent($request);
    $student = $auth['student'];
    $studentId = $auth['studentId'];
    $schoolId = $auth['schoolId'];

    $exam = $this->getExamForStudent($examId, $schoolId, $student);

    $now = Carbon::now();

    if ($exam->startTime && $now->lt(Carbon::parse($exam->startTime))) {
        return response()->json([
            'message' => 'This exam has not started yet',
        ], 403);
    }

    if ($exam->endTime && $now->gt(Carbon::parse($exam->endTime))) {
        return response()->json([
            'message' => 'This exam is no longer available',
        ], 403);
    }

    $attempt = CbtExamAttempt::where('examId', $exam->examId)
        ->where('studentId', $studentId)
        ->where('schoolId', $schoolId)
        ->whereIn('status', ['in_progress', 'paused', 'completed', 'expired'])
        ->latest('attemptId')
        ->first();


        if ($response = $this->ensureExamNotForceEnded($exam)) {
    return $response;
}

    if ($attempt) {
        $this->autoPauseIfHeartbeatMissing($attempt, 30);
        $attempt->refresh();

        if ($attempt->status === 'completed') {
            return response()->json([
                'message' => 'You have already completed this exam',
            ], 403);
        }

        if ((int) $attempt->timeRemainingSeconds <= 0 || $attempt->status === 'expired') {
            $attempt->update([
                'status' => 'expired',
                'timeRemainingSeconds' => 0,
            ]);

            return response()->json([
                'message' => 'Exam attempt has expired',
            ], 410);
        }

        if ($attempt->status === 'paused') {
            $attempt->update([
                'status' => 'in_progress',
                'pausedAt' => null,
                'lastHeartbeatAt' => now(),
            ]);

            $attempt->refresh();
        } elseif ($attempt->status === 'in_progress' && !$attempt->lastHeartbeatAt) {
            $attempt->update([
                'lastHeartbeatAt' => now(),
            ]);

            $attempt->refresh();
        }
    } else {
        $totalQuestions = $exam->sections->sum(
            fn($section) => $section->questions->count()
        );

        $startedAt = $now->copy();
        $endsAt = $startedAt->copy()->addMinutes((int) $exam->durationMinutes);

        $attempt = CbtExamAttempt::create([
            'examId' => $exam->examId,
            'studentId' => $studentId,
            'schoolId' => $schoolId,
            'classId' => $exam->classId,
            'startedAt' => $startedAt,
            'endsAt' => $endsAt,
            'status' => 'in_progress',
            'score' => 0,
            'totalQuestions' => $totalQuestions,
            'answeredQuestions' => 0,
            'timeSpentSeconds' => 0,
            'timeRemainingSeconds' => ((int) $exam->durationMinutes) * 60,
            'lastHeartbeatAt' => now(),
            'pauseCount' => 0,
            'totalPausedSeconds' => 0,
        ]);
    }

    return response()->json([
        'message' => 'Exam started successfully',
        'data' => $this->buildAttemptResponse($exam, $attempt, $student),
    ]);
}
    public function attempt(Request $request, int $examId): JsonResponse
    {
        $auth = $this->getAuthenticatedStudent($request);
        $student = $auth['student'];
        $studentId = $auth['studentId'];
        $schoolId = $auth['schoolId'];

        $exam = $this->getExamForStudent($examId, $schoolId, $student);

        $attempt = CbtExamAttempt::where('examId', $exam->examId)
            ->where('studentId', $studentId)
            ->where('schoolId', $schoolId)
            ->latest('attemptId')
            ->first();

            if ($attempt) {
    $this->autoPauseIfHeartbeatMissing($attempt, 30);
    $attempt->refresh();
}
        if (!$attempt) {
            return response()->json([
                'message' => 'No active attempt found for this exam',
            ], 404);
        }

        if ($attempt->status === 'completed') {
            return response()->json([
                'message' => 'This exam has already been submitted',
            ], 403);
        }

        if ((int) $attempt->timeRemainingSeconds <= 0) {
            $attempt->update([
                'status' => 'expired',
                'timeRemainingSeconds' => 0,
            ]);

            return response()->json([
                'message' => 'Exam attempt has expired',
            ], 410);
        }

        if ($attempt->status === 'paused') {
            $attempt->update([
                'status' => 'in_progress',
                'pausedAt' => null,
                'lastHeartbeatAt' => now(),
            ]);

            $attempt->refresh();
        } elseif ($attempt->status === 'in_progress' && !$attempt->lastHeartbeatAt) {
            $attempt->update([
                'lastHeartbeatAt' => now(),
            ]);

            $attempt->refresh();
        }

        return response()->json([
            'message' => 'Attempt loaded successfully',
            'data' => $this->buildAttemptResponse($exam, $attempt, $student),
        ]);
    }
    public function saveAnswer(Request $request, int $examId): JsonResponse
    {
        $request->validate([
            'questionId' => 'required|integer',
            'selectedOptionId' => 'nullable|integer',
            'answerText' => 'nullable|string',
            'isFlagged' => 'nullable|boolean',
        ]);

        $auth = $this->getAuthenticatedStudent($request);
        $student = $auth['student'];
        $studentId = $auth['studentId'];
        $schoolId = $auth['schoolId'];

        $exam = $this->getExamForStudent($examId, $schoolId, $student);

        $attempt = CbtExamAttempt::where('examId', $exam->examId)
            ->where('studentId', $studentId)
            ->where('schoolId', $schoolId)
            ->where('status', 'in_progress')
            ->latest('attemptId')
            ->first();

        if (!$attempt) {
            return response()->json([
                'message' => 'No active exam attempt found',
            ], 404);
        }


        if ($response = $this->ensureExamNotForceEnded($exam)) {
    return $response;
}

        $this->autoPauseIfHeartbeatMissing($attempt, 30);
$attempt->refresh();

        if ((int) $attempt->timeRemainingSeconds <= 0 || $attempt->status !== 'in_progress') {
    return response()->json([
        'message' => 'This exam is no longer active',
    ], 403);
}

        $question = $exam->sections
            ->flatMap(fn($section) => $section->questions)
            ->firstWhere('questionId', (int) $request->questionId);

        if (!$question) {
            return response()->json([
                'message' => 'Question not found in this exam',
            ], 404);
        }

        $selectedOptionId = $request->selectedOptionId ? (int) $request->selectedOptionId : null;
        $answerText = $request->answerText;
        $isFlagged = (bool) $request->boolean('isFlagged', false);

        $isCorrect = null;
        $scoreAwarded = 0;

        // Try to detect correct option robustly
        $correctOption = $question->options->first(function ($option) {
            return (int) ($option->isCorrect ?? 0) === 1 || $option->isCorrect === true;
        });

        // Score objective questions whenever a correct option exists
        if ($correctOption && $selectedOptionId) {
            $isCorrect = ((int) $selectedOptionId === (int) $correctOption->optionId);
            $scoreAwarded = $isCorrect ? (float) ($question->mark ?? 1) : 0;
        }

        $answer = CbtExamAnswer::updateOrCreate(
            [
                'attemptId' => $attempt->attemptId,
                'questionId' => $question->questionId,
            ],
            [
                'examId' => $exam->examId,
                'studentId' => $studentId,
                'selectedOptionId' => $selectedOptionId,
                'answerText' => $answerText,
                'isCorrect' => $isCorrect,
                'scoreAwarded' => $scoreAwarded,
                'isFlagged' => $isFlagged,
                'answeredAt' => now(),
            ]
        );

        $answeredCount = CbtExamAnswer::where('attemptId', $attempt->attemptId)
            ->where(function ($query) {
                $query->whereNotNull('selectedOptionId')
                    ->orWhereNotNull('answerText');
            })
            ->count();

        $attemptScore = (float) CbtExamAnswer::where('attemptId', $attempt->attemptId)->sum('scoreAwarded');

        $attempt->update([
            'answeredQuestions' => $answeredCount,
            'score' => $attemptScore,
        ]);

        return response()->json([
            'message' => 'Answer saved successfully',
            'data' => [
                'answerId' => $answer->answerId,
                'questionId' => $answer->questionId,
                'selectedOptionId' => $answer->selectedOptionId,
                'answerText' => $answer->answerText,
                'isFlagged' => (bool) $answer->isFlagged,
                'isCorrect' => $answer->isCorrect,
                'scoreAwarded' => (float) $answer->scoreAwarded,
                'attemptScore' => $attemptScore,
            ],
        ]);
    }


    public function heartbeat(Request $request, int $examId): JsonResponse
{
    $student = $this->getAuthenticatedStudent($request);
    if (!$student) {
        return response()->json(['message' => 'Unauthenticated'], 401);
    }

    $exam = CbtExam::where('examId', $examId)
        ->where('schoolId', $student['schoolId'])
        ->first();

    if (!$exam) {
        return response()->json(['message' => 'Exam not found'], 404);
    }

    $attempt = CbtExamAttempt::where('examId', $examId)
        ->where('studentId', $student['studentId'])
        ->where('schoolId', $student['schoolId'])
        ->latest('attemptId')
        ->first();

    if (!$attempt) {
        return response()->json(['message' => 'Attempt not found'], 404);
    }

    if ($exam->forceEndedAt) {
        if (!in_array($attempt->status, ['completed', 'expired'])) {
            $this->finalizeExamAttempt($attempt, true);
            $attempt->refresh();
        }

        return response()->json([
            'message' => 'Exam has been ended by the examiner.',
            'timeRemainingSeconds' => 0,
            'status' => 'completed',
            'forceEnded' => true,
        ], 200);
    }

    if ($attempt->status !== 'in_progress') {
        return response()->json([
            'message' => 'Attempt is not active',
            'timeRemainingSeconds' => (int) $attempt->timeRemainingSeconds,
            'status' => $attempt->status,
        ], 409);
    }

    $now = now();

    $elapsed = $attempt->lastHeartbeatAt
        ? Carbon::parse($attempt->lastHeartbeatAt)->diffInSeconds($now)
        : 0;

    $remaining = max(0, ((int) $attempt->timeRemainingSeconds) - $elapsed);

    $attempt->timeRemainingSeconds = $remaining;
    $attempt->lastHeartbeatAt = $now;

    if ($remaining <= 0) {
        $attempt->status = 'expired';
        $attempt->timeRemainingSeconds = 0;
    }

    $attempt->save();

    return response()->json([
        'message' => 'Heartbeat recorded',
        'timeRemainingSeconds' => (int) $attempt->timeRemainingSeconds,
        'status' => $attempt->status,
    ]);
}


    public function pauseExam(Request $request, int $examId)
    {
        $student = $this->getAuthenticatedStudent($request);
        if (!$student) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $exam = CbtExam::where('examId', $examId)
        ->where('schoolId', $student['schoolId'])
        ->first();

    if (!$exam) {
        return response()->json(['message' => 'Exam not found'], 404);
    }

        $attempt = CbtExamAttempt::where('examId', $examId)
            ->where('studentId', $student['studentId'])
            ->where('schoolId', $student['schoolId'])
            ->latest('attemptId')
            ->first();

        if (!$attempt) {
            return response()->json(['message' => 'Attempt not found'], 404);
        }

        if ($response = $this->ensureExamNotForceEnded($exam)) {
    return $response;
}

        if ($attempt->status !== 'in_progress') {
            return response()->json([
                'message' => 'Attempt is not active',
                'status' => $attempt->status,
                'timeRemainingSeconds' => (int) $attempt->timeRemainingSeconds,
            ]);
        }

        $now = now();

        if ($attempt->lastHeartbeatAt) {
            // $elapsed = max(0, $attempt->lastHeartbeatAt->diffInSeconds($now));
            $elapsed = 0;

            if ($attempt->lastHeartbeatAt) {
                $elapsed = max(
                    0,
                    Carbon::parse($attempt->lastHeartbeatAt)->diffInSeconds($now)
                );
            }
            $attempt->timeRemainingSeconds = max(0, ((int) $attempt->timeRemainingSeconds) - $elapsed);
        }

        if ($attempt->timeRemainingSeconds <= 0) {
            $attempt->status = 'expired';
            $attempt->timeRemainingSeconds = 0;
        } else {
            $attempt->status = 'paused';
            $attempt->pausedAt = $now;
            $attempt->pauseCount = ((int) $attempt->pauseCount) + 1;
        }

        $attempt->lastHeartbeatAt = null;
        $attempt->save();

        return response()->json([
            'message' => 'Exam paused successfully',
            'status' => $attempt->status,
            'timeRemainingSeconds' => (int) $attempt->timeRemainingSeconds,
        ]);
    }


    public function submit(Request $request, int $examId): JsonResponse
    {
        $auth = $this->getAuthenticatedStudent($request);
        $student = $auth['student'];
        $studentId = $auth['studentId'];
        $schoolId = $auth['schoolId'];

        $exam = $this->getExamForStudent($examId, $schoolId, $student);

        $attempt = CbtExamAttempt::where('examId', $exam->examId)
            ->where('studentId', $studentId)
            ->where('schoolId', $schoolId)
            ->where('status', 'in_progress')
            ->latest('attemptId')
            ->first();


        if (!$attempt) {
            return response()->json([
                'message' => 'No active exam attempt found',
            ], 404);
        }

        if ($response = $this->ensureExamNotForceEnded($exam)) {
    return $response;
}

        $this->finalizeAttempt($exam, $attempt);

        return response()->json([
            'message' => 'Exam submitted successfully',
            'data' => [
                'attemptId' => $attempt->attemptId,
                'status' => 'completed',
                'score' => (float) $attempt->fresh()->score,
                'answeredQuestions' => (int) $attempt->fresh()->answeredQuestions,
                'totalQuestions' => (int) $attempt->fresh()->totalQuestions,
                'submittedAt' => optional($attempt->fresh()->submittedAt)->toDateTimeString(),
            ],
        ]);
    }

    protected function finalizeAttempt(CbtExam $exam, CbtExamAttempt $attempt): void
    {
        DB::transaction(function () use ($exam, $attempt) {
            $answers = CbtExamAnswer::where('attemptId', $attempt->attemptId)->get();

            $score = (float) $answers->sum('scoreAwarded');
            $answeredCount = $answers->filter(function ($answer) {
                return !is_null($answer->selectedOptionId) || !is_null($answer->answerText);
            })->count();

            $startedAt = $attempt->startedAt ? Carbon::parse($attempt->startedAt) : now();
            $timeSpentSeconds = $startedAt->diffInSeconds(now());

            $attempt->update([
                'status' => 'completed',
                'submittedAt' => now(),
                'score' => $score,
                'answeredQuestions' => $answeredCount,
                'timeSpentSeconds' => $timeSpentSeconds,
            ]);
        });
    }


    public function submissionSummary(Request $request, int $examId): JsonResponse
    {
        $auth = $this->getAuthenticatedStudent($request);
        $student = $auth['student'];
        $studentId = $auth['studentId'];
        $schoolId = $auth['schoolId'];

        $exam = $this->getExamForStudent($examId, $schoolId, $student);

        $attempt = CbtExamAttempt::where('examId', $exam->examId)
            ->where('studentId', $studentId)
            ->where('schoolId', $schoolId)
            ->latest('attemptId')
            ->first();

        if (!$attempt) {
            return response()->json([
                'message' => 'Submission summary not found',
            ], 404);
        }

        $totalQuestions = $exam->sections->sum(function ($section) {
            return $section->questions->count();
        });

        $totalObtainableMarks = $exam->sections->sum(function ($section) {
            return $section->questions->sum(function ($question) {
                return (float) ($question->mark ?? 1);
            });
        });

        $percentage = 0;
        if ($totalObtainableMarks > 0) {
            $percentage = round(((float) $attempt->score / $totalObtainableMarks) * 100, 2);
        }

        return response()->json([
            'message' => 'Submission summary retrieved successfully',
            'data' => [
                'examId' => $exam->examId,
                'title' => $exam->title,
                'subjectName' => optional($exam->subject)->subjectName,
                'status' => $attempt->status,
                'submittedAt' => optional($attempt->submittedAt)->toDateTimeString(),
                'score' => (float) $attempt->score,
                'totalQuestions' => (int) $totalQuestions,
                'answeredQuestions' => (int) $attempt->answeredQuestions,
                'durationMinutes' => (int) $exam->durationMinutes,
                'percentage' => $percentage,
                'canReview' => false,
            ],
        ]);
    }


    public function review(Request $request, int $examId): JsonResponse
    {
        $auth = $this->getAuthenticatedStudent($request);
        $student = $auth['student'];
        $studentId = $auth['studentId'];
        $schoolId = $auth['schoolId'];

        $exam = $this->getExamForStudent($examId, $schoolId, $student);

        $attempt = CbtExamAttempt::where('examId', $exam->examId)
            ->where('studentId', $studentId)
            ->where('schoolId', $schoolId)
            ->where('status', 'completed')
            ->latest('attemptId')
            ->first();

        if (!$attempt) {
            return response()->json([
                'message' => 'Review not available for this exam',
            ], 404);
        }

        $answers = CbtExamAnswer::where('attemptId', $attempt->attemptId)
            ->get()
            ->keyBy('questionId');

        $totalQuestions = $exam->sections->sum(function ($section) {
            return $section->questions->count();
        });

        $totalMarks = $exam->sections->sum(function ($section) {
            return $section->questions->sum(function ($question) {
                return (float) ($question->mark ?? 1);
            });
        });

        $percentage = $totalMarks > 0
            ? round(((float) $attempt->score / $totalMarks) * 100, 2)
            : 0;

        return response()->json([
            'exam' => [
                'examId' => $exam->examId,
                'title' => $exam->title,
                'subjectName' => optional($exam->subject)->subjectName,
                'durationMinutes' => (int) $exam->durationMinutes,
                'submittedAt' => optional($attempt->submittedAt)->toDateTimeString(),
                'score' => (float) $attempt->score,
                'totalMarks' => (float) $totalMarks,
                'percentage' => $percentage,
                'totalQuestions' => (int) $totalQuestions,
                'answeredQuestions' => (int) $attempt->answeredQuestions,
            ],
            'sections' => $exam->sections->map(function ($section) use ($answers) {
                return [
                    'sectionId' => $section->sectionId,
                    'title' => $section->title,
                    'description' => $section->description,
                    'instructions' => $section->instructions,
                    'orderIndex' => (int) $section->orderIndex,
                    'questions' => $section->questions->map(function ($question) use ($answers) {
                        $answer = $answers->get($question->questionId);

                        $correctOption = $question->options->first(function ($option) {
                            return (int) ($option->isCorrect ?? 0) === 1 || $option->isCorrect === true;
                        });

                        $isAnswered = !is_null($answer?->selectedOptionId) || !is_null($answer?->answerText);

                        return [
                            'questionId' => $question->questionId,
                            'questionText' => $question->questionText,
                            'questionType' => $question->questionType ?? $question->type ?? 'multiple_choice',
                            'mark' => (float) ($question->mark ?? 1),
                            'orderIndex' => (int) ($question->orderIndex ?? 0),
                            'options' => $question->options->map(function ($option) {
                                return [
                                    'optionId' => $option->optionId,
                                    'optionText' => $option->optionText,
                                    'optionLabel' => $option->optionLabel,
                                    'isCorrect' => (bool) ($option->isCorrect ?? false),
                                ];
                            })->values(),
                            'selectedOptionId' => $answer?->selectedOptionId,
                            'answerText' => $answer?->answerText,
                            'correctOptionId' => $correctOption?->optionId,
                            'isCorrect' => $answer?->isCorrect,
                            'scoreAwarded' => (float) ($answer?->scoreAwarded ?? 0),
                            'isAnswered' => $isAnswered,
                        ];
                    })->values(),
                ];
            })->values(),
        ]);
    }



    public function resumeSummary(Request $request, int $examId): JsonResponse
    {
        try {
            $auth = $this->getAuthenticatedStudent($request);
            $studentId = $auth['studentId'];
            $schoolId = $auth['schoolId'];

            $exam = CbtExam::with([
                'subject:subjectId,subjectName',
                'questions',
            ])
                ->where('examId', $examId)
                ->where('schoolId', $schoolId)
                ->first();

            if (!$exam) {
                return response()->json([
                    'message' => 'Exam not found',
                ], 404);
            }

            $attempt = CbtExamAttempt::with(['answers'])
                ->where('examId', $examId)
                ->where('studentId', $studentId)
                ->where('schoolId', $schoolId)
                ->latest('attemptId')
                ->first();



                if ($response = $this->ensureExamNotForceEnded($exam)) {
    return $response;
}


                if ($attempt) {
    $this->autoPauseIfHeartbeatMissing($attempt, 30);
    $attempt->refresh();
}
            if (!$attempt) {
                return response()->json([
                    'message' => 'No existing attempt found for this exam',
                ], 404);
            }

            if ($attempt->status !== 'completed' && (int) $attempt->timeRemainingSeconds <= 0) {
                $attempt->update([
                    'status' => 'expired',
                    'timeRemainingSeconds' => 0,
                ]);

                $attempt->refresh();
            }

            $totalQuestions = (int) ($attempt->totalQuestions ?: $exam->questions()->count());

            $answers = $attempt->answers ?? collect();

            $answeredCount = $answers->filter(function ($answer) {
                return !is_null($answer->selectedOptionId) || filled($answer->answerText);
            })->count();

            $flaggedCount = $answers->filter(function ($answer) {
                return (bool) $answer->isFlagged;
            })->count();

            $unansweredCount = max(0, $totalQuestions - $answeredCount);

            return response()->json([
                'message' => 'Resume summary fetched successfully',
                'data' => [
                    'examId' => $exam->examId,
                    'title' => $exam->title,
                    'subjectName' => optional($exam->subject)->subjectName,
                    'durationMinutes' => (int) $exam->durationMinutes,
                    'totalQuestions' => $totalQuestions,
                    'answeredCount' => $answeredCount,
                    'flaggedCount' => $flaggedCount,
                    'unansweredCount' => $unansweredCount,
                    'startedAt' => optional($attempt->startedAt)?->toISOString(),
                    'pausedAt' => optional($attempt->pausedAt)?->toISOString(),
                    'endsAt' => optional($attempt->endsAt)?->toISOString(),
                    'timeRemainingSeconds' => (int) $attempt->timeRemainingSeconds,
                    'status' => $attempt->status,
                ],
            ]);
        } catch (\Throwable $e) {
            \Log::error('Resume summary failed', [
                'examId' => $examId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to fetch resume summary right now',
            ], 500);
        }
    }



    protected function autoPauseIfHeartbeatMissing(CbtExamAttempt $attempt, int $graceSeconds = 30): void
{
    if ($attempt->status !== 'in_progress') {
        return;
    }

    if (!$attempt->lastHeartbeatAt) {
        return;
    }

    $now = now();
    $lastHeartbeatAt = Carbon::parse($attempt->lastHeartbeatAt);
    $secondsSinceLastHeartbeat = $lastHeartbeatAt->diffInSeconds($now);

    if ($secondsSinceLastHeartbeat < $graceSeconds) {
        return;
    }

    $elapsed = max(0, $secondsSinceLastHeartbeat);
    $remaining = max(0, ((int) $attempt->timeRemainingSeconds) - $elapsed);

    $attempt->timeRemainingSeconds = $remaining;

    if ($remaining <= 0) {
        $attempt->status = 'expired';
        $attempt->timeRemainingSeconds = 0;
    } else {
        $attempt->status = 'paused';
        $attempt->pausedAt = $now;
        $attempt->pauseCount = ((int) $attempt->pauseCount) + 1;
    }

    $attempt->lastHeartbeatAt = null;
    $attempt->save();
}


public function forceEndExam(Request $request, int $examId): JsonResponse
{
    // $authUser = auth()->user();
    $schoolId = $request->header('X-School-ID');

    // if (!$authUser) {
    //     return response()->json([
    //         'message' => 'Unauthenticated',
    //     ], 401);
    // }

    $exam = CbtExam::where('examId', $examId)
        ->where('schoolId', $schoolId)
        ->firstOrFail();

    if ($exam->forceEndedAt) {
        return response()->json([
            'message' => 'Exam has already been ended.',
        ], 422);
    }

    DB::transaction(function () use ($exam) {
        $exam->update([
            'forceEndedAt' => now(),
            'forceEndedBy' => auth()->id(),
            'forceEndReason' => 'Ended by examiner',
            'status' => 'ended',
        ]);

        $attempts = CbtExamAttempt::where('examId', $exam->examId)
            ->whereIn('status', ['in_progress', 'paused'])
            ->lockForUpdate()
            ->get();

        foreach ($attempts as $attempt) {
            $this->finalizeExamAttempt($attempt, true);
        }
    });

    return response()->json([
        'message' => 'Exam ended successfully for all candidates.',
    ]);
}

protected function finalizeExamAttempt(CbtExamAttempt $attempt, bool $forced = false): void
{
    if (in_array($attempt->status, ['completed', 'expired'])) {
        return;
    }

    $attempt->loadMissing([
        'exam.sections.questions.options',
        'answers',
    ]);

    if (!$attempt->exam) {
        throw new \RuntimeException("Exam relationship not found for attempt {$attempt->attemptId}");
    }

    $score = 0;
    $answeredCount = 0;

    $answersByQuestion = $attempt->answers->keyBy('questionId');

    foreach ($attempt->exam->sections as $section) {
        foreach ($section->questions as $question) {
            $answer = $answersByQuestion->get($question->questionId);

            if (!$answer || !$answer->selectedOptionId) {
                continue;
            }

            $answeredCount++;

            $correctOption = $question->options->firstWhere('isCorrect', true);
            $questionMark = (float) ($question->mark ?? $question->marks ?? 1);

            if (
                $correctOption &&
                (int) $answer->selectedOptionId === (int) $correctOption->optionId
            ) {
                $score += $questionMark;
            }
        }
    }

    $attempt->update([
        'status' => 'completed',
        'submittedAt' => now(),
        'score' => $score,
        'answeredQuestions' => $answeredCount,
        'timeRemainingSeconds' => 0,
        'lastHeartbeatAt' => null,
    ]);
}

protected function ensureExamNotForceEnded($exam): ?JsonResponse
{
    if ($exam->forceEndedAt) {
        return response()->json([
            'message' => 'This exam has been ended by the examiner.',
            'status' => 'force_ended',
        ], 410);
    }

    return null;
}
}
