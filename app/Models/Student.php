<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    protected $table = 'students';

    protected $primaryKey = 'studentId';

    protected $fillable = [
        'studentId',
        'userId',
        'schoolId',
        'gender',
        'dateOfBirth',
        'bloodGroup',
        'passport',
        'clubId',
        'houseId',
        'parentId',
        'admissionNumber',
        'schoolAssignedAdmissionNumber'
    ];


    public function school()
    {
        return $this->belongsTo(School::class, 'schoolId', 'schoolId');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'userId', 'id');
    }

    public function student_class()
    {
        return $this->belongsTo(SchoolClass::class, 'classId', 'classId');
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

public function club()
    {
        return $this->belongsTo(Club::class, 'clubId', 'clubId');
    }

    public function house()
    {
        return $this->belongsTo(House::class, 'houseId', 'houseId');
    }
}
