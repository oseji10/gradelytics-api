<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JambTopic extends Model
{
    protected $table = 'jamb_topics';
    protected $primaryKey = 'topicId';

    protected $fillable = [
        'subjectId',
        'topicName',
        'description',
        'isActive',
    ];

    protected $casts = [
        'isActive' => 'boolean',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(JambSubject::class, 'subjectId', 'subjectId');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(JambQuestion::class, 'topicId', 'topicId');
    }
}