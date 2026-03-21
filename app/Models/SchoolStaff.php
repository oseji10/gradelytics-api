<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolStaff extends Model
{
    protected $table = 'school_staff';

    protected $primaryKey = 'schoolStaffId';

    protected $fillable = [
        'schoolStaffId',
        'schoolId',
        'staffId',
    ];
}
