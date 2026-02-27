<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GradingSystem extends Model
{
    use HasFactory;

protected $table = 'grading_systems';
protected $primaryKey = 'gradingId';

protected $fillable = [
    'schoolId',
    'academicYearId',
    'minScore',
    'maxScore',
    'grade',
    'remark',
    'gradePoint'
];

}