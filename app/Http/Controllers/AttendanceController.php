<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StudentAttendance;
use App\Models\AcademicYear;
use App\Models\Term;

class AttendanceController extends Controller
{
    public function index(Request $request)
{
    $schoolId = $request->header('X-School-ID');

    $request->validate([
        'classId' => 'required|integer',
        'date' => 'required|date'
    ]);

    // Get active academic year
    $academicYear = AcademicYear::where('schoolId', $schoolId)
        ->where('isActive', true)
        ->first();

    if (!$academicYear) {
        return response()->json([
            'message' => 'No active academic year found'
        ], 404);
    }

    // Get active term
    $term = Term::where('schoolId', $schoolId)
        ->where('academicYearId', $academicYear->academicYearId)
        ->where('isActive', true)
        ->first();

    if (!$term) {
        return response()->json([
            'message' => 'No active term found'
        ], 404);
    }

    // Check if locked
    $isLocked = $academicYear->isClosed || ($term->isClosed ?? false);

    $attendance = StudentAttendance::where([
            'schoolId' => $schoolId,
            'classId' => $request->classId,
            'academicYearId' => $academicYear->academicYearId,
            'termId' => $term->termId,
            'attendanceDate' => $request->date
        ])
        ->get();

    return response()->json([
        'editable' => !$isLocked,
        'attendance' => $attendance
    ]);
}

   

    public function markAttendance(Request $request)
{
    $schoolId = $request->header('X-School-ID');

    $validated = $request->validate([
        // 'attendanceSessionId' => 'required|integer',
        'records' => 'required|array'
    ]);

    $session = AcademicYear::where('isActive', 1)
    ->where('isClosed', 0)
    ->first();

    if (!$session) {
        return response()->json(['message' => 'Session already closed or inactive'], 400);
    }

    $term = Term::where('isActive', 1)
    ->where('isClosed', 0)
    ->first();

    if (!$term) {
        return response()->json(['message' => 'Term already closed or inactive'], 400);
    }

    foreach ($validated['records'] as $record) {

        StudentAttendance::updateOrCreate(
            [
                'studentId' => $record['studentId']
                ],
                [
                'classId' => $request->classId,
                'attendanceDate' => $request->date,
                'schoolId' => $schoolId,
                'status' => $record['status'],
                'teacherId' => auth()->id(),
                'academicYearId' => $session->academicYearId,
                'termId' => $term->termId,
            ]
        );
    }

    return response()->json(['message' => 'Attendance marked successfully']);
}
    
}
