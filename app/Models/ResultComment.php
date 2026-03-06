<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResultComment extends Model
{
    protected $table = 'result_comments';
    protected $primaryKey = 'commentId';

protected $fillable = [
    'studentId',
    'classId',
    'teacherId',
    'termId',
    'academicYearId',
    'schoolId',
    'commentedBy',
    'comment',
    'commentType',
];






    public function term()
{
    return $this->belongsTo(Term::class, 'termId', 'termId')->orderBy('startDate', 'desc');
}

    public function academic_year()
{
    return $this->belongsTo(AcademicYear::class, 'academicYearId', 'academicYearId');

}

    public function school()
    {
        return $this->belongsTo(School::class, 'schoolId', 'schoolId');
    }

    public function student()
{
    return $this->belongsTo(Student::class, 'studentId', 'studentId');
}

    public function commenter()
{
    return $this->belongsTo(User::class, 'commentedBy', 'id');
}

}
