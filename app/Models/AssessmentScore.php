<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentScore extends Model
{
    protected $fillable = [
        'assessmentId',
        'studentId',
        'score',
        'academicYearId',
        'termId',
        'classId',
        'schoolId',
        'subjectId'
    ];

    protected $primaryKey = 'assessmentScoreId';

    public function assessment()
    {
        return $this->belongsTo(Assessment::class, 'assessmentId');
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'studentId');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subjectId');
    }
}