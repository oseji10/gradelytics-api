<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Topic extends Model
{
    protected $table = 'topics';
    protected $primaryKey = 'topicId';
    public $timestamps = true;

    protected $fillable = [
        'schoolId',
        'subjectId',
        'topicName',
        'createdBy'
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subjectId', 'subjectId');
    }
}