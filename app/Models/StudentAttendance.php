<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentAttendance extends Model
{
    protected $table = 'attendance';

    protected $primaryKey = 'attendanceId';

    protected $fillable = [
        'studentId',
        'classId',
        'schoolId',
        'teacherId',
        'attendanceDate',
        'termId',
        'academicYearId',
        'attendanceStatus',
        'status'
    ];


    public function school()
    {
        return $this->belongsTo(School::class, 'schoolId', 'schoolId');
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'studentId', 'studentId');
    }


    public function class()
    {
        return $this->belongsTo(SchoolClass::class, 'classId', 'classId');
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacherId', 'teacherId');
    }

    public function term()
    {
        return $this->belongsTo(Term::class, 'termId', 'termId');
    }

    public function academic_year()
    {
        return $this->belongsTo(AcademicYear::class, 'academicYearId', 'academicYearId');
    }

}
