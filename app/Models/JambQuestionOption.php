<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JambQuestionOption extends Model
{
    protected $table = 'jamb_question_options';
    protected $primaryKey = 'optionId';

    protected $fillable = [
        'questionId',
        'optionLabel',
        'optionText',
        'isCorrect',
    ];

    protected $casts = [
        'isCorrect' => 'boolean',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(JambQuestion::class, 'questionId', 'questionId');
    }
}