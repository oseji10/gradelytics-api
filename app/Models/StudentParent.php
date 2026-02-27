<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentParent extends Model
{
    protected $table = 'parents';

    protected $primaryKey = 'parentId';

    protected $fillable = [
        'parentId',
        'userId',
        'schoolId'
    ];


    public function school()
    {
        return $this->belongsTo(School::class, 'schoolId', 'schoolId');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'userId', 'id');
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
