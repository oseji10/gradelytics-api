<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Term extends Model
{
    protected $table = 'terms';

    protected $primaryKey = 'termId';

    protected $fillable = [
        'termId',
        'termName',
        'startDate',
        'endDate',
        'isActive',
        'schoolId',
        'termOrder',
        'academicYearId',
    ];

    public function academicYear()
{
    return $this->belongsTo(AcademicYear::class, 'academicYearId', 'academicYearId');
}

}