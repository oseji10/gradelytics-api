<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AffectiveDomain extends Model
{
    use HasFactory;
    protected $table = 'affective_domains';

    protected $primaryKey = 'domainId';

    protected $fillable = ['domainName', 
    'maxScore', 
    'weight', 
    'schoolId',
    'termId',
    'academicYearId',
    // 'classId',
    ];

    public function scores()
    {
        return $this->hasMany(AffectiveScore::class, 'domainId');
    }

    public function school()
    {
        return $this->belongsTo(School::class, 'schoolId');
    }
}