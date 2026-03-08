<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamSection extends Model
{
    protected $table = 'exam_sections';
    protected $primaryKey = 'examSectionId';

    protected $fillable = [
        'examId',
        'schoolId',
        'title',
        'instructions',
        'sectionOrder',
        'totalMarks',
    ];

    public function exam()
    {
        return $this->belongsTo(CbtExam::class, 'examId', 'examId');
    }

    public function sectionQuestions()
    {
        return $this->hasMany(ExamSectionQuestion::class, 'examSectionId', 'examSectionId')
            ->orderBy('orderIndex');
    }

    public function questions()
    {
        return $this->belongsToMany(
            CbtQuestion::class,
            'exam_section_questions',
            'examSectionId',
            'questionId',
            'examSectionId',
            'questionId'
        )
            ->withPivot(['examSectionQuestionId', 'orderIndex', 'mark'])
            ->withTimestamps()
            ->orderBy('exam_section_questions.orderIndex');
    }
}