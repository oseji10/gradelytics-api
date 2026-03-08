<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CbtQuestion extends Model
{
    protected $table = 'cbt_questions';
    protected $primaryKey = 'questionId';
    public $timestamps = true;

    protected $fillable = [
        'schoolId',
        'subjectId',
        'topicId',
        'difficulty',
        'type',
        'questionText',
        'imageUrl',
        'mark',
        'createdBy',
        'classId'
    ];

    protected $casts = [
        'mark' => 'integer',
    ];

    // public function options(): HasMany
    // {
    //     return $this->hasMany(CbtQuestionOption::class, 'questionId', 'questionId')
    //         ->orderByRaw("FIELD(optionLabel,'A','B','C','D','E','F')"); // optional nice ordering
    // }

    // public function subject(): BelongsTo
    // {
    //     return $this->belongsTo(Subject::class, 'subjectId', 'subjectId');
    // }

    // public function topic(): BelongsTo
    // {
    //     return $this->belongsTo(Topic::class, 'topicId', 'topicId');
    // }

    public function subject()
{
    return $this->belongsTo(Subject::class, 'subjectId', 'subjectId');
}

public function topic()
{
    return $this->belongsTo(Topic::class, 'topicId', 'topicId');
}

public function options()
{
    return $this->hasMany(CbtQuestionOption::class, 'questionId', 'questionId');
}

public function correctOption()
{
    return $this->hasOne(CbtQuestionOption::class, 'questionId', 'questionId')
        ->where('isCorrect', true);
}

}