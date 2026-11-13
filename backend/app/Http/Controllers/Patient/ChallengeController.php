<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\Challenge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChallengeController extends Controller
{
    // GET /api/patient/challenges?type=breathing
    public function index(Request $request): JsonResponse
    {
        $request->validate(['type' => ['nullable', 'in:breathing,walking,reading']]);

        $challenges = Challenge::query()
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->orderBy('id')
            ->get();

        return response()->json(['challenges' => $challenges]);
    }

    // POST /api/patient/challenges/{challenge}/complete
    public function complete(Request $request, Challenge $challenge): JsonResponse
    {
        $patient = $request->user()->patient;
        abort_unless($patient, 403);

        $patient->challenges()->attach($challenge->id, ['completed_at' => now()]);

        return response()->json(['message' => 'التحدي اتسجل.'], 201);
    }
}
