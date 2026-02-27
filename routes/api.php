<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PsychomotorController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\StateController;
use App\Http\Controllers\TermsController;
use App\Http\Controllers\AcademicYearController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\MinistryController;
use App\Http\Controllers\AgentsController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SuggestedFollowersController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\ProductRequestController;
use App\Http\Controllers\TransactionsController;
use App\Http\Controllers\ProjectsController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\AffectiveController;
use App\Http\Controllers\FarmersController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\ServicesController;
use App\Http\Controllers\UserEmailController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ClassController;
use App\Http\Controllers\ClassController as ControllersClassController;
use App\Http\Controllers\ResetPasswordController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\OtpController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\RecruitmentJobApplicationsController;
use App\Http\Controllers\LearningController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\InstructorCourseController;
use App\Http\Controllers\GradingController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\InvoicePdfController;
use App\Http\Controllers\SchoolsController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\ParentController;
use App\Http\Controllers\StudentController;
use App\Models\Currency;
use App\Models\PaymentGateway;
use App\Models\Plans;
use Tymon\JWTAuth\Claims\Custom;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\PlansController;
use App\Http\Controllers\AdminDashboardController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::post('/resend-otp', [OtpController::class, 'resendOtp']);
Route::post('/verify-otp', [OtpController::class, 'verifyOtp']);
Route::post('/setup-password', [AuthController::class, 'setupPassword']);

Route::post('/signup', [AuthController::class, 'signup2']);
Route::post('/signin', [AuthController::class, 'signin']);
Route::post('/signin-check', [AuthController::class, 'signin']);
Route::post('/logout', [AuthController::class, 'logout']);
Route::post('/refresh', [AuthController::class, 'refresh']);
Route::get('/users/profile', [AuthController::class, 'profile'])->middleware('auth.jwt');
Route::get('/roles', [RolesController::class, 'index']);
Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLink']);
Route::post('/reset-password', [ResetPasswordController::class, 'reset'])->name('api.password.reset');

Route::get('/currencies', function(){
    $currencies = Currency::orderBy('currencyId')->get()->makeHidden([ 'created_at', 'updated_at', 'deleted_at']);
    return response()->json($currencies);
});

Route::get('/payment-gateways', function(){
    $gateways = PaymentGateway::orderBy('gatewayId')->get()->makeHidden([ 'created_at', 'updated_at', 'deleted_at']);
    return response()->json($gateways);
});

Route::get('/subscription-plans', function(){
    $plans = Plans::with('currency_detail')->orderBy('planId')->get()->makeHidden([ 'created_at', 'updated_at', 'deleted_at']);
    return response()->json($plans);
});

Route::get('/invoices/admin-summary', [InvoiceController::class, 'adminInvoiceSummary']);
Route::get('/admin/invoice-status-breakdown', [InvoiceController::class, 'invoiceStatusBreakdown']);
Route::get('/admin/top-schools', [InvoiceController::class, 'topSchools']);
Route::get('/admin/revenue-trends', [InvoiceController::class, 'revenueTrends']);
Route::get('/admin/payment-method-breakdown', [InvoiceController::class, 'paymentMethodBreakdown']);
Route::get('/admin/overdue-invoices-summary', [InvoiceController::class, 'overdueInvoicesSummary']);
Route::get('/admin/currency-distribution', [InvoiceController::class, 'currencyDistribution']);

Route::get('/users', [UsersController::class, 'index']);
Route::middleware(['auth.jwt', 'school'])->group(function () {

    Route::get('/user', function () {
        // $user = auth()->user(); // Use the 'api' guard for JWT
        $user = auth()->user()->load([
        'default_school',
        'user_role'
    ]);
        $school = $user->activeSchool();
        // $schools =$user->load('default_school');

        return response()->json([
            'user' => [
                // 'id' => (string) $user->id,
                'id' => $user->id,
                'full_name' => trim($user->firstName . ' ' . $user->lastName . ' ' . ($user->otherNames ?? '')),
                'role' => $user->user_role->roleName ?? null,
                'phoneNumber' => $user->phoneNumber,
                'email' => $user->email,
                'default_school' => $user->default_school,
                'schoolId' => $school->schoolId ?? '',
                'user_plan' => $user->current_plan->planName ?? "",
                // $schools,
            ]
        ]);
    });

// Route::get('/plans', function(){
//     $plans = Plans::orderBy('planId')->with('currency_detail', 'isSubscribed')->get();
//     return response()->json($plans);
// });

Route::get('/plans', function () {
    $user = auth()->user();

    $plans = Plans::orderBy('planId')
        ->with('currency_detail')
        ->get()
        ->map(function ($plan) use ($user) {
            $plan->is_subscribed = $user
                ? $plan->subscriptions()
                    ->where('userId', $user->id)
                    ->where('status', 'active')
                    ->exists()
                : false;

            return $plan;
        });

    return response()->json($plans);
    });

    Route::post('/subscription-plans', [PlansController::class, 'store']);
    Route::patch('/subscription-plans/{planId}', [PlansController::class, 'update']);

    Route::post('/currencies', function () {
        $validatedData = request()->validate([
            'currencyName' => 'required|string|max:255',
            'currencyCode' => 'required|string|max:10',
            'currencySymbol' => 'required|string|max:10',
            'country' => 'required|string|max:255',
        ]);
    
        $currency = Currency::create($validatedData);
    
        return response()->json(['message' => 'Currency created successfully', 'currency' => $currency], 201);
    });

    Route::patch('/currencies/{currencyId}', function ($currencyId) {
        
        $validatedData = request()->validate([
            'currencyName' => 'sometimes|string|max:255',
            'currencyCode' => 'sometimes|string|max:10',
            'currencySymbol' => 'sometimes|string|max:10',
            'country' => 'sometimes|string|max:255',
        ]);
        Currency::where('currencyId', $currencyId)->update($validatedData);
        return response()->json(['message' => 'Currency updated successfully']);
    });

    Route::post('/payment-gateways', function () {
        $validatedData = request()->validate([
            'paymentGatewayName' => 'required|string|max:255',
            'url' => 'nullable|string',
        ]);
    
        $gateway = PaymentGateway::create($validatedData);
    
        return response()->json(['message' => 'Payment gateway created successfully', 'gateway' => $gateway], 201);
    }); 

    Route::patch('/payment-gateways/{gatewayId}', function ($gatewayId) {
        
        $validatedData = request()->validate([
            'paymentGatewayName' => 'sometimes|string|max:255',
            'url' => 'sometimes|string',
        ]);
        PaymentGateway::where('gatewayId', $gatewayId)->update($validatedData);
        return response()->json(['message' => 'Payment gateway updated successfully']);
    });
  
    Route::get('profile', [UsersController::class, 'userProfile']);
    Route::patch('/profile', [UsersController::class, 'updateUser']);
    Route::patch('/profile/password', [UsersController::class, 'updatePassword']);

    Route::put('/schools/{schoolId}', [SchoolsController::class, 'update']);
    Route::patch('/schools/{schoolId}/status', [SchoolsController::class, 'toggleSchoolStatus']);

    Route::patch('/schools/{schoolId}/set-default', [SchoolsController::class, 'setDefaultSchool']);

    // User profile routes
    Route::get('profile/biodata', [UsersController::class, 'userBiodataProfile']);
    Route::get('profile/education', [UsersController::class, 'userEducationProfile']);
    Route::get('profile/experience', [UsersController::class, 'userExperienceProfile']);
    Route::get('profile/skills', [UsersController::class, 'userSkillsProfile']);
    Route::get('profile/drivers-license', [UsersController::class, 'userDriversLicenseProfile']);

    Route::post('profile/biodata', [UsersController::class, 'storeUserBiodata']);
    Route::post('profile/education', [UsersController::class, 'storeUserEducation']);
    Route::post('profile/experience', [UsersController::class, 'storeUserExperience']);
    Route::post('profile/skills', [UsersController::class, 'storeUserSkills']);
    Route::post('profile/drivers-license', [UsersController::class, 'storeUserDriversLicense']);

    Route::delete('profile/education/{id}', [UsersController::class, 'deleteUserEducation']);
    Route::delete('profile/experience/{id}', [UsersController::class, 'deleteUserExperience']);
    Route::delete('profile/skills/{id}', [UsersController::class, 'deleteUserSkills']);
    Route::delete('profile/drivers-license/{id}', [UsersController::class, 'deleteUserDriversLicense']);

    Route::post('profile/upload-image', [UsersController::class, 'uploadProfileImage']);
    Route::post('profile/upload-cover-image', [UsersController::class, 'uploadCoverImage']);

    // Schools
    Route::get('schools', [SchoolsController::class, 'index']);
    Route::post('schools', [SchoolsController::class, 'store']);
    Route::get('schools/user-schools', [SchoolsController::class, 'mySchools']);
    // Route::post('/schools/{schoolId}', [SchoolsController::class, 'update']);
   
    Route::get('classes/school', [ClassController::class, 'getSchoolClasses']);
    Route::get('classes/school/{classId}/students', [ClassController::class, 'getSchoolClassStudents']);
    Route::post('classes/school', [ClassController::class, 'storeSchoolClass']);
    Route::patch('classes/school/{classId}', [ClassController::class, 'updateClass']);
    Route::delete('classes/school/{classId}', [ClassController::class, 'destroyClass']);
    Route::patch('classes/school/{classId}/assign-teachers', [ClassController::class, 'assignTeacher']);
    Route::get('classes/admin', [ClassController::class, 'index']);
    Route::get('classes/{classId}/subjects', [SubjectController::class, 'getTeachersSubjects']);


    Route::get('school/teachers', [TeacherController::class, 'getSchoolTeachers']);
    Route::post('school/teachers', [TeacherController::class, 'storeSchoolTeacher']);
    Route::patch('school/teachers/{classId}', [TeacherController::class, 'updateTeacher']);
    Route::delete('school/teachers/{classId}', [TeacherController::class, 'destroyTeacher']);

    Route::get('school/students', [StudentController::class, 'getSchoolStudents']);
    Route::post('school/students', [StudentController::class, 'storeSchoolStudent']);
    Route::patch('school/students/{studentId}', [StudentController::class, 'updateStudent']);
    Route::delete('school/students/{studentId}', [StudentController::class, 'destroyStudent']);


    Route::get('school/subjects', [SubjectController::class, 'getSchoolSubjects']);
    Route::post('school/subjects', [SubjectController::class, 'storeSchoolSubject']);
    Route::patch('school/subjects/{subjectId}', [SubjectController::class, 'updateSubject']);
    Route::patch('school/subjects/{subjectId}/assign-teachers', [SubjectController::class, 'assignTeacher']);
    Route::patch('school/subjects/{subjectId}/assign-class', [SubjectController::class, 'assignClassToSubject']);
    // Route::patch('school/subjects/{subjectId}', [SubjectController::class, 'updateSubject']);
    Route::delete('school/subjects/{subjectId}', [SubjectController::class, 'destroySubject']);

    

    Route::get('school/parents', [ParentController::class, 'getSchoolParents']);
    Route::post('school/parents', [ParentController::class, 'storeSchoolParent']);
    Route::patch('school/parents/{parentId}', [ParentController::class, 'updateParent']);
    Route::patch('school/parents/{parentId}/assign-teachers', [ParentController::class, 'assignParent']);
    Route::delete('school/parents/{parentId}', [ParentController::class, 'destroyParent']);

    Route::prefix('academic-years')->group(function () {
        Route::get('/', [AcademicYearController::class, 'index']);
        Route::post('/', [AcademicYearController::class, 'store']);
        Route::patch('/{id}/activate', [AcademicYearController::class, 'activate']);
        Route::patch('/{id}/close', [AcademicYearController::class, 'close']);
    });

    Route::prefix('terms')->group(function () {
        Route::get('/', [TermsController::class, 'index']);
        Route::post('/', [TermsController::class, 'store']);
        Route::patch('/{id}/activate', [TermsController::class, 'activate']);
    });

    Route::prefix('attendance')->group(function () {

        Route::get('/', [AttendanceController::class, 'index']);
        Route::post('/open', [AttendanceController::class, 'openSession']);
        Route::post('/mark', [AttendanceController::class, 'markAttendance']);
        Route::patch('/close/{id}', [AttendanceController::class, 'closeSession']);
        Route::get('/session/{id}', [AttendanceController::class, 'getSessionAttendance']);
        Route::get('/student/{studentId}/summary', [AttendanceController::class, 'studentSummary']);
    });

    Route::prefix('assessment')->group(function () {
    Route::get('/', [AssessmentController::class, 'index']);
    Route::post('/', [AssessmentController::class, 'createAssessment']);
    Route::patch('/{assessmentId}', [AssessmentController::class, 'update']);
    Route::post('/{assessmentId}/scores', [AssessmentController::class, 'storeScores']);
    Route::get('/scores', [AssessmentController::class, 'getSubjectAssessmentScores']);
    });


   

Route::prefix('domains')->group(function () {
    Route::get('/', [AffectiveController::class, 'domains']);
    Route::get('/affective', [AffectiveController::class, 'affectiveDomain']);
    Route::post('/save', [AffectiveController::class, 'saveDomain']);
    Route::delete('/{id}', [AffectiveController::class, 'deleteDomain']);
    Route::get('/affective/records', [AffectiveController::class, 'getAffectiveClassScores']);
    Route::post('/affective/{domainId}/scores', [AffectiveController::class, 'storeDomainScores']);


    Route::post('/scores', [AffectiveController::class, 'submitScores']);
    Route::get('/scores/student/{studentId}/{schoolId}', [AffectiveController::class, 'getStudentScores']);
});

Route::prefix('psychomotor')->group(function () {
    Route::get('/', [PsychomotorController::class, 'domains']);
    Route::get('/psychomotor', [PsychomotorController::class, 'psychomotorDomain']);
    Route::post('/save', [PsychomotorController::class, 'saveDomain']);
    Route::delete('/{id}', [PsychomotorController::class, 'deleteDomain']);
    Route::get('/psychomotor/records', [PsychomotorController::class, 'getPsychomotorClassScores']);
    Route::post('/psychomotor/{domainId}/scores', [PsychomotorController::class, 'storeDomainScores']);


    Route::post('/scores', [PsychomotorController::class, 'submitScores']);
    Route::get('/scores/student/{studentId}/{schoolId}', [PsychomotorController::class, 'getStudentScores']);
});


Route::prefix('grading')->group(function () {
    Route::get('/', [GradingController::class, 'index']);
    Route::post('/', [GradingController::class, 'store']);
    Route::put('/{gradingId}', [GradingController::class, 'update']);
    Route::delete('/{gradingId}', [GradingController::class, 'destroy']);
});

    Route::get('/subscribers', [SubscriptionController::class, 'index']);
    Route::get('/my-subscriptions', [SubscriptionController::class, 'mySubscriptions']);

    // Support Routes
    Route::get('/support/tickets', [SupportController::class, 'index']);
    Route::post('/support/tickets', [SupportController::class, 'store']);
    Route::post('/support/tickets/{ticketId}/reply', [SupportController::class, 'reply']);
    Route::post('/support/tickets/{ticketId}/admin-reply', [SupportController::class, 'adminReply']);
    Route::get('/support/tickets/all', [SupportController::class, 'indexAdmin']);
    Route::patch('/support/tickets/{ticketId}/status', [SupportController::class, 'updateTicketStatus']);

    Route::post('/subscribe/{planId}', [SubscriptionController::class, 'create']); // Add auth
     Route::put('/subscriptions/{planId}/cancel', [SubscriptionController::class, 'cancel']); // Add auth
    Route::patch('/subscriptions/{subscriptionId}/activate', [SubscriptionController::class, 'activate']);
    Route::patch('/subscriptions/{subscriptionId}/deactivate', [SubscriptionController::class, 'deactivate']);

    Route::get('/users/{id}/profile', [UsersController::class, 'profile']);

    Route::post('/users/{id}/send-email', [UserEmailController::class, 'sendSingle']);
    Route::post('/users/broadcast-email', [UserEmailController::class, 'broadcast']);
});

Route::prefix('invoices')->group(function () {
    Route::get('/{id}/pdf', [InvoicePdfController::class, 'download']);
    Route::get('/{id}/stream-pdf', [InvoicePdfController::class, 'stream']);
    Route::get('/{id}/generate-pdf', [InvoicePdfController::class, 'generate']);
    Route::post('/{id}/send-email', [InvoicePdfController::class, 'sendEmail']);
});

Route::prefix('receipts')->group(function () {
    Route::get('/{id}/pdf', [InvoicePdfController::class, 'downloadReceipt']);
    Route::get('/{id}/stream-pdf', [InvoicePdfController::class, 'stream']);
    Route::get('/{id}/generate-pdf', [InvoicePdfController::class, 'generate']);
    Route::post('/{id}/send-email', [InvoicePdfController::class, 'sendReceiptEmail']);
});

Route::post('/flutterwave/webhook', [WebhookController::class, 'handle']);
Route::get('/subscription/verify-redirect', [SubscriptionController::class, 'verifyRedirect']);

Route::middleware(['auth.jwt'])->group(function () {
    Route::get('/admin/dashboard-counts', [AdminDashboardController::class, 'dashboardCounts']);
    Route::get('/admin/dashboard-details/users', [AdminDashboardController::class, 'usersDetails']);
    Route::get('/admin/dashboard-details/invoices', [AdminDashboardController::class, 'invoicesDetails']);
    Route::get('/admin/dashboard-details/receipts', [AdminDashboardController::class, 'receiptsDetails']);
    Route::get('/admin/dashboard-details/businesses', [AdminDashboardController::class, 'businessesDetails']);

});

