<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PsychomotorDomain extends Model
{
    use HasFactory;
    protected $table = 'psychomotor_domains';

    protected $primaryKey = 'domainId';

    protected $fillable = ['domainName', 'maxScore', 'weight', 'schoolId', 'remarks'];

    public function scores()
    {
        return $this->hasMany(AffectiveScore::class, 'domainId');
    }

    public function school()
    {
        return $this->belongsTo(School::class, 'schoolId');
    }
}