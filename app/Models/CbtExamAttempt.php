<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CbtExamAttempt extends Model
{
    use HasFactory;

    protected $table = 'cbt_exam_attempts';

    protected $primaryKey = 'attemptId';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'schoolId',
        'cbtExamId',
        'studentId',
        'attemptNumber',
        'status',
        'startedAt',
        'endsAt',
        'submittedAt',
        'expiresAt',
        'durationMinutes',
        'timeSpentSeconds',
        'score',
        'totalQuestions',
        'correctAnswers',
        'wrongAnswers',
        'unanswered',
        'percentage',
        'isSubmitted',
        'isTimedOut',
        'submittedBySystem',
        'metadata',
        'examId',
        'pauseCount',
        'totalPausedSeconds',
        'pausedAt',
        'lastHeartbeatAt',
        'totalPausedSeconds',
        'timeRemainingSeconds',
        'totalQuestions',

    ];

    protected $casts = [
        'startedAt' => 'datetime',
        'submittedAt' => 'datetime',
        'expiresAt' => 'datetime',
        'score' => 'decimal:2',
        'percentage' => 'decimal:2',
        'isSubmitted' => 'boolean',
        'isTimedOut' => 'boolean',
        'submittedBySystem' => 'boolean',
        'metadata' => 'array',
         'startedAt' => 'datetime',
        'endsAt' => 'datetime',
        'pausedAt' => 'datetime',
        'lastHeartbeatAt' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function exam(): BelongsTo
    {
        return $this->belongsTo(CbtExam::class, 'examId', 'examId');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'studentId', 'studentId');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(CbtExamAnswer::class, 'attemptId', 'attemptId');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeSubmitted($query)
    {
        return $query->where('isSubmitted', true);
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeTimedOut($query)
    {
        return $query->where('isTimedOut', true);
    }

    public function scopeForStudent($query, int $studentId)
    {
        return $query->where('studentId', $studentId);
    }

    public function scopeForExam($query, int $cbtExamId)
    {
        return $query->where('cbtExamId', $cbtExamId);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getRemainingSecondsAttribute(): int
    {
        if (!$this->expiresAt || $this->isSubmitted) {
            return 0;
        }

        $remaining = now()->diffInSeconds($this->expiresAt, false);

        return max(0, $remaining);
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expiresAt !== null && now()->greaterThanOrEqualTo($this->expiresAt);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function markAsStarted(): void
    {
        $this->update([
            'status' => 'in_progress',
            'startedAt' => $this->startedAt ?? now(),
            'expiresAt' => $this->expiresAt ?? now()->addMinutes((int) $this->durationMinutes),
        ]);
    }

    public function markAsSubmitted(bool $timedOut = false, bool $submittedBySystem = false): void
    {
        $this->update([
            'status' => 'completed',
            'isSubmitted' => true,
            'isTimedOut' => $timedOut,
            'submittedBySystem' => $submittedBySystem,
            'submittedAt' => now(),
        ]);
    }

    
}