<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;


class AcademicYearController extends Controller
{
    public function index(Request $request)
    {
        $schoolId = $request->header('X-School-ID');

        $years = AcademicYear::where('schoolId', $schoolId)
            ->orderBy('startDate', 'desc')
            ->get();

        return response()->json($years);
    }

    public function store(Request $request)
    {
        $schoolId = $request->header('X-School-ID');

        $validated = $request->validate([
            'academicYearName' => 'required|string|max:20',
            'startDate' => 'required|date',
            'endDate' => 'required|date|after:startDate',
        ]);

        $startDate = Carbon::parse($validated['startDate'])->format('Y-m-d');
        $endDate = Carbon::parse($validated['endDate'])->format('Y-m-d');

        $year = AcademicYear::create([
            'schoolId' => $schoolId,
            'academicYearName' => $validated['academicYearName'],
            'startDate' => $startDate,
            'endDate' => $endDate,
            'isActive' => false,
            'isClosed' => false,
        ]);

        return response()->json($year, 201);
    }

    public function activate(Request $request, $academicYearId)
    {
        $schoolId = $request->header('X-School-ID');

        DB::transaction(function () use ($schoolId, $academicYearId) {

            AcademicYear::where('schoolId', $schoolId)
                ->update(['isActive' => false]);

            AcademicYear::where('schoolId', $schoolId)
                ->where('academicYearId', $academicYearId)
                ->update(['isActive' => true]);
        });

        return response()->json([
            'message' => 'Academic year activated successfully'
        ]);
    }

    public function close(Request $request, $academicYearId)
    {
        $schoolId = $request->header('X-School-ID');

        $year = AcademicYear::where('schoolId', $schoolId)
            ->where('academicYearId', $academicYearId)
            ->firstOrFail();

        $year->update([
            'isClosed' => true,
            'isActive' => false
        ]);

        return response()->json([
            'message' => 'Academic year closed successfully'
        ]);
    }
}