<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\GradingSystem;
use App\Models\AcademicYear;
// use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
class GradingController extends Controller
{



    /**
     * Get all grades for the current school.
     */
    public function index(Request $request): JsonResponse
    {
        $schoolId = $request->header('X-School-ID');

        $grades = GradingSystem::where('schoolId', $schoolId)
                    ->orderBy('minScore', 'desc')
                    ->get();

        return response()->json([
            'schoolId' => $schoolId,
            'grades'   => $grades
        ]);
    }

    /**
     * Optionally: Get grade for a specific score
     */
    public function getGrade(Request $request, float $score): JsonResponse
    {
        $schoolId = $request->header('X-School-ID');

        $grade = GradingSystem::where('schoolId', $schoolId)
                    ->where('min_score', '<=', $score)
                    ->where('max_score', '>=', $score)
                    ->first();

        if (!$grade) {
            return response()->json([
                'message' => 'No grade found for this score',
                'score' => $score
            ], 404);
        }

        return response()->json([
            'score' => $score,
            'grade' => $grade->grade,
            'remark' => $grade->remark
        ]);
    }


    /**
     * Store a single grade for the current school and session
     */
    public function store(Request $request): JsonResponse
    {
        $schoolId = $request->header('X-School-ID');

        // Validate single grade payload
        $validated = $request->validate([
            'minScore'   => 'required|integer|min:0',
            'maxScore'   => 'required|integer|max:100',
            'grade'      => 'required|string|max:5',
            'remark'     => 'required|string|max:255',
            'gradePoint' => 'nullable|numeric'
        ]);

        // Get current academic year/session
        $session = AcademicYear::where('schoolId', $schoolId)
            ->where('isActive', true)
            ->firstOrFail();

        // Optional: check for overlapping grades for the same session
        $overlap = GradingSystem::where('schoolId', $schoolId)
            // ->where('academicYearId', $session->academicYearId)
            ->where(function($q) use ($validated) {
                $q->whereBetween('minScore', [$validated['minScore'], $validated['maxScore']])
                  ->orWhereBetween('maxScore', [$validated['minScore'], $validated['maxScore']]);
            })
            ->exists();

        if ($overlap) {
            return response()->json([
                'message' => 'This score range overlaps with an existing grade.'
            ], 400);
        }

        // Store the grade
        $grade = GradingSystem::create([
            'schoolId'       => $schoolId,
            // 'academicYearId' => $session->academicYearId,
            'minScore'       => $validated['minScore'],
            'maxScore'       => $validated['maxScore'],
            'grade'          => $validated['grade'],
            'remark'         => $validated['remark'],
            'gradePoint'     => $validated['gradePoint'] ?? null,
        ]);

        return response()->json([
            'message' => 'Grade saved successfully',
            'grade'   => $grade
        ], 201);
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
