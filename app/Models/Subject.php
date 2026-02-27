<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    protected $table = 'subjects';

    protected $primaryKey = 'subjectId';

    protected $fillable = [
        'subjectId',
        'subjectName',
        'schoolId'
    ];


    public function school()
    {
        return $this->belongsTo(School::class, 'schoolId', 'schoolId');
    }

    public function subject_teachers()
{
    return $this->hasMany(
        SubjectTeacher::class,
        'subjectId',   // foreign key in subject_teachers
        'subjectId'    // local key in subjects
    );
}

//     public function teachers()
// {
//     return $this->belongsToMany(
//         Teacher::class,
//         'subject_teachers',   // pivot table
//         'subjectId',         // foreign key on pivot for subject
//         'teacherId'          // foreign key on pivot for teacher
//     );
// }

public function teachers()
{
    return $this->belongsToMany(
        Teacher::class,
        'subject_teachers',   // pivot table
        'subjectId',          // foreign key on pivot for subject
        'teacherId'           // foreign key on pivot for teacher
    )
    ->withPivot(['classId', 'schoolId', 'userId'])
    ->withTimestamps();
}

}
