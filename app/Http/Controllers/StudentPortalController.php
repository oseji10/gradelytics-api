<?php
namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\User;
use App\Models\Student;
use App\Models\AcademicYear;
use App\Models\Term;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Models\School;
use App\Models\CbtExamAttempt;

use Illuminate\Support\Carbon;
use App\Models\CbtExam;

class StudentPortalController extends Controller
{
public function studentLogin(Request $request)
{
    $request->validate([
        'schoolId' => 'required|integer',
        'admissionNumber' => 'required|string',
        'password' => 'required|string',
    ]);

    $schoolId = (int) $request->schoolId;
    // $admissionNumber = trim($request->admissionNumber);
    $password = $request->password;

    $admissionNumber = strtoupper(trim($request->admissionNumber));


    // Get active academic year and term
    $session = AcademicYear::where('schoolId', $schoolId)
        ->where('isActive', true)
        ->first();

    $term = Term::where('schoolId', $schoolId)
        ->where('isActive', true)
        ->first();

    if (!$session || !$term) {
        return response()->json([
            'message' => 'No active academic year or term found'
        ], 404);
    }

    // Find student within the school by admission number
    $student = Student::with('user')
    ->where('schoolId', $schoolId)
    ->whereRaw('UPPER(admissionNumber) = ?', [$admissionNumber])
    ->first();

    if (!$student || !$student->user) {
        return response()->json([
            'message' => 'Invalid admission number or password'
        ], 401);
    }

     $user = $student->user;
    \Log::info('Student login attempt', [
        'schoolId' => $schoolId,
        'admissionNumber' => $admissionNumber,
        'user_id' => $user->id,
        'user_role' => $user->user_role,
    ]);
   $roleName = strtoupper(trim($user->user_role?->roleName ?? ''));

if ($roleName !== 'STUDENT') {
    return response()->json([
        'message' => 'Unauthorized account type',
        'debug' => [
            'user_id' => $user->id,
            'role_id' => $user->user_role,
            'role_name' => $user->user_role?->roleName,
        ]
    ], 403);
}

    // Optional: block inactive users
    if (isset($user->isActive) && !$user->isActive) {
        return response()->json([
            'message' => 'Account is inactive. Contact school admin'
        ], 403);
    }

    // Verify password
    if (!Hash::check($password, $user->password)) {
        return response()->json([
            'message' => 'Invalid admission number or password'
        ], 401);
    }

    // Optional: update last login
    $user->update([
        'lastLoginAt' => now(),
    ]);

    // Build JWT payload
    $payload = JWTAuth::factory()->customClaims([
        'sub' => $user->id,
        'role' => 'student_access',
        'school_id' => $schoolId,
        'academic_year_id' => $session->academicYearId,
        'term_id' => $term->termId,
        'student_id' => $student->studentId,
        'user_id' => $user->id,
    ])->setTTL(120)->make();

    $token = JWTAuth::encode($payload)->get();

    // Store token in cookie
    $secure = app()->environment('production');

    $cookie = cookie(
        'student_token',
        $token,
        120,
        '/',
        null,
        $secure,
        true,
        false,
        $secure ? 'None' : 'Lax'
    );

    return response()->json([
        'message' => 'Login successful',
        'student' => [
            'studentId' => $student->studentId,
            'admissionNumber' => $student->admissionNumber,
            'firstName' => $student->firstName ?? null,
            'lastName' => $student->lastName ?? null,
            'otherNames' => $student->otherNames ?? null,
            'classId' => $student->classId ?? null,
        ],
        'user' => [
            'id' => $user->id,
            'name' => $user->name ?? trim(($student->firstName ?? '') . ' ' . ($student->lastName ?? '')),
            'email' => $user->email ?? null,
            'role' => $user->role,
            'mustChangePassword' => (bool) ($user->mustChangePassword ?? false),
        ],
        'context' => [
            'schoolId' => $schoolId,
            'academicYearId' => $session->academicYearId,
            'termId' => $term->termId,
        ],
    ])->withCookie($cookie);
}


public function studentDashboard(Request $request)
{
    try {
        $token = $request->cookie('student_token');

        if (!$token) {
            return response()->json(['message' => 'Token missing'], 401);
        }

        JWTAuth::setToken($token);
        $payload = JWTAuth::getPayload();

        $schoolId = $payload->get('school_id');
        $studentId = $payload->get('student_id');
        $userId = $payload->get('user_id');

        if (!$schoolId || !$studentId || !$userId) {
            return response()->json(['message' => 'Invalid token payload'], 401);
        }

        $school = School::where('schoolId', $schoolId)->first();
        if (!$school) {
            return response()->json(['message' => 'School not found'], 404);
        }

        $student = Student::with('user')
            ->where('studentId', $studentId)
            ->where('schoolId', $schoolId)
            ->first();

        if (!$student) {
            return response()->json(['message' => 'Student not found'], 404);
        }

        $user = User::find($userId);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        if ((int) $student->userId !== (int) $user->id) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        $currentTerm = Term::with('academic_year')
            ->where('schoolId', $schoolId)
            ->where('isActive', true)
            ->first();

        $cbtAttempts = CbtExamAttempt::with([
                'exam.subject',
            ])
            ->where('studentId', $studentId)
            ->where('schoolId', $schoolId)
            ->latest('attemptId')
            ->get();

        $totalAttempts = $cbtAttempts->count();
        $completedAttempts = $cbtAttempts->where('status', 'submitted')->count();

        $averageScore = $cbtAttempts->where('status', 'submitted')->avg('score');
        $averagePercentage = $cbtAttempts->where('status', 'submitted')->avg('percentage');

        $cbtResults = $cbtAttempts->map(function ($attempt) {
            return [
                'attemptId' => $attempt->attemptId,
                'examId' => $attempt->examId,
                'examTitle' => $attempt->exam->title ?? null,
                'subject' => $attempt->exam->subject->subjectName ?? null,
                'score' => $attempt->score ?? 0,
                'totalQuestions' => $attempt->totalQuestions ?? 0,
                'correctAnswers' => $attempt->correctAnswers ?? 0,
                'wrongAnswers' => $attempt->wrongAnswers ?? 0,
                'unanswered' => $attempt->unanswered ?? 0,
                'percentage' => $attempt->percentage ?? 0,
                'status' => $attempt->status,
                'startedAt' => optional($attempt->startedAt)->format('Y-m-d H:i:s'),
                'submittedAt' => optional($attempt->submittedAt)->format('Y-m-d H:i:s'),
                'durationMinutes' => $attempt->durationMinutes ?? null,
            ];
        })->values();

        return response()->json([
            'message' => 'Student dashboard retrieved successfully',
            'school' => [
                'schoolId' => $school->schoolId,
                'schoolName' => $school->schoolName,
                'schoolLogo' => $school->schoolLogo,
                'email' => $school->email ?? null,
                'phoneNumber' => $school->phoneNumber ?? null,
                'schoolAddress' => $school->schoolAddress ?? null,
            ],
            'currentTerm' => $currentTerm ? [
                'termId' => $currentTerm->termId,
                'termName' => $currentTerm->termName,
                'academicYear' => $currentTerm->academic_year->academicYearName
                    ?? ($currentTerm->academic_year->startYear . '/' . $currentTerm->academic_year->endYear),
            ] : null,
            'student' => [
                'studentId' => $student->studentId,
                'fullName' => trim(($user->firstName ?? '') . ' ' . ($user->lastName ?? '')),
                'firstName' => $user->firstName,
                'lastName' => $user->lastName,
                'otherNames' => $user->otherNames,
                'admissionNumber' => $student->admissionNumber,
                'schoolAdmissionNumber' => $student->schoolAssignedAdmissionNumber,
                'className' => $student->classes->first()->className ?? null,
            ],
            'cbtSummary' => [
                'totalAttempts' => $totalAttempts,
                'completedAttempts' => $completedAttempts,
                'averageScore' => $averageScore ? round($averageScore, 1) : 0,
                'averagePercentage' => $averagePercentage ? round($averagePercentage, 1) : 0,
            ],
            'cbtResults' => $cbtResults,
        ]);
    } catch (JWTException $e) {
        return response()->json([
            'message' => 'Token invalid or expired',
            'error' => $e->getMessage(),
        ], 401);
    }
}

public function schoolInfo(Request $request)
{
    try {
        $token = $request->cookie('student_token');

        if (!$token) {
            return response()->json(['message' => 'Token missing'], 401);
        }

        JWTAuth::setToken($token);
        $payload = JWTAuth::getPayload();

        $schoolId = $payload->get('school_id');

        if (!$schoolId) {
            return response()->json(['message' => 'Invalid token payload'], 401);
        }

        $school = School::where('schoolId', $schoolId)->first();
        if (!$school) {
            return response()->json(['message' => 'School not found'], 404);
        }

        return response()->json([
            'message' => 'School info retrieved successfully',
            'school' => [
                'schoolId' => $school->schoolId,
                'schoolName' => $school->schoolName,
                'schoolLogo' => $school->schoolLogo,
                'email' => $school->email ?? null,
                'phoneNumber' => $school->phoneNumber ?? null,
                'schoolAddress' => $school->schoolAddress ?? null,
            ],
        ]);
    } catch (JWTException $e) {
        return response()->json([
            'message' => 'Token invalid or expired',
            'error' => $e->getMessage(),
        ], 401);
    }
}





public function studentExamStatus(Request $request)
{
    try {
        $token = $request->cookie('student_token');

        if (!$token) {
            return response()->json([
                'message' => 'Token missing'
            ], 401);
        }

        JWTAuth::setToken($token);
        $payload = JWTAuth::getPayload();

        $schoolId = $payload->get('school_id');
        $studentId = $payload->get('student_id');

        if (!$schoolId || !$studentId) {
            return response()->json([
                'message' => 'Invalid token payload'
            ], 401);
        }

        $student = Student::where('studentId', $studentId)
            ->where('schoolId', $schoolId)
            ->first();

        if (!$student) {
            return response()->json([
                'message' => 'Student not found'
            ], 404);
        }

        $now = now();

        // Load all published CBT exams for this student's school/class
        $exams = CbtExam::with(['subject'])
            ->withCount('questions')
            ->where('schoolId', $schoolId)
            ->where('isPublished', true)
            ->where(function ($query) use ($student) {
                $query->whereNull('classId')
                      ->orWhere('classId', $student->classes->first()->classId);
            })
            ->orderByDesc('examId')
            ->get();

        // Load this student's attempts for these exams
        $attempts = CbtExamAttempt::where('studentId', $studentId)
            ->where('schoolId', $schoolId)
            ->get()
            ->keyBy('examId');

        $availableExams = [];
        $completedExams = [];
        $missedExams = [];

        $notStartedCount = 0;
        $inProgressCount = 0;
        $missedCount = 0;
        $completedCount = 0;

        foreach ($exams as $exam) {
            $attempt = $attempts->get($exam->examId);

            $startAt = !empty($exam->startsAt) ? Carbon::parse($exam->startsAt) : null;
            $endAt = !empty($exam->endsAt) ? Carbon::parse($exam->endsAt) : null;

            $hasStarted = $startAt ? $now->greaterThanOrEqualTo($startAt) : true;
            $hasEnded = $endAt ? $now->greaterThan($endAt) : false;
            $isOpenNow = $hasStarted && !$hasEnded;

            $examData = [
                'examId' => $exam->examId,
                'title' => $exam->title,
                'subjectId' => $exam->subjectId ?? null,
                'subjectName' => $exam->subject->subjectName ?? null,
                'classId' => $exam->classId ?? null,
                'durationMinutes' => $exam->durationMinutes ?? $exam->duration ?? null,
                'totalQuestions' => (int) $exam->questions_count,
                'startTime' => $startAt ? $startAt->toDateTimeString() : null,
                'endTime' => $endAt ? $endAt->toDateTimeString() : null,
            ];

            // COMPLETED
            if ($attempt && in_array($attempt->status, ['submitted', 'completed'])) {
                $completedCount++;

                $completedExams[] = array_merge($examData, [
                    'attemptId' => $attempt->attemptId,
                    'score' => $attempt->score ?? $attempt->totalScore ?? 0,
                    'percentage' => $attempt->percentage ?? 0,
                    'submittedAt' => !empty($attempt->submittedAt)
                        ? Carbon::parse($attempt->submittedAt)->toDateTimeString()
                        : (!empty($attempt->submitted_at)
                            ? Carbon::parse($attempt->submitted_at)->toDateTimeString()
                            : null),
                    'status' => $attempt->status,
                ]);

                continue;
            }

            // IN PROGRESS
            if ($attempt && in_array($attempt->status, ['in_progress', 'started'])) {
                $inProgressCount++;

                $availableExams[] = array_merge($examData, [
                    'attemptId' => $attempt->attemptId,
                    'status' => 'in_progress',
                    'startedAt' => !empty($attempt->startedAt)
                        ? Carbon::parse($attempt->startedAt)->toDateTimeString()
                        : (!empty($attempt->started_at)
                            ? Carbon::parse($attempt->started_at)->toDateTimeString()
                            : null),
                ]);

                continue;
            }

            // MISSED
            if (!$attempt && $hasEnded) {
                $missedCount++;

                $missedExams[] = array_merge($examData, [
                    'status' => 'missed',
                ]);

                continue;
            }

            // AVAILABLE / NOT STARTED
            if (!$attempt && $isOpenNow) {
                $notStartedCount++;

                $availableExams[] = array_merge($examData, [
                    'status' => 'not_started',
                ]);

                continue;
            }

            // Optional:
            // exams not yet open are ignored here.
            // If you want upcoming exams too, add another bucket.
        }

        return response()->json([
            'message' => 'Exam status retrieved successfully',
            'summary' => [
                'notStartedCount' => $notStartedCount,
                'inProgressCount' => $inProgressCount,
                'missedCount' => $missedCount,
                'completedCount' => $completedCount,
            ],
            'availableExams' => array_values($availableExams),
            'completedExams' => array_values($completedExams),
            'missedExams' => array_values($missedExams),
        ]);
    } catch (JWTException $e) {
        return response()->json([
            'message' => 'Token invalid or expired',
            'error' => $e->getMessage()
        ], 401);
    }
}


}