<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParentAccess extends Model
{
    protected $table = 'parent_access';

    protected $primaryKey = 'parentAccessId';

    protected $fillable = [
        'schoolId',
        'phoneNumber',
        'academicYearId',
        'termId',
        'pinHash',
        'pinLookup',
        'pinLast4',
        'paymentMethod',
        'amountPaid',
        'expiresAt',
        'isActive',
        'failedAttempts',
        'lockedUntil',
        'activatedBy',
        'parentId',
    ];

    public function school()
    {
        return $this->belongsTo(School::class, 'schoolId', 'schoolId');
    }

    public function parent()
    {
        return $this->belongsTo(StudentParent::class, 'parentId', 'parentId');
    }
    
}

