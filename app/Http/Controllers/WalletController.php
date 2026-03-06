<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;

use App\Models\School;
use App\Models\AcademicYear;
use App\Models\Term;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    /**
     * Send email to a single user (synchronously)
     */
    public function getWalletBalance(Request $request)
    {
        $schoolId = $request->header('X-School-ID');
        $school = School::where('schoolId', $schoolId)->first();

        $session = AcademicYear::where('schoolId', $schoolId)->where('isActive', true)->first();
        $term = Term::where('schoolId', $schoolId)->where('isActive', true)->first();
            if (!$session || !$term) {
                return response()->json([
                    'message' => 'Active academic year or term not found'
                ], 404);
            }

        if (!$school) {
            return response()->json([
                'message' => 'School not found'
            ], 404);
        }

        return response()->json([
            'walletBalance' => $school->walletBalance,
            'academicYear' => $session ?? null,
            'term' => $term ?? null
        ], 200);
    }
}