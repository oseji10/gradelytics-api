<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JambAttemptQuestion extends Model
{
    protected $table = 'jamb_attempt_questions';
    protected $primaryKey = 'attemptQuestionId';

    protected $fillable = [
        'attemptId',
        'subjectId',
        'questionId',
        'questionOrder',
        'allocatedMark',
        'isAnswered',
        'isCorrect',
        'selectedOption',
        'isFlagged',
        'timeSpentSeconds',
        'answeredAt',
    ];

    protected $casts = [
        'isAnswered' => 'boolean',
        'isCorrect' => 'boolean',
        'isFlagged' => 'boolean',
        'answeredAt' => 'datetime',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(JambAttempt::class, 'attemptId', 'attemptId');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(JambSubject::class, 'subjectId', 'subjectId');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(JambQuestion::class, 'questionId', 'questionId');
    }
}