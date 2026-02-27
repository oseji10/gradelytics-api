<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicYear extends Model
{
    protected $table = 'academic_years';

    protected $primaryKey = 'academicYearId';

    protected $fillable = [
        'academicYearId',
        'academicYearName',
        'startDate',
        'endDate',
        'isActive',
        'schoolId'
    ];

    public function terms()
{
    return $this->hasMany(Term::class, 'academicYearId', 'academicYearId');
}

}