<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubjectTeacher extends Model
{
    protected $table = 'subject_teachers';

    // protected $primaryKey = 'parentId';

    protected $fillable = [
        'teacherId',
        'subjectId',
        'schoolId',
        'userId',
        'classId'
    ];


    public function school()
    {
        return $this->belongsTo(School::class, 'schoolId', 'schoolId');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'userId', 'id');
    }

    public function class()
    {
        return $this->belongsTo(SchoolClass::class, 'classId', 'classId');
    }

    public function students()
{
    return $this->belongsToMany(
        Student::class,
        'student_parents',   // pivot table
        'studentId',         // foreign key on pivot for subject
        'parentId'          // foreign key on pivot for teacher
    );
}
}
