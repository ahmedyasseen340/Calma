<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function patientPayload(array $override = []): array
    {
        return array_merge([
            'first_name' => 'Ahmed',
            'last_name' => 'Ali',
            'email' => 'ahmed@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'patient',
            'dob' => now()->subYears(25)->toDateString(),
            'gender' => 'male',
            'marital_status' => 'single',
        ], $override);
    }

    public function test_adult_patient_registers_with_age_gender_and_marital_status(): void
    {
        $this->postJson('/api/auth/register', $this->patientPayload())
            ->assertCreated()
            ->assertJsonPath('user.patient.gender', 'male')
            ->assertJsonPath('user.patient.marital_status', 'single')
            ->assertJsonPath('user.patient.age', 25)
            ->assertJsonStructure(['token']);

        $this->assertDatabaseCount('patients', 1);
    }

    public function test_patient_must_be_older_than_16(): void
    {
        // 15 years old
        $this->postJson('/api/auth/register', $this->patientPayload(['dob' => now()->subYears(15)->toDateString()]))
            ->assertUnprocessable()->assertJsonValidationErrors('dob');

        // exactly 16 today: not older than 16 yet
        $this->postJson('/api/auth/register', $this->patientPayload(['dob' => now()->subYears(16)->toDateString()]))
            ->assertUnprocessable()->assertJsonValidationErrors('dob');

        // 16 years and 1 day: older than 16
        $this->postJson('/api/auth/register', $this->patientPayload(['dob' => now()->subYears(16)->subDay()->toDateString()]))
            ->assertCreated();

        $this->assertDatabaseCount('patients', 1);
    }

    public function test_gender_and_marital_status_are_validated(): void
    {
        $this->postJson('/api/auth/register', $this->patientPayload(['gender' => 'other']))
            ->assertUnprocessable()->assertJsonValidationErrors('gender');

        $this->postJson('/api/auth/register', $this->patientPayload(['marital_status' => 'divorced']))
            ->assertUnprocessable()->assertJsonValidationErrors('marital_status');

        $this->postJson('/api/auth/register', $this->patientPayload(['gender' => null, 'marital_status' => null]))
            ->assertUnprocessable()->assertJsonValidationErrors(['gender', 'marital_status']);
    }

    public function test_dob_is_required_for_patients(): void
    {
        $payload = $this->patientPayload();
        unset($payload['dob']);

        $this->postJson('/api/auth/register', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('dob');
    }
}
