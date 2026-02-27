<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassStudent extends Model
{
    protected $table = 'class_students';

    // protected $primaryKey = 'studentId';

    protected $fillable = [
        'studentId',
        'schoolId',
        'classId',
    ];


    public function school()
    {
        return $this->belongsTo(School::class, 'schoolId', 'schoolId');
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'studentId', 'studentId');
    }

public function parents()
{
    return $this->belongsToMany(
        StudentParent::class,
        'student_parents',  // pivot table
        'studentId',        // foreign key for Student on pivot
        'parentId'          // foreign key for Parent on pivot
    );
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
