<?php

namespace App\Http\Controllers;

use App\Models\CbtQuestion;
use App\Models\CbtQuestionOption;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CbtQuestionController extends Controller
{
    private function schoolId(Request $request): int
    {
        // Use your real source of schoolId (header or token)
        return (int) ($request->header('X-School-ID') ?? $request->user()->schoolId);
    }

    public function index(Request $request): JsonResponse
    {
        $schoolId = $this->schoolId($request);

        $q = CbtQuestion::where('schoolId', $schoolId)
            ->with(['options', 'subject', 'topic'])
            ->orderByDesc('questionId');

        if ($request->filled('subjectId')) $q->where('subjectId', (int)$request->subjectId);
        if ($request->filled('topicId')) $q->where('topicId', (int)$request->topicId);
        if ($request->filled('difficulty')) $q->where('difficulty', $request->difficulty);
        if ($request->filled('type')) $q->where('type', $request->type);

        if ($request->filled('search')) {
            $s = trim($request->search);
            $q->where('questionText', 'like', "%{$s}%");
        }

        $perPage = (int) $request->query('per_page', 15);

        return response()->json([
            'status' => 'success',
            'data' => $q->paginate($perPage),
        ]);
    }

    public function show(Request $request, int $questionId): JsonResponse
    {
        $schoolId = $this->schoolId($request);

        $question = CbtQuestion::where('schoolId', $schoolId)
            ->where('questionId', $questionId)
            ->with(['options', 'subject', 'topic'])
            ->firstOrFail();

        return response()->json(['status' => 'success', 'data' => $question]);
    }

    public function store(Request $request): JsonResponse
    {
        $schoolId = $this->schoolId($request);
        $userId = (int) $request->user()->id;

        $validated = $request->validate([
            'subjectId' => 'required|integer|exists:subjects,subjectId',
            'classId' => 'nullable|integer|exists:classes,classId',
            'topicId' => 'nullable|integer|exists:topics,topicId',
            'difficulty' => 'nullable|string|in:easy,medium,hard',
            'type' => 'required|string|in:single_choice,multi_choice,theory',
            'questionText' => 'required|string|min:3',
            'imageUrl' => 'nullable|string',
            'mark' => 'nullable|integer|min:1',

            // for choice questions
            'options' => 'nullable|array|min:2',
            'options.*.optionLabel' => 'nullable|string|max:5',
            'options.*.optionText' => 'required_with:options|string|min:1',
            'options.*.isCorrect' => 'required_with:options|boolean',
        ]);

        // enforce options rules
        if (in_array($validated['type'], ['single_choice', 'multi_choice'])) {
            if (empty($validated['options']) || count($validated['options']) < 2) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Choice questions must have at least 2 options.',
                ], 422);
            }

            $correctCount = collect($validated['options'])->where('isCorrect', true)->count();

            if ($validated['type'] === 'single_choice' && $correctCount !== 1) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Single choice must have exactly 1 correct option.',
                ], 422);
            }

            if ($validated['type'] === 'multi_choice' && $correctCount < 1) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Multi choice must have at least 1 correct option.',
                ], 422);
            }
        }

        $question = DB::transaction(function () use ($validated, $schoolId, $userId) {
            $q = CbtQuestion::create([
                'schoolId' => $schoolId,
                'subjectId' => (int) $validated['subjectId'],
                'topicId' => $validated['topicId'] ?? null,
                'difficulty' => $validated['difficulty'] ?? 'medium',
                'type' => $validated['type'],
                'questionText' => $validated['questionText'],
                'imageUrl' => $validated['imageUrl'] ?? null,
                'mark' => $validated['mark'] ?? 1,
                'createdBy' => $userId,
                'classId' => $validated['classId'] ?? null,
            ]);

            if (!empty($validated['options'])) {
                foreach ($validated['options'] as $i => $opt) {
                    CbtQuestionOption::create([
                        'questionId' => $q->questionId,
                        'optionLabel' => $opt['optionLabel'] ?? chr(65 + $i), // A,B,C...
                        'optionText' => $opt['optionText'],
                        'isCorrect' => (bool) $opt['isCorrect'],
                    ]);
                }
            }

            return $q->load(['options', 'subject', 'topic']);
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Question created',
            'data' => $question,
        ], 201);
    }

    public function update(Request $request, int $questionId): JsonResponse
    {
        $schoolId = $this->schoolId($request);

        $question = CbtQuestion::where('schoolId', $schoolId)
            ->where('questionId', $questionId)
            ->firstOrFail();

        $validated = $request->validate([
            'subjectId' => 'sometimes|integer|exists:subjects,subjectId',
            'topicId' => 'nullable|integer|exists:topics,topicId',
            'difficulty' => 'sometimes|string|in:easy,medium,hard',
            'type' => 'sometimes|string|in:single_choice,multi_choice,theory',
            'questionText' => 'sometimes|string|min:3',
            'imageUrl' => 'nullable|string',
            'mark' => 'sometimes|integer|min:1',
            'classId' => 'nullable|integer|exists:classes,classId',

            // replace-all options if provided
            'options' => 'sometimes|array|min:2',
            'options.*.optionLabel' => 'nullable|string|max:5',
            'options.*.optionText' => 'required_with:options|string|min:1',
            'options.*.isCorrect' => 'required_with:options|boolean',
        ]);

        $newType = $validated['type'] ?? $question->type;

        if (in_array($newType, ['single_choice', 'multi_choice']) && array_key_exists('options', $validated)) {
            $correctCount = collect($validated['options'])->where('isCorrect', true)->count();

            if ($newType === 'single_choice' && $correctCount !== 1) {
                return response()->json(['status' => 'error', 'message' => 'Single choice must have exactly 1 correct option.'], 422);
            }
            if ($newType === 'multi_choice' && $correctCount < 1) {
                return response()->json(['status' => 'error', 'message' => 'Multi choice must have at least 1 correct option.'], 422);
            }
        }

        DB::transaction(function () use ($question, $validated) {
            $question->fill(collect($validated)->except('options')->toArray())->save();

            if (array_key_exists('options', $validated)) {
                $question->options()->delete();

                foreach ($validated['options'] as $i => $opt) {
                    CbtQuestionOption::create([
                        'questionId' => $question->questionId,
                        'optionLabel' => $opt['optionLabel'] ?? chr(65 + $i),
                        'optionText' => $opt['optionText'],
                        'isCorrect' => (bool) $opt['isCorrect'],
                    ]);
                }
            }

            // if type becomes theory, you may want to clear options
            if (($validated['type'] ?? null) === 'theory') {
                $question->options()->delete();
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Question updated',
            'data' => $question->fresh()->load(['options', 'subject', 'topic']),
        ]);
    }

    public function destroy(Request $request, int $questionId): JsonResponse
    {
        $schoolId = $this->schoolId($request);

        $question = CbtQuestion::where('schoolId', $schoolId)
            ->where('questionId', $questionId)
            ->firstOrFail();

        $question->delete(); // cascades to options
        return response()->json(['status' => 'success', 'message' => 'Question deleted']);
    }
}