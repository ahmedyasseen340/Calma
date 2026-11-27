<?php

namespace Tests\Feature;

use App\Models\Challenge;
use App\Models\MoodLog;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MoodReportTest extends TestCase
{
    use RefreshDatabase;

    private function patient(): Patient
    {
        $u = User::factory()->create(['role' => 'patient']);
        $p = Patient::factory()->create(['user_id' => $u->id]);
        Sanctum::actingAs($u);

        return $p;
    }

    private function log(Patient $p, int $score, int $daysAgo): void
    {
        MoodLog::create(['patient_id' => $p->id, 'mood_score' => $score, 'logged_at' => now()->subDays($daysAgo)->setTime(12, 0)]);
    }

    // ------------------------------------------------------------------ pop-up challenge
    public function test_a_low_mood_returns_a_random_positive_challenge(): void
    {
        $this->patient();
        Challenge::create(['text' => 'تنفس 3 دقايق', 'type' => 'breathing']);

        foreach ([1, 2] as $score) {
            $this->postJson('/api/patient/mood-logs', ['mood_score' => $score])
                ->assertCreated()
                ->assertJsonPath('challenge.type', 'breathing');
        }
    }

    public function test_a_normal_or_good_mood_returns_no_challenge(): void
    {
        $this->patient();
        Challenge::create(['text' => 'امشي', 'type' => 'walking']);

        foreach ([3, 4, 5] as $score) {
            $this->postJson('/api/patient/mood-logs', ['mood_score' => $score])
                ->assertCreated()->assertJsonPath('challenge', null);
        }
    }

    public function test_a_low_mood_without_any_challenge_in_the_database_is_still_saved(): void
    {
        $this->patient();

        $this->postJson('/api/patient/mood-logs', ['mood_score' => 1])
            ->assertCreated()->assertJsonPath('challenge', null);

        $this->assertDatabaseCount('mood_logs', 1);
    }

    // ------------------------------------------------------------------ report
    public function test_the_report_has_the_weekly_and_monthly_series_the_averages_and_the_comparison(): void
    {
        $p = $this->patient();
        $this->log($p, 5, 0);    // today
        $this->log($p, 3, 1);    // yesterday   -> week average 4.0, month average 4.0
        $this->log($p, 2, 31);   // the 30 days before this month
        $this->log($p, 2, 40);   //                -> previous average 2.0

        $report = $this->getJson('/api/patient/mood-logs/report')->assertOk()->json();

        $this->assertCount(7, $report['weekly']['days']);
        $this->assertCount(30, $report['monthly']['days']);
        $this->assertEquals(4.0, $report['weekly']['average']);
        $this->assertSame(2, $report['weekly']['entries']);
        $this->assertEquals(4.0, $report['monthly']['average']);
        $this->assertEquals(2.0, $report['previous_month']['average']);
        $this->assertSame(2, $report['previous_month']['entries']);
        $this->assertEquals(2.0, $report['comparison']['difference']);
        $this->assertSame('up', $report['comparison']['trend']);

        // the last two days of the weekly series are the two logs, the other days are empty
        $days = $report['weekly']['days'];
        $this->assertEquals(5.0, $days[6]['average']);
        $this->assertEquals(3.0, $days[5]['average']);
        $this->assertNull($days[0]['average']);
        $this->assertSame(0, $days[0]['entries']);
    }

    public function test_the_moving_average_uses_the_last_three_days_with_data(): void
    {
        $p = $this->patient();
        $this->log($p, 1, 2);
        $this->log($p, 3, 1);
        $this->log($p, 5, 0);

        $days = $this->getJson('/api/patient/mood-logs/report')->assertOk()->json('weekly.days');

        $this->assertEquals(1.0, $days[4]['moving_average']);
        $this->assertEquals(2.0, $days[5]['moving_average']);   // (1 + 3) / 2
        $this->assertEquals(3.0, $days[6]['moving_average']);   // (1 + 3 + 5) / 3
        $this->assertNull($days[0]['moving_average']);
    }

    public function test_an_empty_report_has_no_averages_and_no_comparison(): void
    {
        $this->patient();

        $report = $this->getJson('/api/patient/mood-logs/report')->assertOk()->json();

        $this->assertNull($report['weekly']['average']);
        $this->assertNull($report['monthly']['average']);
        $this->assertNull($report['comparison']['difference']);
        $this->assertNull($report['comparison']['trend']);
        $this->assertCount(30, $report['monthly']['days']);
    }

    public function test_a_patient_only_sees_their_own_moods_and_others_cannot_use_the_report(): void
    {
        $other = User::factory()->create(['role' => 'patient']);
        $otherPatient = Patient::factory()->create(['user_id' => $other->id]);
        $this->log($otherPatient, 1, 0);

        $this->patient();
        $this->assertSame(0, $this->getJson('/api/patient/mood-logs/report')->json('weekly.entries'));

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson('/api/patient/mood-logs/report')->assertForbidden();
    }
}
