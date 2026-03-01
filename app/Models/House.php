<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class House extends Model
{
    protected $table = 'houses';

    protected $primaryKey = 'houseId';

    protected $fillable = [
        'schoolId',
        'houseName',
        'houseId',
       
    ];

    public function school()
    {
        return $this->belongsTo(School::class, 'schoolId', 'schoolId');
    }
    
}

