<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CbtExam;
use App\Models\ExamSection;
use App\Models\ExamSectionQuestion;
use App\Models\Question;
use App\Models\CbtQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CbtExamBuilderController extends Controller
{
    protected function exam(Request $request, int $examId): CbtExam
    {
        $schoolId = $request->header('X-School-ID');

        return CbtExam::query()
            ->where('examId', $examId)
            ->where('schoolId', $schoolId)
            ->firstOrFail();
    }

    protected function section(Request $request, int $sectionId): ExamSection
    {
        $schoolId = $request->header('X-School-ID');

        return ExamSection::query()
            ->where('examSectionId', $sectionId)
            ->where('schoolId', $schoolId)
            ->firstOrFail();
    }

    protected function ensureExamIsEditable(CbtExam $exam): void
    {
        if (!method_exists($exam, 'attempts')) {
            return;
        }

        $hasAttempts = $exam->attempts()
            ->whereIn('status', ['in_progress', 'submitted', 'timed_out'])
            ->exists();

        abort_if(
            $hasAttempts,
            422,
            'This exam is locked because students have already started or completed attempts.'
        );
    }

    protected function recalculateSectionTotalMarks(int $sectionId): void
    {
        $sum = ExamSectionQuestion::query()
            ->where('examSectionId', $sectionId)
            ->sum('mark');

        ExamSection::query()
            ->where('examSectionId', $sectionId)
            ->update(['totalMarks' => $sum]);
    }

    protected function recalculateExamTotalMarks(int $examId): void
    {
        $sum = DB::table('exam_section_questions as esq')
            ->join('exam_sections as es', 'es.examSectionId', '=', 'esq.examSectionId')
            ->where('es.examId', $examId)
            ->sum('esq.mark');

        CbtExam::query()
            ->where('examId', $examId)
            ->update(['totalMarks' => $sum]);
    }

    public function builder(Request $request, int $examId): JsonResponse
    {
        $exam = $this->exam($request, $examId);

        $exam->load([
            'class',
            'subject',
            'sections' => function ($query) {
                $query->with([
                    'questions' => function ($q) {
                        $q->with(['subject', 'topic', 'options'])
                            ->orderBy('exam_section_questions.orderIndex');
                    },
                ])->orderBy('sectionOrder');
            },
        ]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'exam' => [
    'examId' => $exam->examId,
    'schoolId' => $exam->schoolId,
    'classId' => $exam->classId,
    'subjectId' => $exam->subjectId,
    'title' => $exam->title,
    'instructions' => $exam->instructions,
    'durationMinutes' => $exam->durationMinutes,
    'startsAt' => $exam->startsAt,
    'endsAt' => $exam->endsAt,
    'totalMarks' => $exam->totalMarks,
    'isPublished' => (bool) $exam->isPublished,
    'isLocked' => method_exists($exam, 'attempts')
        ? $exam->attempts()
            ->whereIn('status', ['in_progress', 'submitted', 'timed_out'])
            ->exists()
        : false,
    'class' => $exam->class ? [
        'classId' => $exam->class->classId,
        'className' => $exam->class->className,
    ] : null,
    'subject' => $exam->subject ? [
        'subjectId' => $exam->subject->subjectId,
        'subjectName' => $exam->subject->subjectName,
    ] : null,
],
                'sections' => $exam->sections->map(function ($section) {
                    return [
                        'examSectionId' => $section->examSectionId,
                        'title' => $section->title,
                        'instructions' => $section->instructions,
                        'sectionOrder' => $section->sectionOrder,
                        'totalMarks' => (int) $section->totalMarks,
                        'questions' => $section->questions->map(function ($question) {
                            return [
                                'examSectionQuestionId' => $question->pivot->examSectionQuestionId ?? null,
                                'questionId' => $question->questionId,
                                'orderIndex' => $question->pivot->orderIndex ?? null,
                                'questionText' => $question->questionText,
                                'type' => $question->type,
                                'difficulty' => $question->difficulty,
                                'mark' => (int) ($question->pivot->mark ?? 0),
                                'subjectId' => $question->subjectId,
                                'topicId' => $question->topicId,
                                'imageUrl' => $question->imageUrl,
                                'subjectName' => optional($question->subject)->subjectName,
                                'topicName' => optional($question->topic)->topicName,
                                'options' => $question->options->map(function ($option) {
                                    return [
                                        'optionId' => $option->optionId,
                                        'optionText' => $option->optionText,
                                        'isCorrect' => (bool) $option->isCorrect,
                                    ];
                                })->values(),
                            ];
                        })->values(),
                    ];
                })->values(),
            ],
        ]);
    }

    public function createSection(Request $request, int $examId): JsonResponse
    {
        $exam = $this->exam($request, $examId);
        $this->ensureExamIsEditable($exam);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'instructions' => ['nullable', 'string'],
        ]);

        $nextOrder = (int) ExamSection::query()
            ->where('examId', $exam->examId)
            ->max('sectionOrder');

        $section = ExamSection::create([
            'examId' => $exam->examId,
            'schoolId' => $exam->schoolId,
            'title' => $validated['title'],
            'instructions' => $validated['instructions'] ?? null,
            'sectionOrder' => $nextOrder + 1,
            'totalMarks' => 0,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Section created successfully',
            'data' => [
                'examSectionId' => $section->examSectionId,
                'title' => $section->title,
                'instructions' => $section->instructions,
                'sectionOrder' => $section->sectionOrder,
                'totalMarks' => 0,
                'questions' => [],
            ],
        ], 201);
    }

    public function updateSection(Request $request, int $sectionId): JsonResponse
    {
        $section = $this->section($request, $sectionId);
        $this->ensureExamIsEditable($section->exam);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'instructions' => ['nullable', 'string'],
        ]);

        $section->update([
            'title' => $validated['title'],
            'instructions' => array_key_exists('instructions', $validated)
                ? $validated['instructions']
                : $section->instructions,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Section updated successfully',
            'data' => [
                'examSectionId' => $section->examSectionId,
                'title' => $section->title,
                'instructions' => $section->instructions,
                'sectionOrder' => $section->sectionOrder,
                'totalMarks' => (int) $section->totalMarks,
            ],
        ]);
    }

    public function deleteSection(Request $request, int $sectionId): JsonResponse
    {
        $section = $this->section($request, $sectionId);
        $this->ensureExamIsEditable($section->exam);

        $examId = $section->examId;

        if ($section->exam->isPublished) {
    return response()->json([
        'status' => 'error',
        'message' => 'Unpublish the exam before deleting sections.',
    ], 422);
}

        DB::transaction(function () use ($section, $examId) {
            $deletedOrder = $section->sectionOrder;
            $section->delete();

            ExamSection::query()
                ->where('examId', $examId)
                ->where('sectionOrder', '>', $deletedOrder)
                ->decrement('sectionOrder');

            $this->recalculateExamTotalMarks($examId);
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Section deleted successfully',
        ]);
    }

    public function reorderSections(Request $request, int $examId): JsonResponse
    {
        $exam = $this->exam($request, $examId);
        $this->ensureExamIsEditable($exam);

        $validated = $request->validate([
            'sections' => ['required', 'array', 'min:1'],
            'sections.*.examSectionId' => ['required', 'integer'],
            'sections.*.sectionOrder' => ['required', 'integer', 'min:1'],
        ]);

        
        $allowedIds = ExamSection::query()
            ->where('examId', $exam->examId)
            ->pluck('examSectionId')
            ->map(fn ($id) => (int) $id)
            ->all();

        $submittedIds = collect($validated['sections'])
            ->pluck('examSectionId')
            ->map(fn ($id) => (int) $id)
            ->all();

        sort($allowedIds);
        sort($submittedIds);

        if ($allowedIds !== $submittedIds) {
            return response()->json([
                'status' => 'error',
                'message' => 'Section list is invalid for this exam',
            ], 422);
        }

        DB::transaction(function () use ($validated, $exam) {
            foreach ($validated['sections'] as $item) {
                ExamSection::query()
                    ->where('examSectionId', $item['examSectionId'])
                    ->where('examId', $exam->examId)
                    ->update([
                        'sectionOrder' => $item['sectionOrder'],
                    ]);
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Sections reordered successfully',
        ]);
    }

    public function attachQuestions(Request $request, int $sectionId): JsonResponse
    {
        $section = $this->section($request, $sectionId);
        $this->ensureExamIsEditable($section->exam);

        $validated = $request->validate([
            'questionIds' => ['required', 'array', 'min:1'],
            'questionIds.*' => ['required', 'integer'],
        ]);

  

        $questionIds = collect($validated['questionIds'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $questions = CbtQuestion::query()
            ->whereIn('questionId', $questionIds)
            ->where('subjectId', $section->exam->subjectId)
            ->get();

        if ($questions->count() !== count($questionIds)) {
            return response()->json([
                'status' => 'error',
                'message' => 'One or more selected questions are invalid for this exam subject',
            ], 422);
        }

        DB::transaction(function () use ($section, $questionIds, $questions) {
            $currentMax = (int) ExamSectionQuestion::query()
                ->where('examSectionId', $section->examSectionId)
                ->max('orderIndex');

            foreach ($questionIds as $index => $questionId) {
                $question = $questions->firstWhere('questionId', $questionId);

                ExamSectionQuestion::firstOrCreate(
                    [
                        'examSectionId' => $section->examSectionId,
                        'questionId' => $questionId,
                    ],
                    [
                        'orderIndex' => $currentMax + $index + 1,
                        'mark' => (int) ($question->mark ?? 1),
                    ]
                );
            }

            $this->recalculateSectionTotalMarks($section->examSectionId);
            $this->recalculateExamTotalMarks($section->examId);
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Questions attached successfully',
        ]);
    }

    public function removeQuestion(Request $request, int $sectionId, int $questionId): JsonResponse
    {
        $section = $this->section($request, $sectionId);
        $this->ensureExamIsEditable($section->exam);

        if ($section->exam->isPublished) {
    return response()->json([
        'status' => 'error',
        'message' => 'Unpublish the exam before deleting sections.',
    ], 422);
}

        DB::transaction(function () use ($section, $questionId) {
            $row = ExamSectionQuestion::query()
                ->where('examSectionId', $section->examSectionId)
                ->where('questionId', $questionId)
                ->firstOrFail();

            $deletedOrder = $row->orderIndex;
            $row->delete();

            ExamSectionQuestion::query()
                ->where('examSectionId', $section->examSectionId)
                ->where('orderIndex', '>', $deletedOrder)
                ->decrement('orderIndex');

            $this->recalculateSectionTotalMarks($section->examSectionId);
            $this->recalculateExamTotalMarks($section->examId);
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Question removed successfully',
        ]);
    }

    public function reorderQuestions(Request $request, int $sectionId): JsonResponse
    {
        $section = $this->section($request, $sectionId);
        $this->ensureExamIsEditable($section->exam);

        $validated = $request->validate([
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.questionId' => ['required', 'integer'],
            'questions.*.orderIndex' => ['required', 'integer', 'min:1'],
        ]);

        $allowedIds = ExamSectionQuestion::query()
            ->where('examSectionId', $section->examSectionId)
            ->pluck('questionId')
            ->map(fn ($id) => (int) $id)
            ->all();

        $submittedIds = collect($validated['questions'])
            ->pluck('questionId')
            ->map(fn ($id) => (int) $id)
            ->all();

        sort($allowedIds);
        sort($submittedIds);

        if ($allowedIds !== $submittedIds) {
            return response()->json([
                'status' => 'error',
                'message' => 'Question list is invalid for this section',
            ], 422);
        }

        DB::transaction(function () use ($validated, $section) {
            foreach ($validated['questions'] as $item) {
                ExamSectionQuestion::query()
                    ->where('examSectionId', $section->examSectionId)
                    ->where('questionId', $item['questionId'])
                    ->update([
                        'orderIndex' => $item['orderIndex'],
                    ]);
            }

            $this->recalculateSectionTotalMarks($section->examSectionId);
            $this->recalculateExamTotalMarks($section->examId);
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Questions reordered successfully',
        ]);
    }

    public function updateQuestionMark(
        Request $request,
        int $sectionId,
        int $questionId
    ): JsonResponse {
        $section = $this->section($request, $sectionId);
        $this->ensureExamIsEditable($section->exam);

        $validated = $request->validate([
            'mark' => ['required', 'numeric', 'min:0'],
        ]);

        $row = ExamSectionQuestion::query()
            ->where('examSectionId', $section->examSectionId)
            ->where('questionId', $questionId)
            ->firstOrFail();

        $row->update([
            'mark' => $validated['mark'],
        ]);

        $this->recalculateSectionTotalMarks($section->examSectionId);
        $this->recalculateExamTotalMarks($section->examId);

        return response()->json([
            'status' => 'success',
            'message' => 'Question mark updated successfully',
            'data' => [
                'examSectionQuestionId' => $row->examSectionQuestionId,
                'examSectionId' => $row->examSectionId,
                'questionId' => $row->questionId,
                'mark' => (float) $row->mark,
            ],
        ]);
    }

    public function moveQuestionToSection(
        Request $request,
        int $sectionId,
        int $questionId
    ): JsonResponse {
        $fromSection = $this->section($request, $sectionId);
        $this->ensureExamIsEditable($fromSection->exam);

        $validated = $request->validate([
            'toSectionId' => ['required', 'integer'],
        ]);

        $toSection = $this->section($request, (int) $validated['toSectionId']);

        if ((int) $fromSection->examId !== (int) $toSection->examId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot move question to a section from another exam',
            ], 422);
        }

        if ((int) $fromSection->examSectionId === (int) $toSection->examSectionId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Source and destination sections must be different',
            ], 422);
        }

        DB::transaction(function () use ($fromSection, $toSection, $questionId) {
            $sourceRow = ExamSectionQuestion::query()
                ->where('examSectionId', $fromSection->examSectionId)
                ->where('questionId', $questionId)
                ->firstOrFail();

            $oldOrder = $sourceRow->orderIndex;
            $mark = $sourceRow->mark;

            $alreadyExistsInDestination = ExamSectionQuestion::query()
                ->where('examSectionId', $toSection->examSectionId)
                ->where('questionId', $questionId)
                ->exists();

            if ($alreadyExistsInDestination) {
                abort(422, 'This question already exists in the target section.');
            }

            $sourceRow->delete();

            ExamSectionQuestion::query()
                ->where('examSectionId', $fromSection->examSectionId)
                ->where('orderIndex', '>', $oldOrder)
                ->decrement('orderIndex');

            $nextOrder = (int) ExamSectionQuestion::query()
                ->where('examSectionId', $toSection->examSectionId)
                ->max('orderIndex');

            ExamSectionQuestion::create([
                'examSectionId' => $toSection->examSectionId,
                'questionId' => $questionId,
                'orderIndex' => $nextOrder + 1,
                'mark' => $mark,
            ]);

            $this->recalculateSectionTotalMarks($fromSection->examSectionId);
            $this->recalculateSectionTotalMarks($toSection->examSectionId);
            $this->recalculateExamTotalMarks($fromSection->examId);
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Question moved successfully',
        ]);
    }
}