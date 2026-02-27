<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolClass extends Model
{
    protected $table = 'classes';

    protected $primaryKey = 'classId';

    protected $fillable = [
        'classId',
        'className',
        'schoolId'
    ];


    public function school()
    {
        return $this->belongsTo(School::class, 'schoolId', 'schoolId');
    }

    public function class_teachers()
{
    return $this->belongsToMany(
        Teacher::class,
        'class_teachers',   // pivot table
        'classId',         // foreign key on pivot for subject
        'teacherId'          // foreign key on pivot for teacher
    );
}
}
