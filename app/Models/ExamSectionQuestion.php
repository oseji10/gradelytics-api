<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamSectionQuestion extends Model
{
    protected $table = 'exam_section_questions';
    protected $primaryKey = 'examSectionQuestionId';

    protected $fillable = [
        'examSectionId',
        'questionId',
        'orderIndex',
        'mark',
    ];

    public function section()
    {
        return $this->belongsTo(ExamSection::class, 'examSectionId', 'examSectionId');
    }

    public function question()
    {
        return $this->belongsTo(CbtQuestion::class, 'questionId', 'questionId');
    }
}