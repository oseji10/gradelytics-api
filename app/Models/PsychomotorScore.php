<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PsychomotorScore extends Model
{
    use HasFactory;

    protected $table = 'psychomotor_scores';

    protected $primaryKey = 'scoreId';

    protected $fillable = ['studentId', 'domainId', 'score', 'subjectId', 'schoolId',
    'termId',
    'academicYearId',
    'classId',
    ];

    public function domain()
    {
        return $this->belongsTo(PsychomotorDomain::class, 'domainId');
    }

     public function type()
    {
        return $this->belongsTo(PsychomotorDomain::class, 'domainId');
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'studentId');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class, 'subjectId');
    }

    public function school()
    {
        return $this->belongsTo(School::class, 'schoolId');
    }
}