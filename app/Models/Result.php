<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Result extends Model
{
    protected $table = 'results';
    protected $primaryKey = 'resultId';

protected $fillable = [
    'studentId',
    'classId',
    'subjectId',
    'termId',
    'academicYearId',
    'schoolId',
    'totalScore',
    'grade',
    'remark',
    'classTeacherComment',
    'principalComment',
];

public function class_subject()
    {
        return $this->belongsTo(ClassSubject::class, 'subjectId');
    }


    public function subject()
{
    return $this->belongsTo(Subject::class, 'subjectId', 'subjectId');
}

}
