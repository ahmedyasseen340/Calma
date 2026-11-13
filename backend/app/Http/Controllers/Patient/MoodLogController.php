<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MoodLogController extends Controller
{
    // POST /api/patient/mood-logs   body: { mood_score: 1-5, challenge_id?: int }
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mood_score' => ['required', 'integer', 'between:1,5'],
            'challenge_id' => ['nullable', 'integer', 'exists:challenges,id'],
        ]);

        $patient = $request->user()->patient;
        abort_unless($patient, 403);

        $log = $patient->moodLogs()->create([
            'mood_score' => $data['mood_score'],
            'challenge_id' => $data['challenge_id'] ?? null,
            'logged_at' => now(),
        ]);

        return response()->json(['mood_log' => $log], 201);
    }

    // GET /api/patient/mood-logs
    public function index(Request $request): JsonResponse
    {
        $patient = $request->user()->patient;
        abort_unless($patient, 403);

        return response()->json($patient->moodLogs()->latest('logged_at')->paginate(30));
    }
}
