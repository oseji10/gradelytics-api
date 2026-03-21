<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\JambQuestion;
use App\Models\JambQuestionOption;
use App\Models\JambSubject;
use App\Models\JambTopic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminJambController extends Controller
{
    public function subjects(): JsonResponse
    {
        return response()->json([
            'subjects' => JambSubject::orderBy('subjectName')->get(),
        ]);
    }

    public function storeSubject(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subjectName' => ['required', 'string', 'max:120', 'unique:jamb_subjects,subjectName'],
            'subjectCode' => ['nullable', 'string', 'max:30', 'unique:jamb_subjects,subjectCode'],
            'isActive' => ['nullable', 'boolean'],
        ]);

        $subject = JambSubject::create([
            'subjectName' => $validated['subjectName'],
            'subjectCode' => $validated['subjectCode'] ?? null,
            'isActive' => $validated['isActive'] ?? true,
        ]);

        return response()->json([
            'message' => 'Subject created successfully',
            'subject' => $subject,
        ], 201);
    }

    public function topics(Request $request): JsonResponse
    {
        $query = JambTopic::with('subject');

        if ($request->filled('subjectId')) {
            $query->where('subjectId', $request->integer('subjectId'));
        }

        return response()->json([
            'topics' => $query->orderBy('topicName')->get(),
        ]);
    }

    public function storeTopic(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subjectId' => ['required', 'integer', 'exists:jamb_subjects,subjectId'],
            'topicName' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'isActive' => ['nullable', 'boolean'],
        ]);

        $exists = JambTopic::where('subjectId', $validated['subjectId'])
            ->where('topicName', $validated['topicName'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'This topic already exists under the selected subject',
            ], 422);
        }

        $topic = JambTopic::create([
            'subjectId' => $validated['subjectId'],
            'topicName' => $validated['topicName'],
            'description' => $validated['description'] ?? null,
            'isActive' => $validated['isActive'] ?? true,
        ]);

        return response()->json([
            'message' => 'Topic created successfully',
            'topic' => $topic,
        ], 201);
    }

    public function questions(Request $request): JsonResponse
    {
        $query = JambQuestion::with(['subject', 'topic', 'options']);

        if ($request->filled('subjectId')) {
            $query->where('subjectId', $request->integer('subjectId'));
        }

        if ($request->filled('topicId')) {
            $query->where('topicId', $request->integer('topicId'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('difficulty')) {
            $query->where('difficulty', $request->string('difficulty'));
        }

        if ($request->filled('year')) {
            $query->where('year', $request->integer('year'));
        }

        $questions = $query->latest('questionId')->paginate(20);

        return response()->json($questions);
    }

    public function storeQuestion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subjectId' => ['required', 'integer', 'exists:jamb_subjects,subjectId'],
            'topicId' => ['nullable', 'integer', 'exists:jamb_topics,topicId'],
            'year' => ['nullable', 'integer', 'min:1978', 'max:' . now()->year],
            'questionText' => ['required', 'string'],
            'questionImage' => ['nullable', 'string'],
            'passageText' => ['nullable', 'string'],
            'correctOption' => ['required', Rule::in(['A', 'B', 'C', 'D'])],
            'explanation' => ['nullable', 'string'],
            'difficulty' => ['nullable', Rule::in(['easy', 'medium', 'hard'])],
            'status' => ['nullable', Rule::in(['draft', 'published', 'archived'])],

            'options' => ['required', 'array', 'size:4'],
            'options.A' => ['required', 'string'],
            'options.B' => ['required', 'string'],
            'options.C' => ['required', 'string'],
            'options.D' => ['required', 'string'],
        ]);

        if (!empty($validated['topicId'])) {
            $topic = JambTopic::where('topicId', $validated['topicId'])
                ->where('subjectId', $validated['subjectId'])
                ->first();

            if (!$topic) {
                return response()->json([
                    'message' => 'Selected topic does not belong to the selected subject',
                ], 422);
            }
        }

        $question = DB::transaction(function () use ($validated) {
            $question = JambQuestion::create([
                'subjectId' => $validated['subjectId'],
                'topicId' => $validated['topicId'] ?? null,
                'year' => $validated['year'] ?? null,
                'questionText' => $validated['questionText'],
                'questionImage' => $validated['questionImage'] ?? null,
                'passageText' => $validated['passageText'] ?? null,
                'optionType' => 'single_choice',
                'correctOption' => $validated['correctOption'],
                'explanation' => $validated['explanation'] ?? null,
                'difficulty' => $validated['difficulty'] ?? 'medium',
                'status' => $validated['status'] ?? 'draft',
            ]);

            foreach (['A', 'B', 'C', 'D'] as $label) {
                JambQuestionOption::create([
                    'questionId' => $question->questionId,
                    'optionLabel' => $label,
                    'optionText' => $validated['options'][$label],
                    'isCorrect' => $validated['correctOption'] === $label,
                ]);
            }

            return $question->load(['subject', 'topic', 'options']);
        });

        return response()->json([
            'message' => 'Question created successfully',
            'question' => $question,
        ], 201);
    }

    public function updateQuestion(Request $request, int $questionId): JsonResponse
    {
        $question = JambQuestion::with('options')->find($questionId);

        if (!$question) {
            return response()->json([
                'message' => 'Question not found',
            ], 404);
        }

        $validated = $request->validate([
            'subjectId' => ['required', 'integer', 'exists:jamb_subjects,subjectId'],
            'topicId' => ['nullable', 'integer', 'exists:jamb_topics,topicId'],
            'year' => ['nullable', 'integer', 'min:1978', 'max:' . now()->year],
            'questionText' => ['required', 'string'],
            'questionImage' => ['nullable', 'string'],
            'passageText' => ['nullable', 'string'],
            'correctOption' => ['required', Rule::in(['A', 'B', 'C', 'D'])],
            'explanation' => ['nullable', 'string'],
            'difficulty' => ['nullable', Rule::in(['easy', 'medium', 'hard'])],
            'status' => ['nullable', Rule::in(['draft', 'published', 'archived'])],

            'options' => ['required', 'array', 'size:4'],
            'options.A' => ['required', 'string'],
            'options.B' => ['required', 'string'],
            'options.C' => ['required', 'string'],
            'options.D' => ['required', 'string'],
        ]);

        if (!empty($validated['topicId'])) {
            $topic = JambTopic::where('topicId', $validated['topicId'])
                ->where('subjectId', $validated['subjectId'])
                ->first();

            if (!$topic) {
                return response()->json([
                    'message' => 'Selected topic does not belong to the selected subject',
                ], 422);
            }
        }

        DB::transaction(function () use ($question, $validated) {
            $question->update([
                'subjectId' => $validated['subjectId'],
                'topicId' => $validated['topicId'] ?? null,
                'year' => $validated['year'] ?? null,
                'questionText' => $validated['questionText'],
                'questionImage' => $validated['questionImage'] ?? null,
                'passageText' => $validated['passageText'] ?? null,
                'correctOption' => $validated['correctOption'],
                'explanation' => $validated['explanation'] ?? null,
                'difficulty' => $validated['difficulty'] ?? 'medium',
                'status' => $validated['status'] ?? 'draft',
            ]);

            foreach (['A', 'B', 'C', 'D'] as $label) {
                $option = $question->options->firstWhere('optionLabel', $label);

                if ($option) {
                    $option->update([
                        'optionText' => $validated['options'][$label],
                        'isCorrect' => $validated['correctOption'] === $label,
                    ]);
                }
            }
        });

        return response()->json([
            'message' => 'Question updated successfully',
            'question' => $question->fresh(['subject', 'topic', 'options']),
        ]);
    }

    public function deleteQuestion(int $questionId): JsonResponse
    {
        $question = JambQuestion::find($questionId);

        if (!$question) {
            return response()->json([
                'message' => 'Question not found',
            ], 404);
        }

        $question->delete();

        return response()->json([
            'message' => 'Question deleted successfully',
        ]);
    }



    public function showQuestion(int $questionId): JsonResponse
{
    $question = JambQuestion::with(['subject', 'topic', 'options'])->find($questionId);

    if (!$question) {
        return response()->json([
            'message' => 'Question not found',
        ], 404);
    }

    return response()->json([
        'question' => $question,
    ]);
}
}