<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Staff;
use App\Models\StaffType;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Mail\WelcomeEmail;
use Illuminate\Support\Facades\Mail;
use DB;
use Illuminate\Http\JsonResponse;
use App\Models\Lgas;
use App\Models\StateCoordinators;
use App\Models\CommunityLead;
use App\Models\DriversLicense;
use App\Models\Education;
use App\Models\Skills;
use App\Models\ClassTeacher;
use Illuminate\Queue\Worker;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;


use App\Models\SchoolClass;


class UsersController extends Controller
{
    public function index()
    {
        $users = User::with('user_role', 'current_plan')->get();
        return response()->json($users);

    }










        public function update(Request $request, $id)
{
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user->update($request->all());
        return response()->json($user);
    }

   public function destroy($id): JsonResponse
    {
        return DB::transaction(function () use ($id) {
            // Find the user
            $user = User::find($id);
            if (!$user) {
                return response()->json(['message' => 'Staff not found'], 404);
            }

            // Find the associated staff record
            // $staff = Staff::where('userId', $id)->first();
            // if (!$staff) {
            //     return response()->json(['message' => 'Associated staff record not found'], 404);
            // }

            // Delete both records
            // $staff->delete();
            $user->delete();

            return response()->json(['message' => 'User deleted successfully']);
        }, 5);
    }




    public function uploadProfileImage(Request $request)
    {
        $request->validate([
            'profileImage' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048', // max 2MB
        ]);

        $user = auth()->user();

        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }

        // Handle file upload
       if ($request->hasFile('profileImage')) {
    $image = $request->file('profileImage');
    $imageName = time() . '_' . $user->id . '.' . $image->getClientOriginalExtension();

    // Store using Laravel storage
    $path = $image->storeAs('profile-images', $imageName, 'public');

    // Save path
    $user->profileImage = $path; // this becomes "profile-images/xxxx.jpg"
    $user->save();

    return response()->json([
        'message' => 'Profile image uploaded successfully',
        'profileImage' => $user->profileImage
    ]);
}


        return response()->json(['message' => 'No image uploaded'], 400);
    }






    public function currentUser(Request $request)
{
    $user = Auth::user();

    return response()->json([
        'user' => [
            'id' => $user->id,
            'full_name' => $user->firstName . ' ' . $user->lastName,
            'role' => $user->role,
            'phoneNumber' => $user->phoneNumber,
            'email' => $user->email,
        ]
    ]);
}


// public function userProfile(Request $request)
// {
//     $authUser = Auth::user(); // logged-in user
//     $user = User::with('user_role')->find($authUser->id); // Eager load role

//     if (!$user) {
//         return response()->json([
//             'message' => 'User not found'
//         ], 404);
//     }

//     return response()->json([
//         'id' => $user->id,
//         'firstName' => $user->firstName,
//         'lastName' => $user->lastName,
//         'email' => $user->email,
//         'phoneNumber' => $user->phoneNumber,
//         'role' => $user->user_role->roleName ?? 'Member', // Get role name with fallback
//         'user_plan' => $user->current_plan->planName ?? 'Free'

//     ]);
// }


public function userProfile(Request $request): JsonResponse
{
    $user = $request->user();
    $schoolId = $request->header('X-School-ID');

    $isClassTeacher = false;
    $classHead = [];

    // 🔥 MULTIPLE CLASS SUPPORT
    if ($user->role === 'teacher' && $user->teacher) {

        $classes = SchoolClass::where('schoolId', $schoolId)
            ->whereHas('class_teachers', function ($query) use ($user) {
                $query->where('teacherId', $user->teacher->teacherId);
            })
            ->get();

        if ($classes->isNotEmpty()) {
            $isClassTeacher = true;

            $classHead = $classes->map(function ($class) {
                return [
                    'className' => $class->className,
                    'classId'   => $class->classId
                ];
            });
        }
    }

    // 🔥 PERMISSION FLAGS
    $canEditResults = false;
    $canViewReports = false;

    switch ($user->role) {
        case 'admin':
            $canEditResults = true;
            $canViewReports = true;
            break;

        case 'teacher':
            $canEditResults = true; // teachers can edit results
            $canViewReports = true;
            break;

        case 'student':
            $canEditResults = false;
            $canViewReports = true; // can view own report
            break;

        default:
            $canEditResults = false;
            $canViewReports = false;
            break;
    }

    return response()->json([
        'firstName' => $user->firstName,
        'lastName'  => $user->lastName,
        'email'     => $user->email,
        'phoneNumber' => $user->phoneNumber,
        'role'      => $user->role,

        'isClassTeacher' => true,

        // 🔥 Now returns ARRAY of classes
        'classHead' => $isClassTeacher ? $classHead : null,

        'signatureUrl' => $user->teacher->signature ?? null,

        // 🔥 PERMISSIONS OBJECT
        'permissions' => [
            'canEditResults' => $canEditResults,
            'canViewReports' => $canViewReports
        ]
    ]);
}

public function updateSignature(Request $request)
{
    $user = Auth::user();

    if (!$user->teacher) {
        return response()->json([
            'message' => 'User is not a teacher'
        ], 403);
    }

    $validated = $request->validate([
        'signature' => 'required|image|mimes:png,jpg,jpeg,svg|max:2048',
    ]);

    $teacher = $user->teacher;

    if ($request->hasFile('signature')) {
        if ($teacher->signature) {
            Storage::disk('public')->delete($teacher->signature);
        }

        $teacher->signature = $request->file('signature')
            ->store('teacher-signatures', 'public');

        $teacher->save();
    }

    return response()->json([
        'message' => 'Signature updated successfully',
        'user' => [
            'firstName'   => $user->fresh()->firstName,
            'lastName'    => $user->fresh()->lastName,
            'email'       => $user->email,
            'phoneNumber' => $user->phoneNumber,
            'signature'   => $teacher->signature,
        ]
    ]);
}


public function updateUser(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'firstName'   => 'required|string|max:255',
            'lastName'    => 'required|string|max:255',
            'phoneNumber' => 'nullable|string|max:20',
        ]);

        $user->update([
            'firstName' => $validated['firstName'],
            'lastName'  => $validated['lastName'],
            'phoneNumber'      => $validated['phoneNumber'] ?? null,
        ]);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user'    => [
                'firstName'   => $user->fresh()->firstName,
                'lastName'    => $user->fresh()->lastName,
                'email'       => $user->email,
                'phoneNumber' => $user->phoneNumber,
            ]
        ]);
    }

    /**
     * PATCH /api/profile/password
     * Change user's password
     */
    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'currentPassword' => ['required', function ($attribute, $value, $fail) use ($user) {
                if (!Hash::check($value, $user->password)) {
                    $fail('The current password is incorrect.');
                }
            }],
            'newPassword' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $user->update([
            'password' => Hash::make($validated['newPassword']),
        ]);

        return response()->json([
            'message' => 'Password changed successfully'
        ]);
    }

}
