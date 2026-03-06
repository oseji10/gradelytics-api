<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PinCost extends Model
{
    protected $table = 'pin_cost';


    protected $fillable = [
        'quantityRange',
        'costPerPin',
        'schoolId',
    ];

    public function school()
    {
        return $this->belongsTo(School::class, 'schoolId', 'schoolId');
    }
    
}

