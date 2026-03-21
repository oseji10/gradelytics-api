<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JambAttempt extends Model
{
    protected $table = 'jamb_attempts';
    protected $primaryKey = 'attemptId';

    protected $fillable = [
        'studentId',
        'mode',
        'status',
        'subjectId',
        'topicId',
        'durationMinutes',
        'timeRemainingSeconds',
        'totalQuestions',
        'answeredQuestions',
        'correctAnswers',
        'wrongAnswers',
        'unansweredQuestions',
        'score',
        'percentage',
        'startedAt',
        'submittedAt',
        'expiresAt',
        'currentQuestionOrder',
        'settingsJson',
    ];

    protected $casts = [
        'startedAt' => 'datetime',
        'submittedAt' => 'datetime',
        'expiresAt' => 'datetime',
        'settingsJson' => 'array',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(JambSubject::class, 'subjectId', 'subjectId');
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(JambTopic::class, 'topicId', 'topicId');
    }

    public function attemptQuestions(): HasMany
    {
        return $this->hasMany(JambAttemptQuestion::class, 'attemptId', 'attemptId')
            ->orderBy('questionOrder');
    }
}