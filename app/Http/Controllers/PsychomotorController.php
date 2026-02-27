<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PsychomotorDomain;
use App\Models\AffectiveDomain;
use App\Models\PsychomotorScore;
use App\Models\AcademicYear;
use App\Models\Term;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class PsychomotorController extends Controller
{


public function domains(Request $request)
{
    $schoolId = $request->header('X-School-ID');

    if (!$schoolId) {
        return response()->json([
            'message' => 'School ID header missing'
        ], 400);
    }

    $affective = AffectiveDomain::where('schoolId', $schoolId)->get();
    $psychomotor = PsychomotorDomain::where('schoolId', $schoolId)->get();

    return response()->json([
        'affective' => $affective,
        'psychomotor' => $psychomotor,
    ], 200);
}


public function psychomotorDomain(Request $request)
{
    $schoolId = $request->header('X-School-ID');

    if (!$schoolId) {
        return response()->json([
            'message' => 'School ID header missing'
        ], 400);
    }

    $psychomotor = PsychomotorDomain::where('schoolId', $schoolId)->get();
    // $psychomotor = PsychomotorDomain::where('schoolId', $schoolId)->get();

    return response()->json( $psychomotor, 200);
}


public function getPsychomotorClassScores(Request $request)
{
    $schoolId = $request->header('X-School-ID');
    $session = AcademicYear::where('schoolId', $schoolId)->where('isActive', true)->firstOrFail();
    $term = Term::where('schoolId', $schoolId)->where('isActive', true)->firstOrFail();

       $validated = $request->validate([
       'classId'         => 'nullable|integer'
    ]);


    $scores = PsychomotorScore::with('type')
        // ->where('classId', $classId)
        // ->where('classId', $validated['classId'])
        ->where('termId', $term->termId)
        ->where('academicYearId', $session->academicYearId)
        ->where('schoolId', $schoolId)
        ->get();

    return response()->json($scores);
}

public function saveDomain(Request $request)
{
    $schoolId = $request->header('X-School-ID');

    $validated = $request->validate([
        'domainName' => 'required|string|max:255',
        'domainType' => 'required|string|in:Psychomotor,Psychomotor',
        'maxScore'   => 'required|numeric|min:1',
        'weight'     => 'required|numeric|min:0|max:100',
        'id'         => 'nullable|integer'
    ]);

    if (!$schoolId) {
        return response()->json([
            'message' => 'School ID header missing'
        ], 400);
    }

    // 🔥 Select model dynamically
    switch ($validated['domainType']) {
        case 'Psychomotor':
            $model = new PsychomotorDomain();
            $primaryKey = 'domainId';
            break;

        case 'Psychomotor':
            $model = new PsychomotorDomain();
            $primaryKey = 'domainId';
            break;

        default:
            return response()->json([
                'message' => 'Invalid domain type'
            ], 400);
    }

    $validated['schoolId'] = $schoolId;

    // Remove domainType before saving
    unset($validated['domainType']);

    $domain = $model->updateOrCreate(
        [$primaryKey => $request->id ?? 0],
        $validated
    );

    return response()->json([
        'message' => 'Domain saved successfully',
        'data' => $domain
    ], 200);
}

    // Delete a domain
    public function deleteDomain($id)
    {
        $domain = PsychomotorDomain::findOrFail($id);
        $domain->delete();
        return response()->json(['message' => 'Domain deleted']);
    }



public function storeDomainScores(Request $request, int $domainId): JsonResponse
{
    $schoolId = $request->header('X-School-ID');

    $validated = $request->validate([
        'classId' => 'required|integer',
        'ratings' => 'required|array',
        'ratings.*.studentId' => 'required|integer',
        'ratings.*.rating' => 'required|numeric|min:0'
    ]);

    $domain = PsychomotorDomain::findOrFail($domainId); 
    // If separate tables: PsychomotorDomain::findOrFail($domainId)

    // 🔥 Get current academic year
    $session = AcademicYear::where('schoolId', $schoolId)
        ->where('isActive', true)
        ->firstOrFail();

    // 🔥 Get current term
    $term = Term::where('schoolId', $schoolId)
        ->where('isActive', true)
        ->firstOrFail();

    foreach ($validated['ratings'] as $entry) {

        if ($entry['rating'] > $domain->maxScore) {
            return response()->json([
                'message' => "Rating cannot exceed max score of {$domain->maxScore}"
            ], 400);
        }

        PsychomotorScore::updateOrCreate(
            [
                'domainId' => $domainId,
                'studentId' => $entry['studentId'],
                'termId' => $term->termId,
            ],
            [
                'score' => $entry['rating'],
                'schoolId' => $schoolId,
                'classId' => $validated['classId'],
                'academicYearId' => $session->academicYearId,
            ]
        );
    }

    return response()->json([
        'message' => 'Domain ratings saved successfully'
    ], 200);
}

    // Fetch scores for a student
    public function getStudentScores($studentId, $schoolId)
    {
        $scores = PsychomotorScore::with('domain', 'subject')
            ->where('studentId', $studentId)
            ->where('schoolId', $schoolId)
            ->get();

        return response()->json($scores);
    }
}