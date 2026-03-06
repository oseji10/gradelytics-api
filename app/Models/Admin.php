<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Admin extends Model
{
    protected $table = 'admin';

    // protected $primaryKey = 'adminId';
    protected $fillable = [
        'userId',
        'schoolId',
    ];


    public function school()
    {
        return $this->belongsTo(School::class, 'schoolId', 'schoolId');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'userId', 'id');
    }

   }
