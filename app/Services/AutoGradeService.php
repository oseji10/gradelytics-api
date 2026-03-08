<?php
use App\Models\User;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;

class AutoGradeExamService
{
    public function gradeAttempt(ExamAttempt $attempt): array
    {
        $attempt->load(['answers', 'questions.question.options']);

        $score = 0;
        $maxScore = 0;

        foreach ($attempt->questions as $attemptQuestion) {
            $question = $attemptQuestion->question;
            $mark = $attemptQuestion->mark ?? 1;
            $maxScore += $mark;

            $answer = $attempt->answers
                ->firstWhere('questionId', $question->questionId);

            if (!$answer) {
                continue;
            }

            if ($question->type === 'single_choice') {
                $correctOptionIds = $question->options
                    ->where('isCorrect', true)
                    ->pluck('optionId')
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all();

                $selected = collect($answer->selectedOptionIds ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all();

                sort($correctOptionIds);
                sort($selected);

                $isCorrect = $selected === $correctOptionIds;

                $answer->isCorrect = $isCorrect;
                $answer->awardedScore = $isCorrect ? $mark : 0;
                $answer->save();

                if ($isCorrect) {
                    $score += $mark;
                }
            }

            if ($question->type === 'multi_choice') {
                $correctOptionIds = $question->options
                    ->where('isCorrect', true)
                    ->pluck('optionId')
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all();

                $selected = collect($answer->selectedOptionIds ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all();

                sort($correctOptionIds);
                sort($selected);

                $isCorrect = $selected === $correctOptionIds;

                $answer->isCorrect = $isCorrect;
                $answer->awardedScore = $isCorrect ? $mark : 0;
                $answer->save();

                if ($isCorrect) {
                    $score += $mark;
                }
            }

            if ($question->type === 'theory') {
                $answer->isCorrect = null;
                $answer->awardedScore = 0;
                $answer->save();
            }
        }

        $percentage = $maxScore > 0 ? round(($score / $maxScore) * 100, 2) : 0;

        $attempt->score = $score;
        $attempt->maxScore = $maxScore;
        $attempt->percentage = $percentage;
        $attempt->save();

        return [
            'score' => $score,
            'maxScore' => $maxScore,
            'percentage' => $percentage,
        ];
    }
}