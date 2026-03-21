<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JambSubject extends Model
{
    protected $table = 'jamb_subjects';
    protected $primaryKey = 'subjectId';

    protected $fillable = [
        'subjectName',
        'subjectCode',
        'isActive',
    ];

    protected $casts = [
        'isActive' => 'boolean',
    ];

    public function topics(): HasMany
    {
        return $this->hasMany(JambTopic::class, 'subjectId', 'subjectId');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(JambQuestion::class, 'subjectId', 'subjectId');
    }
}