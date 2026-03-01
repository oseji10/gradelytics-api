<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\House;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SchoolsController extends Controller
{
    public function index(Request $request)
    {
        // return $schoolId = $request->header('X-School-ID');

        $schools = School::all();
        return response()->json($schools);
    }

    public function getClubs(Request $request)
    {
        $schoolId = $request->header('X-School-ID');
        $clubs = Club::where('schoolId', $schoolId)->get();
        return response()->json($clubs);
    }

    public function getHouses(Request $request)
    {
        $schoolId = $request->header('X-School-ID');
        $houses = House::where('schoolId', $schoolId)->get();
        return response()->json($houses);
    }

public function mySchools(Request $request)
    {
        // return $schoolId = $request->header('X-School-ID');

        $schools = School::with('currency', 'payment_gateway')
        ->where('ownerId', auth()->id())
        ->get();
        return response()->json($schools);
    }


     public function storeClub(Request $request)
{
    $schoolId = $request->header('X-School-ID');

    $validated = $request->validate([
        'clubName' => 'required|string|max:255',
    ]);

    $club = Club::create([
        'clubName' => $validated['clubName'], // map frontend name → db clubName
        'schoolId' => $schoolId
    ]);

    return response()->json($club, 201);
}

      public function storeHouse(Request $request)
    {
        $schoolId = $request->header('X-School-ID');
        $validated = $request->validate([
            'houseName' => 'required|string|max:255',
        ]);
        $house = House::create([
        'houseName' => $validated['houseName'], // map frontend name → db clubName
        'schoolId' => $schoolId
    ]);
        return response()->json($house, 201); 
    }

    public function store(Request $request)
    {

        $user = Auth::user();
        if (!$user->canCreateSchool()) {
            return response()->json([
                'message' => 'Sorry you can\'t add any more businesses. Upgrade to premium to add more businesses.'
            ], 403);
        }
        // Validate the request data
        $validated = $request->validate([
            'schoolName' => 'required|string|max:255',
            'schoolAddress' => 'nullable|string',
            // 'taxId' => 'nullable|string',
            'schoolEmail' => 'required|email',
            'schoolPhone' => 'nullable|string',
            'schoolLogo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'authorizedSignature' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            // 'countryCode' => 'required|string',
            // 'timezone' => 'required|string',
            // 'gatewayPreference' => 'nullable|integer|exists:payment_gateways,gatewayId',
            // 'companyStatus' => 'required|in:active,inactive',
            // 'currency' => 'required|integer|exists:currencies,currencyId',
        ]);

        // Handle logo upload
        if ($request->hasFile('schoolLogo')) {
            $logoFile = $request->file('schoolLogo');
            $logoPath = $logoFile->store('school-logos', 'public');
            $validated['schoolLogo'] = $logoPath;
        } else {
            $validated['schoolLogo'] = null;
        }


        // Handle signature upload
        if ($request->hasFile('authorizedSignature')) {
            $logoFile = $request->file('authorizedSignature');
            $logoPath = $logoFile->store('signatures', 'public');
            $validated['authorizedSignature'] = $logoPath;
        } else {
            $validated['authorizedSignature'] = null;
        }

        $owner = auth()->id();
        $validated['addedBy'] = $owner;
        $company = School::create($validated);

        // Return a response, typically JSON
        return response()->json($company, 201); // HTTP status code 201: Created
    }


    public function show($id)
    {
        $company = Company::find($id);
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }
        return response()->json($company);
    }


    public function myCompanies()
    {
        $company = Company::where('createdBy', auth()->id())
            ->first();
        if (!$company) {
            return response()->json(['message' => 'No companies found'], 404);
        }
        return response()->json($company);
    }

    // public function update(Request $request, $id)
    // {
    //     // Find the company
    //     $company = Company::findOrFail($id);

    //     // Validate the request data
    //     $validated = $request->validate([
    //         'companyName' => 'required|string|max:255',
    //         'companyDescription' => 'required|string',
    //         'companyAddress' => 'required|string',
    //         'companyEmail' => 'required|email',
    //         'companyPhone' => 'nullable|string',
    //         'companyWebsite' => 'nullable|url',
    //         'companyIndustry' => 'required|string',
    //         'companySize' => 'required|string',
    //         'companyLocation' => 'required|string',
    //         'companyFoundedYear' => 'nullable|integer|min:1900|max:' . date('Y'),
    //         'companyStatus' => 'required|in:active,inactive',
    //         'companyLogo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // 5MB max
    //     ]);

    //     // Handle logo upload
    //     if ($request->hasFile('companyLogo')) {
    //         // Delete old logo if exists
    //         if ($company->companyLogo) {
    //             Storage::disk('public')->delete($company->companyLogo);
    //         }

    //         $logoFile = $request->file('companyLogo');
    //         $logoPath = $logoFile->store('company-logos', 'public');
    //         $validated['companyLogo'] = $logoPath;
    //     } else {
    //         // Keep the existing logo if no new file is uploaded
    //         $validated['companyLogo'] = $company->companyLogo;
    //     }

    //     // Update the company
    //     $company->update($validated);

    //     return response()->json($company, 200);
    // }

    public function destroy($id)
    {
        $company = Company::find($id);
        if (!$company) {
            return response()->json(['message' => 'Company not found'], 404);
        }

        $company->delete();
        return response()->json(['message' => 'Company deleted successfully']);
    }



public function update(Request $request)
{
    $schoolId = $request->header('X-School-ID');
    // Handle method spoofing (for file uploads)
    if ($request->has('_method') && strtoupper($request->_method) === 'PUT') {
        // Laravel will now parse files correctly because it's treated as POST
    }

    $validated = $request->validate([
        'schoolName' => 'required|string|max:255',
        'schoolEmail' => 'required|email|max:255',
        'schoolAddress' => 'nullable|string',
        // 'taxId' => 'nullable|string',
        'schoolPhone' => 'nullable|string|max:20',
         'schoolLogo' => 'sometimes|file|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
'authorizedSignature' => 'sometimes|file|image|mimes:png,jpg,jpeg,svg|max:2048',
// 'timezone' => 'required|string|max:100',
        // 'currency' => 'required|exists:currencies,currencyId',
        // 'gatewayPreference' => 'required|exists:payment_gateways,gatewayId',
        'status' => 'sometimes|in:active,inactive',
    ]);

    $user = auth()->user();

    $school = School::where('schoolId', $schoolId)
        // ->where('ownerId', $user->id)
        ->firstOrFail();

    $updateData = $validated;

    // Handle logo
    if ($request->hasFile('schoolLogo')) {
        if ($school->schoolLogo) {
            Storage::disk('public')->delete($school->schoolLogo);
        }
        $updateData['schoolLogo'] = $request->file('schoolLogo')->store('school-logos', 'public');
        
    }

    // Handle signature
    if ($request->hasFile('authorizedSignature')) {
        if ($school->authorizedSignature) {
            Storage::disk('public')->delete($school->authorizedSignature);
        }
        $updateData['authorizedSignature'] = $request->file('authorizedSignature')->store('signatures', 'public');
    }

    $school->update($updateData);
    // $school->load('currency', 'payment_gateway');

    return response()->json([
        'message' => 'Business updated successfully',
        'school' => $school,
    ]);
}


public function setDefaultSchool(Request $request, $schoolId)
{
    $request->validate([
        // Optional: add any validation if sending JSON body
    ]);

    $user = Auth::user();
    $schoolId = (int) $schoolId;

    // Start a database transaction for atomicity
    return DB::transaction(function () use ($user, $schoolId) {
        // Check if the school belongs to the authenticated user
        $school = $user->default_school()->where('schools.schoolId', $schoolId)->first();

        if (!$school) {
            return response()->json([
                'message' => 'School not found or you do not have access to it.'
            ], 404);
        }

        if ($school->isDefault === 1) {
            return response()->json([
                'message' => 'This school is already the default.',
                'school' => $school
            ], 200);
        }

        // Unset current default school
        $user->default_school()->where('isDefault', 1)->update(['isDefault' => 0]);

        // Set the new one as default
        $school->update(['isDefault' => 1]);

        return response()->json([
            'message' => 'Default school switched successfully.',
            'school' => $school->makeHidden(['authorizedSignature']) // optional: hide sensitive fields
        ], 200);
    });
}

public function toggleSchoolStatus(Request $request, $schoolId)
{
    $validated = $request->validate([
        'status' => 'required|in:active,inactive',
    ]);

    $user = auth()->user();

    $school = School::where('schoolId', $schoolId)
        ->where('ownerId', $user->id)
        ->firstOrFail();

    // 🚫 Prevent disabling the default school
    if ($school->isDefault == 1 && $request->status === 'inactive') {
        return response()->json([
            'message' => 'You cannot deactivate the default business. Switch to another business or add a new one and make it the default before disabling this',
        ], 422);
    }

    $school->update([
        'status' => $request->status,
    ]);

    return response()->json([
        'message' => 'School status updated successfully',
        'school' => $school,
    ]);
}


}
