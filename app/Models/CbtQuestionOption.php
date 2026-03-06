<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CbtQuestionOption extends Model
{
    protected $table = 'cbt_question_options';
    protected $primaryKey = 'optionId';
    public $timestamps = true;

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
        return $this->belongsTo(CbtQuestion::class, 'questionId', 'questionId');
    }
}