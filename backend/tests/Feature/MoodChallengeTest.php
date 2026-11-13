<?php

namespace Tests\Feature;

use App\Models\Challenge;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MoodChallengeTest extends TestCase
{
    use RefreshDatabase;

    private function patientUser(): User
    {
        $user = User::factory()->create(['role' => 'patient']);
        Patient::factory()->create(['user_id' => $user->id]);

        return $user;
    }

    public function test_mood_score_must_be_between_1_and_5(): void
    {
        Sanctum::actingAs($this->patientUser());

        $this->postJson('/api/patient/mood-logs', ['mood_score' => 4])->assertCreated();
        $this->postJson('/api/patient/mood-logs', ['mood_score' => 6])->assertUnprocessable();
        $this->postJson('/api/patient/mood-logs', ['mood_score' => 0])->assertUnprocessable();

        $this->assertDatabaseCount('mood_logs', 1);
    }

    public function test_mood_log_can_reference_a_challenge(): void
    {
        Sanctum::actingAs($this->patientUser());
        $challenge = Challenge::create(['text' => 'امشي 15 دقيقة', 'type' => 'walking']);

        $this->postJson('/api/patient/mood-logs', ['mood_score' => 3, 'challenge_id' => $challenge->id])
            ->assertCreated()
            ->assertJsonPath('mood_log.challenge_id', $challenge->id);
    }

    public function test_patient_can_complete_a_challenge(): void
    {
        Sanctum::actingAs($this->patientUser());
        $challenge = Challenge::create(['text' => 'تنفس بعمق', 'type' => 'breathing']);

        $this->postJson("/api/patient/challenges/{$challenge->id}/complete")->assertCreated();

        $this->assertDatabaseCount('patient_challenges', 1);
    }
}
