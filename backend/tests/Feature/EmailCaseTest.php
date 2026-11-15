<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailCaseTest extends TestCase
{
    use RefreshDatabase;

    private function patientPayload(string $email): array
    {
        return [
            'first_name' => 'Ahmed', 'last_name' => 'Ali', 'email' => $email,
            'password' => 'password123', 'password_confirmation' => 'password123', 'role' => 'patient',
            'dob' => now()->subYears(25)->toDateString(), 'gender' => 'male', 'marital_status' => 'single',
        ];
    }

    public function test_the_email_is_saved_in_lower_case_and_trimmed(): void
    {
        $this->postJson('/api/auth/register', $this->patientPayload('  Ahmed@Example.COM '))->assertCreated();

        $this->assertDatabaseHas('users', ['email' => 'ahmed@example.com']);
    }

    public function test_the_same_email_with_another_letter_case_cannot_register_twice(): void
    {
        $this->postJson('/api/auth/register', $this->patientPayload('ahmed@example.com'))->assertCreated();

        $this->postJson('/api/auth/register', $this->patientPayload('AHMED@example.com'))
            ->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_login_ignores_the_letter_case_of_the_email(): void
    {
        $this->postJson('/api/auth/register', $this->patientPayload('ahmed@example.com'))->assertCreated();

        $this->postJson('/api/auth/login', ['email' => 'AHMED@Example.com', 'password' => 'password123'])
            ->assertOk()->assertJsonStructure(['token']);
    }

    public function test_the_model_also_lowercases_emails_set_in_code(): void
    {
        $user = User::factory()->create(['email' => 'Mixed.Case@Example.com']);

        $this->assertSame('mixed.case@example.com', $user->fresh()->email);
    }
}
