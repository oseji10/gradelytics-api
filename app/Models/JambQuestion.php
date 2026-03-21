<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JambQuestion extends Model
{
    protected $table = 'jamb_questions';
    protected $primaryKey = 'questionId';

    protected $fillable = [
        'subjectId',
        'topicId',
        'year',
        'questionText',
        'questionImage',
        'passageText',
        'optionType',
        'correctOption',
        'explanation',
        'difficulty',
        'status',
        'createdBy',
        'schoolId',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(JambSubject::class, 'subjectId', 'subjectId');
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(JambTopic::class, 'topicId', 'topicId');
    }

    public function options(): HasMany
    {
        return $this->hasMany(JambQuestionOption::class, 'questionId', 'questionId')
            ->orderByRaw("FIELD(optionLabel, 'A', 'B', 'C', 'D')");
    }
}