<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CbtExam;
use App\Models\CbtExamAttempt;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CbtDashboardController extends Controller
{
    protected function resolveSchoolId(Request $request): int
    {
        $user = auth()->user();

        if (!$user) {
            abort(response()->json([
                'message' => 'Unauthenticated',
            ], 401));
        }

        // Adjust this to your app structure
        $schoolId = $user->schoolId ?? $user->school?->schoolId ?? null;

        if (!$schoolId) {
            abort(response()->json([
                'message' => 'School context not found',
            ], 422));
        }

        return (int) $schoolId;
    }

    public function stats(Request $request): JsonResponse
    {
        $schoolId = $request->header('X-School-ID');
        $now = Carbon::now();

        $totalExams = CbtExam::where('schoolId', $schoolId)->count();

        $runningNow = CbtExam::where('schoolId', $schoolId)
            ->where(function ($query) use ($now) {
                $query->whereNull('startsAt')
                    ->orWhere('startsAt', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('endsAt')
                    ->orWhere('endsAt', '>=', $now);
            })
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhereNotIn('status', ['draft', 'archived', 'cancelled']);
            })
            ->count();

        // Pending grading:
        // if objective CBT only, this can mean "completed attempts not yet synced/published"
        // for now we count submitted attempts with score still null or 0 for manually graded scenarios
        $pendingGrading = CbtExamAttempt::where('schoolId', $schoolId)
            ->where('status', 'completed')
            ->where(function ($query) {
                $query->whereNull('score');
            })
            ->count();

        $completionStats = CbtExam::where('schoolId', $schoolId)
            ->withCount([
                'attempts as total_attempts_count',
                'attempts as completed_attempts_count' => function ($query) {
                    $query->where('status', 'completed');
                },
            ])
            ->get();

        $avgCompletionRate = 0;

        $eligible = $completionStats->filter(function ($exam) {
            return (int) $exam->total_attempts_count > 0;
        });

        if ($eligible->count() > 0) {
            $avgCompletionRate = round(
                $eligible->avg(function ($exam) {
                    return (int) $exam->total_attempts_count > 0
                        ? ((int) $exam->completed_attempts_count / (int) $exam->total_attempts_count)
                        : 0;
                }),
                4
            );
        }

        return response()->json([
            'totalExams' => $totalExams,
            'runningNow' => $runningNow,
            'pendingGrading' => $pendingGrading,
            'avgCompletionRate' => $avgCompletionRate,
        ]);
    }

    public function exams(Request $request): JsonResponse
    {
        $schoolId = $request->header('X-School-ID');
        $now = Carbon::now();

        $limit = max(1, min((int) $request->get('limit', 20), 100));
        $search = trim((string) $request->get('search', ''));
        $statusFilter = trim((string) $request->get('status', ''));
        $sort = (string) $request->get('sort', 'startsAt,desc');

        [$sortField, $sortDirection] = array_pad(explode(',', $sort), 2, null);
        $sortField = in_array($sortField, ['startsAt', 'title', 'created_at'], true)
            ? $sortField
            : 'startsAt';
        $sortDirection = strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';

        $query = CbtExam::query()
            ->where('schoolId', $schoolId)
            ->with([
                'subject:subjectId,subjectName',
            ])
            ->withCount([
                'attempts as totalCandidates',
                'attempts as startedCount' => function ($query) {
                    $query->whereIn('status', ['in_progress', 'completed']);
                },
                'attempts as completedCount' => function ($query) {
                    $query->where('status', 'completed');
                },
            ]);

        if ($search !== '') {
            $query->where(function ($inner) use ($search) {
                $inner->where('title', 'like', "%{$search}%")
                    ->orWhere('courseCode', 'like', "%{$search}%");
            });
        }

        $exams = $query
            ->orderBy($sortField, $sortDirection)
            ->limit($limit)
            ->get()
            ->map(function ($exam) use ($now) {
                $status = $this->determineExamStatus($exam, $now);

                return [
                    'id' => $exam->examId,
                    'title' => $exam->title,
                    'courseCode' => $exam->courseCode
                        ?? optional($exam->subject)->subjectName
                        ?? '',
                    'startsAt' => optional($exam->startsAt)->toDateTimeString()
                        ?? $exam->startsAt,
                    'durationMinutes' => (int) ($exam->durationMinutes ?? 0),
                    'totalCandidates' => (int) ($exam->totalCandidates ?? 0),
                    'status' => $status,
                    'startedCount' => (int) ($exam->startedCount ?? 0),
                    'completedCount' => (int) ($exam->completedCount ?? 0),
                    'pendingGrading' => 0,
                    'createdAt' => optional($exam->created_at)->toDateTimeString(),
                ];
            });

        if ($statusFilter !== '' && strtolower($statusFilter) !== 'all') {
            $exams = $exams->filter(function ($exam) use ($statusFilter) {
                return $exam['status'] === $statusFilter;
            })->values();
        }

        return response()->json([
            'exams' => $exams,
        ]);
    }

    protected function determineExamStatus($exam, Carbon $now): string
    {
        $rawStatus = strtolower((string) ($exam->status ?? ''));

        if ($rawStatus === 'cancelled') {
            return 'Cancelled';
        }

        if ($rawStatus === 'archived') {
            return 'Ended';
        }

        $startsAt = $exam->startsAt ? Carbon::parse($exam->startsAt) : null;
        $endsAt = $exam->endsAt ? Carbon::parse($exam->endsAt) : null;

        if ($startsAt && $now->lt($startsAt)) {
            return 'Upcoming';
        }

        if ($endsAt && $now->gt($endsAt)) {
            return 'Ended';
        }

        if (($startsAt && $now->gte($startsAt)) && (!$endsAt || $now->lte($endsAt))) {
            return 'Running';
        }

        return 'Upcoming';
    }
}