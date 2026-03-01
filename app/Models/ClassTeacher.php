<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassTeacher extends Model
{
    protected $table = 'class_teachers';

    // protected $primaryKey = 'teacherId';

    protected $fillable = [
        'teacherId',
        'schoolId',
        'classId',
        'signature'
    ];


    public function school()
    {
        return $this->belongsTo(School::class, 'schoolId', 'schoolId');
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacherId', 'teacherId');
    }



public function classes()
{
    return $this->belongsToMany(
        SchoolClass::class,
        'class_students',
        'studentId',
        'classId',
        'studentId',
        'classId'
    )->withPivot('schoolId');
}

}
