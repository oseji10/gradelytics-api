<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class school extends Model
{
    protected $table = 'schools';

    protected $primaryKey = 'schoolId';

    protected $fillable = [
        'schoolId',
        'schoolName',
        'schoolPhone',
        'schoolEmail',
        'schoolLogo',
        'countryCode',
        'currency',
        'ownerId',
        'isDefault',
        'schoolAddress',
        'status',
        'taxId'
    ];

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency', 'currencyId');
    }

    public function payment_gateway()
    {
        return $this->belongsTo(PaymentGateway::class, 'gatewayPreference', 'gatewayId');
    }



public function owner()
{
    return $this->belongsTo(User::class, 'id', 'ownerId');
}
}

