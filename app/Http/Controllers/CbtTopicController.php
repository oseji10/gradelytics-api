<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CbtTopicController extends Controller
{
    private function schoolId(Request $request): int
    {
        return (int) ($request->header('X-School-ID') ?? $request->user()->schoolId);
    }

    public function index(Request $request): JsonResponse
    {
        $schoolId = $this->schoolId($request);

        $query = Topic::where('schoolId', $schoolId)
            ->with('subject')
            ->orderBy('topicName');

        if ($request->filled('subjectId')) {
            $query->where('subjectId', (int) $request->subjectId);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->get()
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $schoolId = $this->schoolId($request);
        $userId = $request->user()->id;

        $validated = $request->validate([
            'subjectId' => 'required|integer|exists:subjects,subjectId',
            'topicName' => 'required|string|max:255',
        ]);

        $topic = Topic::create([
            'schoolId' => $schoolId,
            'subjectId' => $validated['subjectId'],
            'topicName' => $validated['topicName'],
            'createdBy' => $userId,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Topic created',
            'data' => $topic
        ]);
    }

    public function update(Request $request, int $topicId): JsonResponse
    {
        $schoolId = $this->schoolId($request);

        $topic = Topic::where('schoolId', $schoolId)
            ->where('topicId', $topicId)
            ->firstOrFail();

        $validated = $request->validate([
            'topicName' => 'required|string|max:255',
        ]);

        $topic->update([
            'topicName' => $validated['topicName']
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Topic updated',
            'data' => $topic
        ]);
    }

    public function destroy(Request $request, int $topicId): JsonResponse
    {
        $schoolId = $this->schoolId($request);

        $topic = Topic::where('schoolId', $schoolId)
            ->where('topicId', $topicId)
            ->firstOrFail();

        $topic->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Topic deleted'
        ]);
    }
}