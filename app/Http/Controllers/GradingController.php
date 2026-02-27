<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class GradingController extends Controller
{
    use App\Models\GradingSystem;
use App\Models\AcademicYear;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

public function store(Request $request): JsonResponse
{
    $schoolId = $request->header('X-School-ID');

    $validated = $request->validate([
        'grades' => 'required|array',
        'grades.*.minScore' => 'required|integer|min:0',
        'grades.*.maxScore' => 'required|integer|max:100',
        'grades.*.grade' => 'required|string',
        'grades.*.remark' => 'required|string',
        'grades.*.gradePoint' => 'nullable|numeric'
    ]);

    $session = AcademicYear::where('schoolId', $schoolId)
        ->where('isActive', true)
        ->firstOrFail();

    // Optional: delete existing grading for this year
    GradingSystem::where('schoolId', $schoolId)
        ->where('academicYearId', $session->academicYearId)
        ->delete();

    foreach ($validated['grades'] as $grade) {
        GradingSystem::create([
            'schoolId' => $schoolId,
            'academicYearId' => $session->academicYearId,
            'minScore' => $grade['minScore'],
            'maxScore' => $grade['maxScore'],
            'grade' => $grade['grade'],
            'remark' => $grade['remark'],
            'gradePoint' => $grade['gradePoint'] ?? null,
        ]);
    }

    return response()->json([
        'message' => 'Grading system saved successfully'
    ], 200);
}


public function calculateGrade(int $score, int $schoolId, int $academicYearId)
{
    return GradingSystem::where('schoolId', $schoolId)
        // ->where('academicYearId', $academicYearId)
        ->where('minScore', '<=', $score)
        ->where('maxScore', '>=', $score)
        ->first();
}

}
