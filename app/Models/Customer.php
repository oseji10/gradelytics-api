<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $table = 'customers';

    protected $primaryKey = 'customerId';

    protected $fillable = [
        'customerId',
        'customerName',
        'customerEmail',
        'customerPhone',
        'customerAddress',
        'schoolId'
    ];


    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'schoolId', 'schoolId');
    }


}
