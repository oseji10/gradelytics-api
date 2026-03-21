<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveJambAnswerRequest;
use App\Http\Requests\StartJambPracticeRequest;
use App\Models\JambAttempt;
use App\Models\JambAttemptQuestion;
use App\Models\JambSubject;
use App\Models\JambTopic;
use App\Services\JambPracticeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentJambController extends Controller
{
    public function __construct(
        protected JambPracticeService $practiceService
    ) {
    }

    /**
     * Replace this with your existing student auth resolver.
     */
    protected function getAuthenticatedStudent(Request $request): array
    {
        $student = $request->attributes->get('student');

        if (!$student) {
            abort(response()->json([
                'message' => 'Unauthenticated student',
            ], 401));
        }

        return [
            'student' => $student,
            'studentId' => $student->studentId,
            'schoolId' => $student->schoolId ?? null,
        ];
    }

    public function subjects(): JsonResponse
    {
        $subjects = JambSubject::where('isActive', true)
            ->orderBy('name')
            ->get(['subjectId', 'name', 'code']);

        return response()->json([
            'subjects' => $subjects,
        ]);
    }

    public function topics(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subjectId' => ['required', 'integer', 'exists:jamb_subjects,subjectId'],
        ]);

        $topics = JambTopic::where('subjectId', $validated['subjectId'])
            ->where('isActive', true)
            ->orderBy('name')
            ->get(['topicId', 'subjectId', 'name', 'description']);

        return response()->json([
            'topics' => $topics,
        ]);
    }

    public function startPractice(StartJambPracticeRequest $request): JsonResponse
    {
        $auth = $this->getAuthenticatedStudent($request);

        $attempt = $this->practiceService->startPracticeAttempt(
            $auth['studentId'],
            $request->validated()
        );

        return response()->json([
            'message' => 'JAMB practice attempt created successfully',
            'attempt' => $attempt,
        ], 201);
    }

    public function showAttempt(Request $request, int $attemptId): JsonResponse
    {
        $auth = $this->getAuthenticatedStudent($request);

        $attempt = JambAttempt::with([
            'subject',
            'topic',
            'attemptQuestions.question.subject',
            'attemptQuestions.question.topic',
            'attemptQuestions.question.options',
        ])
            ->where('attemptId', $attemptId)
            ->where('studentId', $auth['studentId'])
            ->first();

        if (!$attempt) {
            return response()->json([
                'message' => 'Attempt not found',
            ], 404);
        }

        $this->practiceService->expireAttemptIfNeeded($attempt);
        $attempt->refresh();

        return response()->json([
            'attempt' => [
                'attemptId' => $attempt->attemptId,
                'mode' => $attempt->mode,
                'status' => $attempt->status,
                'subject' => $attempt->subject,
                'topic' => $attempt->topic,
                'durationMinutes' => $attempt->durationMinutes,
                'timeRemainingSeconds' => $attempt->timeRemainingSeconds,
                'totalQuestions' => $attempt->totalQuestions,
                'answeredQuestions' => $attempt->answeredQuestions,
                'correctAnswers' => $attempt->correctAnswers,
                'wrongAnswers' => $attempt->wrongAnswers,
                'unansweredQuestions' => $attempt->unansweredQuestions,
                'score' => $attempt->score,
                'percentage' => $attempt->percentage,
                'startedAt' => $attempt->startedAt,
                'submittedAt' => $attempt->submittedAt,
                'expiresAt' => $attempt->expiresAt,
                'currentQuestionOrder' => $attempt->currentQuestionOrder,
                'questions' => $attempt->attemptQuestions->map(function ($item) use ($attempt) {
                    return [
                        'attemptQuestionId' => $item->attemptQuestionId,
                        'questionOrder' => $item->questionOrder,
                        'selectedOption' => $item->selectedOption,
                        'isAnswered' => $item->isAnswered,
                        'isCorrect' => $attempt->status === 'submitted' || $attempt->status === 'expired'
                            ? $item->isCorrect
                            : null,
                        'isFlagged' => $item->isFlagged,
                        'timeSpentSeconds' => $item->timeSpentSeconds,
                        'question' => [
                            'questionId' => $item->question->questionId,
                            'questionText' => $item->question->questionText,
                            'questionImage' => $item->question->questionImage,
                            'passageText' => $item->question->passageText,
                            'difficulty' => $item->question->difficulty,
                            'year' => $item->question->year,
                            'topic' => $item->question->topic
                                ? [
                                    'topicId' => $item->question->topic->topicId,
                                    'name' => $item->question->topic->name,
                                ]
                                : null,
                            'options' => $item->question->options->map(function ($option) use ($attempt) {
                                return [
                                    'optionId' => $option->optionId,
                                    'optionLabel' => $option->optionLabel,
                                    'optionText' => $option->optionText,
                                    'isCorrect' => $attempt->status === 'submitted' || $attempt->status === 'expired'
                                        ? $option->isCorrect
                                        : null,
                                ];
                            }),
                            'explanation' => $attempt->status === 'submitted' || $attempt->status === 'expired'
                                ? $item->question->explanation
                                : null,
                        ],
                    ];
                }),
            ],
        ]);
    }

    public function saveAnswer(SaveJambAnswerRequest $request, int $attemptId): JsonResponse
    {
        $auth = $this->getAuthenticatedStudent($request);

        $attempt = JambAttempt::where('attemptId', $attemptId)
            ->where('studentId', $auth['studentId'])
            ->first();

        if (!$attempt) {
            return response()->json([
                'message' => 'Attempt not found',
            ], 404);
        }

        $attemptQuestion = $this->practiceService->saveAnswer($attempt, $request->validated());

        $attempt->refresh();

        return response()->json([
            'message' => 'Answer saved successfully',
            'attemptSummary' => [
                'attemptId' => $attempt->attemptId,
                'status' => $attempt->status,
                'answeredQuestions' => $attempt->answeredQuestions,
                'unansweredQuestions' => $attempt->unansweredQuestions,
                'currentQuestionOrder' => $attempt->currentQuestionOrder,
                'timeRemainingSeconds' => $attempt->timeRemainingSeconds,
            ],
            'question' => [
                'attemptQuestionId' => $attemptQuestion->attemptQuestionId,
                'selectedOption' => $attemptQuestion->selectedOption,
                'isAnswered' => $attemptQuestion->isAnswered,
                'isFlagged' => $attemptQuestion->isFlagged,
                'timeSpentSeconds' => $attemptQuestion->timeSpentSeconds,
            ],
        ]);
    }

    public function submit(Request $request, int $attemptId): JsonResponse
    {
        $auth = $this->getAuthenticatedStudent($request);

        $attempt = JambAttempt::where('attemptId', $attemptId)
            ->where('studentId', $auth['studentId'])
            ->first();

        if (!$attempt) {
            return response()->json([
                'message' => 'Attempt not found',
            ], 404);
        }

        $attempt = $this->practiceService->submitAttempt($attempt);

        return response()->json([
            'message' => 'Attempt submitted successfully',
            'attempt' => [
                'attemptId' => $attempt->attemptId,
                'status' => $attempt->status,
                'score' => $attempt->score,
                'percentage' => $attempt->percentage,
                'correctAnswers' => $attempt->correctAnswers,
                'wrongAnswers' => $attempt->wrongAnswers,
                'unansweredQuestions' => $attempt->unansweredQuestions,
                'submittedAt' => $attempt->submittedAt,
            ],
        ]);
    }

    public function result(Request $request, int $attemptId): JsonResponse
    {
        $auth = $this->getAuthenticatedStudent($request);

        $attempt = JambAttempt::with([
            'subject',
            'topic',
            'attemptQuestions.question.topic',
            'attemptQuestions.question.options',
        ])
            ->where('attemptId', $attemptId)
            ->where('studentId', $auth['studentId'])
            ->first();

        if (!$attempt) {
            return response()->json([
                'message' => 'Attempt not found',
            ], 404);
        }

        $this->practiceService->expireAttemptIfNeeded($attempt);
        $attempt->refresh();

        if (!in_array($attempt->status, ['submitted', 'expired'])) {
            return response()->json([
                'message' => 'Result is only available after submission',
            ], 422);
        }

        $topicBreakdown = $attempt->attemptQuestions
            ->groupBy(fn ($item) => optional($item->question->topic)->name ?? 'General')
            ->map(function ($rows, $topicName) {
                $total = $rows->count();
                $correct = $rows->where('isCorrect', true)->count();
                $answered = $rows->where('isAnswered', true)->count();

                return [
                    'topic' => $topicName,
                    'totalQuestions' => $total,
                    'answeredQuestions' => $answered,
                    'correctAnswers' => $correct,
                    'score' => $correct,
                    'percentage' => $total > 0 ? round(($correct / $total) * 100, 2) : 0,
                ];
            })
            ->values();

        return response()->json([
            'result' => [
                'attemptId' => $attempt->attemptId,
                'mode' => $attempt->mode,
                'status' => $attempt->status,
                'subject' => $attempt->subject ? [
                    'subjectId' => $attempt->subject->subjectId,
                    'name' => $attempt->subject->name,
                    'code' => $attempt->subject->code,
                ] : null,
                'topic' => $attempt->topic ? [
                    'topicId' => $attempt->topic->topicId,
                    'name' => $attempt->topic->name,
                ] : null,
                'totalQuestions' => $attempt->totalQuestions,
                'answeredQuestions' => $attempt->answeredQuestions,
                'correctAnswers' => $attempt->correctAnswers,
                'wrongAnswers' => $attempt->wrongAnswers,
                'unansweredQuestions' => $attempt->unansweredQuestions,
                'score' => $attempt->score,
                'percentage' => $attempt->percentage,
                'startedAt' => $attempt->startedAt,
                'submittedAt' => $attempt->submittedAt,
                'topicBreakdown' => $topicBreakdown,
                'review' => $attempt->attemptQuestions->map(function (JambAttemptQuestion $item) {
                    return [
                        'attemptQuestionId' => $item->attemptQuestionId,
                        'questionOrder' => $item->questionOrder,
                        'selectedOption' => $item->selectedOption,
                        'correctOption' => $item->question->correctOption,
                        'isCorrect' => $item->isCorrect,
                        'question' => [
                            'questionId' => $item->question->questionId,
                            'questionText' => $item->question->questionText,
                            'passageText' => $item->question->passageText,
                            'questionImage' => $item->question->questionImage,
                            'explanation' => $item->question->explanation,
                            'topic' => $item->question->topic ? [
                                'topicId' => $item->question->topic->topicId,
                                'name' => $item->question->topic->name,
                            ] : null,
                            'options' => $item->question->options->map(function ($option) {
                                return [
                                    'optionLabel' => $option->optionLabel,
                                    'optionText' => $option->optionText,
                                    'isCorrect' => $option->isCorrect,
                                ];
                            }),
                        ],
                    ];
                }),
            ],
        ]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $auth = $this->getAuthenticatedStudent($request);

        $attempts = JambAttempt::where('studentId', $auth['studentId'])->get();

        $latestAttempts = JambAttempt::with(['subject', 'topic'])
            ->where('studentId', $auth['studentId'])
            ->latest('attemptId')
            ->limit(10)
            ->get();

        return response()->json([
            'summary' => [
                'totalAttempts' => $attempts->count(),
                'completedAttempts' => $attempts->whereIn('status', ['submitted', 'expired'])->count(),
                'inProgressAttempts' => $attempts->where('status', 'in_progress')->count(),
                'averageScorePercentage' => round((float) $attempts->whereIn('status', ['submitted', 'expired'])->avg('percentage'), 2),
            ],
            'recentAttempts' => $latestAttempts->map(function ($attempt) {
                return [
                    'attemptId' => $attempt->attemptId,
                    'status' => $attempt->status,
                    'mode' => $attempt->mode,
                    'subjectName' => optional($attempt->subject)->name,
                    'topicName' => optional($attempt->topic)->name,
                    'score' => $attempt->score,
                    'percentage' => $attempt->percentage,
                    'startedAt' => $attempt->startedAt,
                    'submittedAt' => $attempt->submittedAt,
                ];
            }),
        ]);
    }
}