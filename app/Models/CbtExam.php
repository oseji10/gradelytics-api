<?php
// app/Models/CbtExam.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CbtExam extends Model
{
    protected $table = 'cbt_exams';
    protected $primaryKey = 'examId';
    public $timestamps = true;

    protected $fillable = [
        'examId',
        'schoolId',
        'academicYearId',
        'termId',
        'classId',
        'subjectId',
        'title',
        'instructions',
        'durationMinutes',
        'startsAt',
        'endsAt',
        'totalMarks',
        'shuffleQuestions',
        'shuffleOptions',
        'attemptLimit',
        'isPublished',
        'showResultImmediately',

        // optional if you want later "sync to results"
        'scoreMode',           // practice | graded
        'resultComponent',     // ca | exam | custom | none
        'componentMaxScore',   // e.g. 20
        'createdBy',
    ];

    protected $casts = [
        'startsAt' => 'datetime',
        'endsAt' => 'datetime',
        'shuffleQuestions' => 'boolean',
        'shuffleOptions' => 'boolean',
        'isPublished' => 'boolean',
        'showResultImmediately' => 'boolean',
        'attemptLimit' => 'integer',
        'durationMinutes' => 'integer',
        'totalMarks' => 'float',
        'componentMaxScore' => 'float',
    ];

    // ───────────────────────── Relationships ─────────────────────────

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subjectId', 'subjectId');
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'classId', 'classId'); // adjust model name if yours differs
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'termId', 'termId');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academicYearId', 'academicYearId');
    }

    public function questions(): BelongsToMany
{
    return $this->belongsToMany(
        CbtQuestion::class,
        'cbt_exam_questions', // ✅ correct table
        'examId',
        'questionId'
    )->withPivot(['orderIndex'])->orderBy('cbt_exam_questions.orderIndex');
}

    public function attempts(): HasMany
    {
        return $this->hasMany(CbtExamAttempt::class, 'examId', 'examId');
    }

    // ───────────────────────── Scopes ─────────────────────────

    public function scopeForSchool($query, int $schoolId)
    {
        return $query->where('schoolId', $schoolId);
    }

    public function scopePublished($query)
    {
        return $query->where('isPublished', true);
    }

    public function isActiveNow(): bool
    {
        $now = now();
        if ($this->startsAt && $now->lt($this->startsAt)) return false;
        if ($this->endsAt && $now->gt($this->endsAt)) return false;
        return true;
    }

    public function sections()
{
    return $this->hasMany(ExamSection::class, 'examId', 'examId')
        ->orderBy('sectionOrder');
}

public function canBeModified(): bool
{
    return !$this->attempts()
        ->whereIn('status', ['in_progress', 'submitted', 'timed_out'])
        ->exists();
}

// public function class()
// {
//     return $this->belongsTo(SchoolClass::class, 'classId', 'classId');
// }

// public function subject()
// {
//     return $this->belongsTo(Subject::class, 'subjectId', 'subjectId');
// }

// public function sections()
// {
//     return $this->hasMany(ExamSection::class, 'examId', 'examId')
//         ->orderBy('sectionOrder');
// }

}