<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\ParentPinService;
use App\Models\School;

class ParentAccessController extends Controller
{
    public function activate(Request $request, ParentPinService $pinService)
    {
        $schoolId = $request->header('X-School-ID');
        $request->validate([
            'phoneNumber' => 'required|string',
            'academicSessionId' => 'required|integer',
            'termId' => 'required|integer',
            'amountPaid' => 'required|numeric',
        ]);

        

        // Prevent duplicate activation same term
        if (\App\Models\ParentAccess::where([
            'schoolId' => $schoolId,
            'phoneNumber' => $request->phoneNumber,
            'academicYearId' => $request->academicSessionId,
            'termId' => $request->termId,
        ])->exists()) {
            return response()->json([
                'message' => 'Access already exists for this term.'
            ], 422);
        }

        $result = $pinService->createPinRecord(
            $schoolId,
            $request->phoneNumber,
            $request->academicSessionId,
            $request->termId,
            'cash',
            $request->amountPaid
        );

        return response()->json([
            'message' => 'Parent access activated successfully',
            'pin' => $result['plain_pin'],
            'expires_at' => $result['record']->expires_at
        ]);
    }
}