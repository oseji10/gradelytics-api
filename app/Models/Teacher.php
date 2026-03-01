<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Teacher extends Model
{
    protected $table = 'teachers';

    protected $primaryKey = 'teacherId';

    protected $fillable = [
        'teacherId',
        'qualification',
        'schoolId',
        'userId',
        'signature'
    ];


    public function school()
    {
        return $this->belongsTo(School::class, 'schoolId', 'schoolId');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'userId', 'id');
    }

    public function subjects()
{
    return $this->belongsToMany(
        Subject::class,
        'subject_teachers',
        'teacherId',
        'subjectId'
    );
}

}
