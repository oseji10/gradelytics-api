<?php

namespace App\Http\Controllers;

use App\Models\CbtExam;
use App\Models\CbtQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CbtExamBuilderController extends Controller
{
    private function schoolId(Request $request): int
    {
        return (int) ($request->header('X-School-ID') ?? $request->user()->schoolId);
    }

    private function exam(Request $request, int $examId): CbtExam
    {
        $schoolId = $this->schoolId($request);

        return CbtExam::where('schoolId', $schoolId)
            ->where('examId', $examId)
            ->firstOrFail();
    }

    public function list(Request $request, int $examId): JsonResponse
    {
        $exam = $this->exam($request, $examId);

        $exam->load(['questions.options', 'questions.subject', 'questions.topic']);

        return response()->json([
            'status' => 'success',
            'data' => $exam,
        ]);
    }

    public function attach(Request $request, int $examId): JsonResponse
    {
        $exam = $this->exam($request, $examId);
        $schoolId = $this->schoolId($request);

        $validated = $request->validate([
            'questionIds' => 'required|array|min:1',
            'questionIds.*' => 'required|integer',
        ]);

        $questions = CbtQuestion::where('schoolId', $schoolId)
            ->whereIn('questionId', $validated['questionIds'])
            ->get();

        if ($questions->count() !== count($validated['questionIds'])) {
            return response()->json(['status' => 'error', 'message' => 'Some questions not found'], 404);
        }

        // ✅ IMPORTANT: since questions have no classId, enforce subject match only
        $bad = $questions->first(fn($q) => (int)$q->subjectId !== (int)$exam->subjectId);
        if ($bad) {
            return response()->json([
                'status' => 'error',
                'message' => 'All selected questions must match the exam subject.',
            ], 422);
        }

        DB::transaction(function () use ($exam, $questions) {
            $currentMax = (int) DB::table('cbt_exam_questions')
                ->where('examId', $exam->examId)
                ->max('orderIndex');

            foreach ($questions as $i => $q) {
                $exists = DB::table('cbt_exam_questions')
                    ->where('examId', $exam->examId)
                    ->where('questionId', $q->questionId)
                    ->exists();

                if ($exists) continue;

                DB::table('cbt_exam_questions')->insert([
                    'examId' => $exam->examId,
                    'questionId' => $q->questionId,
                    'orderIndex' => $currentMax + $i + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // recompute totalMarks from question.mark
            $total = DB::table('cbt_exam_questions')
                ->join('cbt_questions', 'cbt_questions.questionId', '=', 'cbt_exam_questions.questionId')
                ->where('cbt_exam_questions.examId', $exam->examId)
                ->sum('cbt_questions.mark');

            $exam->update(['totalMarks' => $total]);
        });

        return response()->json(['status' => 'success', 'message' => 'Questions attached']);
    }

    public function detach(Request $request, int $examId, int $questionId): JsonResponse
    {
        $exam = $this->exam($request, $examId);

        DB::transaction(function () use ($exam, $questionId) {
            DB::table('cbt_exam_questions')
                ->where('examId', $exam->examId)
                ->where('questionId', $questionId)
                ->delete();

            $total = DB::table('cbt_exam_questions')
                ->join('cbt_questions', 'cbt_questions.questionId', '=', 'cbt_exam_questions.questionId')
                ->where('cbt_exam_questions.examId', $exam->examId)
                ->sum('cbt_questions.mark');

            $exam->update(['totalMarks' => $total]);
        });

        return response()->json(['status' => 'success', 'message' => 'Question removed']);
    }

    public function reorder(Request $request, int $examId): JsonResponse
    {
        $exam = $this->exam($request, $examId);

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.questionId' => 'required|integer',
            'items.*.orderIndex' => 'required|integer|min:1',
        ]);

        DB::transaction(function () use ($exam, $validated) {
            foreach ($validated['items'] as $item) {
                DB::table('cbt_exam_questions')
                    ->where('examId', $exam->examId)
                    ->where('questionId', (int)$item['questionId'])
                    ->update([
                        'orderIndex' => (int)$item['orderIndex'],
                        'updated_at' => now(),
                    ]);
            }
        });

        return response()->json(['status' => 'success', 'message' => 'Reordered']);
    }
}