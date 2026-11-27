<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\Challenge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;

class MoodLogController extends Controller
{
    /** A mood score up to this value (1 = sad, 2) shows a positive challenge pop-up. */
    private const LOW_MOOD_MAX = 2;

    // POST /api/patient/mood-logs   body: { mood_score: 1-5, challenge_id?: int }
    // answer: { mood_log, challenge }  (challenge = a random positive challenge when the mood is 1 or 2, else null)
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

        $challenge = $log->mood_score <= self::LOW_MOOD_MAX ? Challenge::inRandomOrder()->first() : null;

        return response()->json(['mood_log' => $log, 'challenge' => $challenge], 201);
    }

    // GET /api/patient/mood-logs
    public function index(Request $request): JsonResponse
    {
        $patient = $request->user()->patient;
        abort_unless($patient, 403);

        return response()->json($patient->moodLogs()->latest('logged_at')->paginate(30));
    }

    /**
     * GET /api/patient/mood-logs/report : data for the weekly and monthly charts.
     *  - weekly   = the last 7 days, monthly = the last 30 days (today included)
     *  - every day has its average, how many entries, and a 3-day moving average (null where there is no data)
     *  - comparison = this month's average against the 30 days before it
     */
    public function report(Request $request): JsonResponse
    {
        $patient = $request->user()->patient;
        abort_unless($patient, 403);

        $today = Carbon::today();
        $monthStart = $today->copy()->subDays(29);
        $previousStart = $today->copy()->subDays(59);

        $logs = $patient->moodLogs()->where('logged_at', '>=', $previousStart->copy()->startOfDay())->get();

        $inRange = fn (Carbon $from, Carbon $to) => $logs->filter(
            fn ($l) => $l->logged_at->between($from->copy()->startOfDay(), $to->copy()->endOfDay())
        );

        $week = $inRange($today->copy()->subDays(6), $today);
        $month = $inRange($monthStart, $today);
        $previous = $inRange($previousStart, $monthStart->copy()->subDay());

        $monthAverage = $this->average($month);
        $previousAverage = $this->average($previous);
        $difference = ($monthAverage !== null && $previousAverage !== null) ? round($monthAverage - $previousAverage, 2) : null;

        return response()->json([
            'weekly' => [
                'from' => $today->copy()->subDays(6)->toDateString(),
                'to' => $today->toDateString(),
                'average' => $this->average($week),
                'entries' => $week->count(),
                'days' => $this->dailySeries($week, $today->copy()->subDays(6), 7),
            ],
            'monthly' => [
                'from' => $monthStart->toDateString(),
                'to' => $today->toDateString(),
                'average' => $monthAverage,
                'entries' => $month->count(),
                'days' => $this->dailySeries($month, $monthStart, 30),
            ],
            'previous_month' => [
                'from' => $previousStart->toDateString(),
                'to' => $monthStart->copy()->subDay()->toDateString(),
                'average' => $previousAverage,
                'entries' => $previous->count(),
            ],
            'comparison' => [
                'difference' => $difference,
                'trend' => $difference === null ? null : ($difference > 0.05 ? 'up' : ($difference < -0.05 ? 'down' : 'same')),
            ],
        ]);
    }

    private function average(Collection $logs): ?float
    {
        return $logs->isEmpty() ? null : round((float) $logs->avg('mood_score'), 2);
    }

    /** One row per day, even days without data, so the chart has no gaps. */
    private function dailySeries(Collection $logs, Carbon $from, int $days): array
    {
        $byDay = $logs->groupBy(fn ($l) => $l->logged_at->toDateString());

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $from->copy()->addDays($i)->toDateString();
            $dayLogs = $byDay->get($date);

            $series[] = [
                'date' => $date,
                'average' => $dayLogs ? round((float) $dayLogs->avg('mood_score'), 2) : null,
                'entries' => $dayLogs ? $dayLogs->count() : 0,
            ];
        }

        foreach ($series as $i => $row) {
            $window = collect(array_slice($series, max(0, $i - 2), min($i, 2) + 1))
                ->pluck('average')
                ->filter(fn ($v) => $v !== null);

            $series[$i]['moving_average'] = $window->isEmpty() ? null : round((float) $window->avg(), 2);
        }

        return $series;
    }
}
