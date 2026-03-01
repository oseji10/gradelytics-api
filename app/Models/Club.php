<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Club extends Model
{
    protected $table = 'clubs';

    protected $primaryKey = 'clubId';

    protected $fillable = [
        'schoolId',
        'clubName',
        'clubId',
       
    ];

    public function school()
    {
        return $this->belongsTo(School::class, 'schoolId', 'schoolId');
    }


}

