<?php

namespace App\Services;

use App\Models\JambAttempt;
use App\Models\JambAttemptQuestion;
use App\Models\JambQuestion;
use App\Models\JambTopic;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JambPracticeService
{
    public function startPracticeAttempt(int $studentId, array $payload): JambAttempt
    {
        return DB::transaction(function () use ($studentId, $payload) {
            $subjectId = (int) $payload['subjectId'];
            $topicId = $payload['topicId'] ?? null;
            $questionCount = (int) $payload['questionCount'];
            $difficulty = $payload['difficulty'] ?? null;
            $timed = (bool) ($payload['timed'] ?? false);
            $durationMinutes = $timed ? (int) ($payload['durationMinutes'] ?? 0) : null;
            $year = $payload['year'] ?? null;

            if ($topicId) {
                $topic = JambTopic::where('topicId', $topicId)
                    ->where('subjectId', $subjectId)
                    ->first();

                if (!$topic) {
                    throw ValidationException::withMessages([
                        'topicId' => ['Selected topic does not belong to the selected subject.'],
                    ]);
                }
            }

            $questionQuery = JambQuestion::query()
                ->where('subjectId', $subjectId)
                ->where('status', 'published');

            if ($topicId) {
                $questionQuery->where('topicId', $topicId);
            }

            if ($difficulty) {
                $questionQuery->where('difficulty', $difficulty);
            }

            if ($year) {
                $questionQuery->where('year', $year);
            }

            $availableCount = (clone $questionQuery)->count();

            if ($availableCount < $questionCount) {
                throw ValidationException::withMessages([
                    'questionCount' => [
                        "Only {$availableCount} published questions match this filter. Reduce the requested count.",
                    ],
                ]);
            }

            $questions = $questionQuery
                ->inRandomOrder()
                ->limit($questionCount)
                ->get();

            $now = Carbon::now();

            $attempt = JambAttempt::create([
                'studentId' => $studentId,
                'mode' => 'practice',
                'status' => 'in_progress',
                'subjectId' => $subjectId,
                'topicId' => $topicId,
                'durationMinutes' => $durationMinutes,
                'timeRemainingSeconds' => $timed ? $durationMinutes * 60 : null,
                'totalQuestions' => $questionCount,
                'answeredQuestions' => 0,
                'correctAnswers' => 0,
                'wrongAnswers' => 0,
                'unansweredQuestions' => $questionCount,
                'score' => 0,
                'percentage' => 0,
                'startedAt' => $now,
                'expiresAt' => $timed ? $now->copy()->addMinutes($durationMinutes) : null,
                'currentQuestionOrder' => 1,
                'settingsJson' => [
                    'timed' => $timed,
                    'durationMinutes' => $durationMinutes,
                    'difficulty' => $difficulty,
                    'year' => $year,
                    'mode' => 'practice',
                ],
            ]);

            foreach ($questions->values() as $index => $question) {
                JambAttemptQuestion::create([
                    'attemptId' => $attempt->attemptId,
                    'subjectId' => $question->subjectId,
                    'questionId' => $question->questionId,
                    'questionOrder' => $index + 1,
                    'allocatedMark' => 1,
                ]);
            }

            return $attempt->load(['subject', 'topic']);
        });
    }

    public function saveAnswer(JambAttempt $attempt, array $payload): JambAttemptQuestion
    {
        if (!in_array($attempt->status, ['in_progress', 'paused'])) {
            throw ValidationException::withMessages([
                'attempt' => ['This attempt can no longer be updated.'],
            ]);
        }

        $this->expireAttemptIfNeeded($attempt);

        $attemptQuestion = JambAttemptQuestion::where('attemptId', $attempt->attemptId)
            ->where('attemptQuestionId', $payload['attemptQuestionId'])
            ->first();

        if (!$attemptQuestion) {
            throw ValidationException::withMessages([
                'attemptQuestionId' => ['Question does not belong to this attempt.'],
            ]);
        }

        $question = JambQuestion::findOrFail($attemptQuestion->questionId);

        $selectedOption = $payload['selectedOption'] ?? null;
        $isFlagged = array_key_exists('isFlagged', $payload)
            ? (bool) $payload['isFlagged']
            : $attemptQuestion->isFlagged;

        $attemptQuestion->selectedOption = $selectedOption;
        $attemptQuestion->isAnswered = !empty($selectedOption);
        $attemptQuestion->isCorrect = !empty($selectedOption) && $selectedOption === $question->correctOption;
        $attemptQuestion->isFlagged = $isFlagged;
        $attemptQuestion->timeSpentSeconds = (int) ($payload['timeSpentSeconds'] ?? $attemptQuestion->timeSpentSeconds);
        $attemptQuestion->answeredAt = !empty($selectedOption) ? now() : null;
        $attemptQuestion->save();

        if (!empty($payload['currentQuestionOrder'])) {
            $attempt->currentQuestionOrder = (int) $payload['currentQuestionOrder'];
        }

        $this->refreshAttemptStats($attempt);

        return $attemptQuestion->fresh(['question.options', 'question.topic']);
    }

    public function submitAttempt(JambAttempt $attempt): JambAttempt
    {
        if ($attempt->status === 'submitted') {
            return $attempt->fresh();
        }

        $this->expireAttemptIfNeeded($attempt);

        if ($attempt->status === 'expired') {
            $attempt->submittedAt = $attempt->submittedAt ?? now();
            $attempt->save();
            return $this->refreshAttemptStats($attempt);
        }

        $attempt->status = 'submitted';
        $attempt->submittedAt = now();
        $attempt->timeRemainingSeconds = $this->calculateRemainingSeconds($attempt);
        $attempt->save();

        return $this->refreshAttemptStats($attempt);
    }

    public function expireAttemptIfNeeded(JambAttempt $attempt): void
    {
        if (!$attempt->expiresAt || $attempt->status === 'submitted') {
            return;
        }

        if (now()->greaterThan($attempt->expiresAt)) {
            $attempt->status = 'expired';
            $attempt->submittedAt = $attempt->submittedAt ?? now();
            $attempt->timeRemainingSeconds = 0;
            $attempt->save();

            $this->refreshAttemptStats($attempt);
        }
    }

    public function refreshAttemptStats(JambAttempt $attempt): JambAttempt
    {
        $questions = JambAttemptQuestion::where('attemptId', $attempt->attemptId)->get();

        $answered = $questions->where('isAnswered', true)->count();
        $correct = $questions->where('isCorrect', true)->count();
        $wrong = $answered - $correct;
        $total = $questions->count();
        $unanswered = $total - $answered;
        $score = $correct;
        $percentage = $total > 0 ? round(($correct / $total) * 100, 2) : 0;

        $attempt->answeredQuestions = $answered;
        $attempt->correctAnswers = $correct;
        $attempt->wrongAnswers = $wrong;
        $attempt->unansweredQuestions = $unanswered;
        $attempt->score = $score;
        $attempt->percentage = $percentage;
        $attempt->timeRemainingSeconds = $this->calculateRemainingSeconds($attempt);
        $attempt->save();

        return $attempt->fresh(['subject', 'topic']);
    }

    protected function calculateRemainingSeconds(JambAttempt $attempt): ?int
    {
        if (!$attempt->expiresAt) {
            return null;
        }

        $remaining = now()->diffInSeconds($attempt->expiresAt, false);
        return max(0, $remaining);
    }
}