<?php

namespace App\Services;

use App\Models\AssessmentScore;
use App\Models\Result;
use App\Models\AcademicYear;
use App\Models\Term;
use App\Models\GradingSystem;

class ResultService
{
    public function computeAndStoreResult(
        int $studentId,
        int $classId,
        int $subjectId,
        int $schoolId
    ): void {

        $session = AcademicYear::where('schoolId', $schoolId)
            ->where('isActive', true)
            ->firstOrFail();

        $term = Term::where('schoolId', $schoolId)
            ->where('isActive', true)
            ->firstOrFail();

        // 🔥 Calculate total subject score
        $total = AssessmentScore::where('studentId', $studentId)
            ->where('subjectId', $subjectId)
            ->where('termId', $term->termId)
            ->sum('score');

        // 🔥 Get grading
        $gradeData = GradingSystem::where('schoolId', $schoolId)
            // ->where('academicYearId', $session->academicYearId)
            ->where('minScore', '<=', $total)
            ->where('maxScore', '>=', $total)
            ->first();

        $grade = $gradeData->grade ?? null;
        $remark = $gradeData->remark ?? null;

        // 🔥 Store final result
        Result::updateOrCreate(
            [
                'studentId' => $studentId,
                'subjectId' => $subjectId,
                'termId' => $term->termId,
            ],
            [
                'classId' => $classId,
                'schoolId' => $schoolId,
                'academicYearId' => $session->academicYearId,
                'totalScore' => $total,
                'grade' => $grade,
                'remark' => $remark,
            ]
        );
    }
}