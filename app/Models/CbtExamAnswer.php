<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CbtExamAnswer extends Model
{
    protected $table = 'cbt_exam_answers';
    protected $primaryKey = 'answerId';

    protected $fillable = [
        'attemptId',
        'examId',
        'studentId',
        'questionId',
        'selectedOptionId',
        'answerText',
        'isCorrect',
        'scoreAwarded',
        'isFlagged',
        'answeredAt',
    ];

    protected $casts = [
        'isCorrect' => 'boolean',
        'isFlagged' => 'boolean',
        'scoreAwarded' => 'float',
        'answeredAt' => 'datetime',
    ];

    public function attempt()
    {
        return $this->belongsTo(CbtExamAttempt::class, 'attemptId', 'attemptId');
    }

    public function question()
    {
        return $this->belongsTo(CbtQuestion::class, 'questionId', 'questionId');
    }
}