<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assessment extends Model
{
    protected $fillable = [
        'schoolId',
        'subjectId',
        'assessmentName',
        'maxScore',
        'weight'
    ];

    protected $primaryKey = 'assessmentId';

    public function scores()
    {
        return $this->hasMany(AssessmentScore::class, 'assessmentId');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subjectId');
    }

   
}