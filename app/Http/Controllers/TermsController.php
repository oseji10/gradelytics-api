<?php

namespace App\Http\Controllers;

use App\Models\Term;
use App\Models\AcademicYear;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
class TermsController extends Controller
{
    public function index(Request $request)
    {
        $schoolId = $request->header('X-School-ID');

        $terms = Term::with('academicYear')
            ->where('schoolId', $schoolId)
            ->orderBy('termOrder')
            ->get();

        return response()->json($terms);
    }

    public function store(Request $request)
    {
        $schoolId = $request->header('X-School-ID');

        $validated = $request->validate([
            'academicYearId' => 'required|exists:academic_years,academicYearId',
            'termName' => 'required|string|max:50',
            'termOrder' => 'nullable|integer|min:1|max:3',
            'startDate' => 'required|date',
            'endDate' => 'required|date|after:startDate',
        ]);

        $startDate = Carbon::parse($validated['startDate'])->format('Y-m-d');
        $endDate = Carbon::parse($validated['endDate'])->format('Y-m-d');

        $term = Term::create([
            'schoolId' => $schoolId,
            'academicYearId' => $validated['academicYearId'],
            'termName' => $validated['termName'],
            // 'termOrder' => $validated['termOrder'],
            'startDate' => $startDate,
            'endDate' => $endDate,
            'isActive' => false,
        ]);

        return response()->json($term, 201);
    }

    public function activate(Request $request, $termId)
    {
        $schoolId = $request->header('X-School-ID');

        $term = Term::where('schoolId', $schoolId)
            ->where('termId', $termId)
            ->firstOrFail();

            if (!$term->academicYear->isActive || $term->academicYear->isClosed) {
    return response()->json([
        'message' => 'Cannot activate term. Academic year inactive or closed.'
    ], 400);
}
        DB::transaction(function () use ($schoolId, $term) {

            // Deactivate all terms
            Term::where('schoolId', $schoolId)
                ->update(['isActive' => false]);

            // Activate selected term
            $term->update(['isActive' => true]);
        });

        return response()->json([
            'message' => 'Term activated successfully'
        ]);
    }
}