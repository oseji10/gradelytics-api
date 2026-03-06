<?php
// app/Http/Controllers/CbtExamController.php

namespace App\Http\Controllers;

use App\Models\CbtExam;
use App\Models\AcademicYear;
use App\Models\Term;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CbtExamController extends Controller
{
    /**
     * Admin/Teacher: list exams for a school (with optional filters)
     */
    public function index(Request $request): JsonResponse
    {
        $schoolId = (int) $request->header('X-School-ID');

        $query = CbtExam::query()
            ->forSchool($schoolId)
            ->with(['subject', 'class', 'term', 'academicYear'])
            ->orderByDesc('examId');

        if ($request->filled('classId')) {
            $query->where('classId', (int) $request->classId);
        }
        if ($request->filled('subjectId')) {
            $query->where('subjectId', (int) $request->subjectId);
        }
        if ($request->filled('termId')) {
            $query->where('termId', (int) $request->termId);
        }
        if ($request->filled('academicYearId')) {
            $query->where('academicYearId', (int) $request->academicYearId);
        }
        if ($request->filled('published')) {
            $query->where('isPublished', filter_var($request->published, FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = (int) ($request->query('per_page', 15));

        return response()->json([
            'status' => 'success',
            'data' => $query->paginate($perPage),
        ]);
    }

    /**
     * Admin/Teacher: create exam
     */
    public function store(Request $request): JsonResponse
    {

        $schoolId = (int) $request->header('X-School-ID');
        $userId = optional($request->user())->id ?? optional($request->user())->userId ?? null;
        
        $academicYear = AcademicYear::where('schoolId', $schoolId)
            ->where('isActive', true)
            ->firstOrFail();

        $term = Term::where('academicYearId', $academicYear->academicYearId)
            ->where('isActive', true)
            ->firstOrFail();

        $academicYear = AcademicYear::where('schoolId', $schoolId)
        ->where('isActive', true)
        ->first();

    if (!$academicYear) {
        return response()->json([
            'message' => 'No active academic year found'
        ], 404);
    }

    // Get active term
    $term = Term::where('schoolId', $schoolId)
        ->where('academicYearId', $academicYear->academicYearId)
        ->where('isActive', true)
        ->first();

    if (!$term) {
        return response()->json([
            'message' => 'No active term found'
        ], 404);
    }

        $validated = $request->validate([
            // 'academicYearId' => ['required', 'integer'],
            // 'termId' => ['required', 'integer'],
            'classId' => ['required', 'integer'],
            'subjectId' => ['required', 'integer'],

            'title' => ['required', 'string', 'max:255'],
            'instructions' => ['nullable', 'string'],

            'durationMinutes' => ['required', 'integer', 'min:1', 'max:600'],
            'startsAt' => ['nullable', 'date'],
            'endsAt' => ['nullable', 'date', 'after_or_equal:startsAt'],

            'shuffleQuestions' => ['sometimes', 'boolean'],
            'shuffleOptions' => ['sometimes', 'boolean'],
            'attemptLimit' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'showResultImmediately' => ['sometimes', 'boolean'],
            'isPublished' => ['sometimes', 'boolean'],

            // optional grading linkage
            'scoreMode' => ['sometimes', Rule::in(['practice', 'graded'])],
            'resultComponent' => ['sometimes', Rule::in(['none', 'ca', 'exam', 'custom'])],
            'componentMaxScore' => ['sometimes', 'numeric', 'min:0'],
        ]);

        $exam = CbtExam::create(array_merge($validated, [
            'schoolId' => $schoolId,
            'totalMarks' => 0, // will be recalculated after attaching questions
            'isPublished' => (bool) ($validated['isPublished'] ?? false),
            'shuffleQuestions' => (bool) ($validated['shuffleQuestions'] ?? false),
            'shuffleOptions' => (bool) ($validated['shuffleOptions'] ?? false),
            'attemptLimit' => (int) ($validated['attemptLimit'] ?? 1),
            'showResultImmediately' => (bool) ($validated['showResultImmediately'] ?? false),
            'scoreMode' => $validated['scoreMode'] ?? 'practice',
            'resultComponent' => $validated['resultComponent'] ?? 'none',
            'componentMaxScore' => $validated['componentMaxScore'] ?? null,
            'createdBy' => $userId,
            'termId' => $term->termId,
            'academicYearId' => $academicYear->academicYearId,
            'startsAt' => $validated['startsAt'] ?? null,
            'endsAt' => $validated['endsAt'] ?? null,
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Exam created',
            'data' => $exam->load(['subject', 'class', 'term', 'academicYear']),
        ], 201);
    }

    /**
     * Admin/Teacher: show exam (includes questions)
     */
    public function show(Request $request, int $examId): JsonResponse
    {
        $schoolId = (int) $request->header('X-School-ID');

        $exam = CbtExam::query()
            ->forSchool($schoolId)
            ->with([
                'subject',
                'class',
                'term',
                'academicYear',
                'questions.options', // assumes CbtQuestion has options() relationship
            ])
            ->where('examId', $examId)
            ->firstOrFail();

        return response()->json([
            'status' => 'success',
            'data' => $exam,
        ]);
    }

    /**
     * Admin/Teacher: update exam (does not attach questions here)
     */
    public function update(Request $request, int $examId): JsonResponse
    {
        $schoolId = (int) $request->header('X-School-ID');

        $exam = CbtExam::query()->forSchool($schoolId)->where('examId', $examId)->firstOrFail();

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'instructions' => ['sometimes', 'nullable', 'string'],

            'durationMinutes' => ['sometimes', 'integer', 'min:1', 'max:600'],
            'startsAt' => ['sometimes', 'nullable', 'date'],
            'endsAt' => ['sometimes', 'nullable', 'date', 'after_or_equal:startsAt'],

            'shuffleQuestions' => ['sometimes', 'boolean'],
            'shuffleOptions' => ['sometimes', 'boolean'],
            'attemptLimit' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'showResultImmediately' => ['sometimes', 'boolean'],

            'scoreMode' => ['sometimes', Rule::in(['practice', 'graded'])],
            'resultComponent' => ['sometimes', Rule::in(['none', 'ca', 'exam', 'custom'])],
            'componentMaxScore' => ['sometimes', 'numeric', 'min:0'],
        ]);

        // Safety: optionally prevent changes if already published or has attempts
        // if ($exam->isPublished) { ... }

        $exam->fill($validated);
        $exam->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Exam updated',
            'data' => $exam->fresh()->load(['subject', 'class', 'term', 'academicYear']),
        ]);
    }

    /**
     * Admin/Teacher: publish/unpublish exam
     */
    public function setPublish(Request $request, int $examId): JsonResponse
    {
        $schoolId = (int) $request->header('X-School-ID');

        $validated = $request->validate([
            'isPublished' => ['required', 'boolean'],
        ]);

        $exam = CbtExam::query()->forSchool($schoolId)->where('examId', $examId)->firstOrFail();

        // Optional: ensure exam has questions before publishing
        if ($validated['isPublished'] === true) {
            $hasQuestions = DB::table('exam_questions')->where('examId', $exam->examId)->exists();
            if (!$hasQuestions) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot publish exam without questions attached.',
                ], 422);
            }
        }

        $exam->isPublished = (bool) $validated['isPublished'];
        $exam->save();

        return response()->json([
            'status' => 'success',
            'message' => $exam->isPublished ? 'Exam published' : 'Exam unpublished',
            'data' => $exam,
        ]);
    }

    /**
     * Admin/Teacher: attach questions to exam (replaces existing set)
     * payload: { "questionIds": [1,2,3], "autoOrder": true }
     */
    public function attachQuestions(Request $request, int $examId): JsonResponse
    {
        $schoolId = (int) $request->header('X-School-ID');

        $validated = $request->validate([
            'questionIds' => ['required', 'array', 'min:1'],
            'questionIds.*' => ['integer'],
            'autoOrder' => ['sometimes', 'boolean'],
        ]);

        $exam = CbtExam::query()->forSchool($schoolId)->where('examId', $examId)->firstOrFail();

        $questionIds = array_values(array_unique($validated['questionIds']));
        $autoOrder = (bool) ($validated['autoOrder'] ?? true);

        DB::transaction(function () use ($exam, $questionIds, $autoOrder) {
            DB::table('exam_questions')->where('examId', $exam->examId)->delete();

            $rows = [];
            foreach ($questionIds as $i => $qid) {
                $rows[] = [
                    'examId' => $exam->examId,
                    'questionId' => $qid,
                    'orderIndex' => $autoOrder ? ($i + 1) : ($i + 1),
                ];
            }
            DB::table('exam_questions')->insert($rows);

            // Recalculate totalMarks (assumes questions table has `mark`)
            $totalMarks = DB::table('questions')->whereIn('questionId', $questionIds)->sum('mark');
            DB::table('exams')->where('examId', $exam->examId)->update(['totalMarks' => $totalMarks]);
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Questions attached',
            'data' => $exam->fresh()->load(['questions.options']),
        ]);
    }

    /**
     * Admin/Teacher: delete exam
     */
    public function destroy(Request $request, int $examId): JsonResponse
    {
        $schoolId = (int) $request->header('X-School-ID');

        $exam = CbtExam::query()->forSchool($schoolId)->where('examId', $examId)->firstOrFail();

        DB::transaction(function () use ($exam) {
            DB::table('exam_questions')->where('examId', $exam->examId)->delete();
            // optionally also delete attempts/answers if you want
            $exam->delete();
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Exam deleted',
        ]);
    }
}