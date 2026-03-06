<?php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Models\ParentAccess;
use App\Models\School;
use App\Models\Term;
use App\Models\AcademicYear;
use App\Models\User;
use App\Models\Admin;
use App\Models\PinCost;
use Illuminate\Http\Request;

class PinManagementController extends Controller
{
    
public function generate(Request $request)
{
    $schoolId = $request->header('X-School-ID');

    $validated = $request->validate([
        'quantity' => 'required|integer|min:1|max:500',
    ]);

    $quantity = $validated['quantity'];

    $pin_cost = PinCost::where('schoolId', $schoolId)
        ->where('minQuantity', '<=', $quantity)
        ->where('maxQuantity', '>=', $quantity)
        ->first();

    if (!$pin_cost) {
        return response()->json([
            'message' => 'No pricing range found for this quantity.'
        ], 404);
    }

    // ✅ Calculate ONCE
    $costPerPin = $pin_cost->costPerPin;
    $totalAmount = $quantity * $costPerPin;

    $generatedPins = [];

    try {
        $academicYear = AcademicYear::where('schoolId', $schoolId)
            ->where('isActive', true)
            ->firstOrFail();

        $term = Term::where('academicYearId', $academicYear->academicYearId)
            ->where('isActive', true)
            ->firstOrFail();

        DB::transaction(function () use (
            $schoolId,
            $academicYear,
            $term,
            $quantity,
            $costPerPin,
            $totalAmount,
            &$generatedPins
        ) {

            for ($i = 0; $i < $quantity; $i++) {
                $rawPin = strtoupper(Str::random(8));

                ParentAccess::create([
                    'schoolId' => $schoolId,
                    'academicYearId' => $academicYear->academicYearId,
                    'termId' => $term->termId,
                    'pinHash' => Hash::make($rawPin),
                    'pinLookup' => hash('sha256', $rawPin),
                    'pinLast4' => substr($rawPin, -4),
                    'paymentMethod' => 'cash',
                    'amountPaid' => $costPerPin, // per PIN
                    'expiresAt' => $term->endDate,
                    'isActive' => true,
                    'failedAttempts' => 0,
                ]);

                $generatedPins[] = $rawPin;
            }

            // ✅ Update wallet ONCE
            $school = School::where('schoolId', $schoolId)->first();
            $school->walletBalance += $totalAmount;
            $school->save();
        });

        return response()->json([
            'message' => 'PINs generated successfully',
            'pins' => $generatedPins,
            'quantity' => $quantity,
            'costPerPin' => $costPerPin,
            'totalAmount' => $totalAmount,
            'term' => $term->termName,
            'academicYear' => $academicYear->academicYearName,
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'PIN generation failed',
            'error' => $e->getMessage(),
        ], 500);
    }
}
public function getPins(Request $request)
{
    $schoolId = $request->header('X-School-ID');

    try {
        $academicYear = AcademicYear::where('schoolId', $schoolId)
            ->where('isActive', true)
            ->firstOrFail();

        $term = Term::where('academicYearId', $academicYear->academicYearId)
            ->where('isActive', true)
            ->firstOrFail();

        $pins = ParentAccess::where('schoolId', $schoolId)
            ->where('academicYearId', $academicYear->academicYearId)
            ->where('termId', $term->termId)
            ->get(['parentAccessId', 'pinLast4', 'paymentMethod', 'amountPaid', 'expiresAt', 'isActive']);

        return response()->json([
            'message' => 'PINs retrieved successfully',
            'pins' => $pins,
            'term' => $term->termName,
            'academicYear' => $academicYear->academicYearName,
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Failed to retrieve PINs',
            'error' => $e->getMessage(),
        ], 500);
    }
}


public function calculatePrice(Request $request)
{
    $schoolId = $request->header('X-School-ID');

    $validated = $request->validate([
        'quantity' => 'required|integer|min:1|max:500',
    ]);

    $pin_cost = PinCost::where('schoolId', $schoolId)
        ->where('minQuantity', '<=', $validated['quantity'])
        ->where('maxQuantity', '>=', $validated['quantity'])
        ->first();

    if (!$pin_cost) {
        return response()->json([
            'message' => 'No pricing range found for this quantity.'
        ], 404);
    }

    $totalAmount = $validated['quantity'] * $pin_cost->costPerPin;

    return response()->json([
        'totalAmount' => $totalAmount,
        'costPerPin' => $pin_cost->costPerPin,
        'quantity' => $validated['quantity']
    ]);

}

}