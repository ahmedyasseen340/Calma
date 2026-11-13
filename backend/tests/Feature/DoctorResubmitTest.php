<?php

namespace Tests\Feature;

use App\Models\DoctorProfile;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DoctorResubmitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
    }

    private function doctor(string $status): User
    {
        $u = User::factory()->create(['role' => 'doctor', 'email' => 'doc@example.com']);
        Storage::disk('public')->put('doctors/photos/old.jpg', 'x');
        Storage::disk('local')->put('doctors/id_cards/old.jpg', 'x');
        Storage::disk('local')->put('doctors/certificates/old.jpg', 'x');
        DoctorProfile::create([
            'user_id' => $u->id, 'specialization' => 'Old', 'dob' => '1985-05-20',
            'id_card_img' => 'doctors/id_cards/old.jpg', 'certificate_img' => 'doctors/certificates/old.jpg',
            'phone' => '01012345678', 'photo' => 'doctors/photos/old.jpg', 'status' => $status,
        ]);

        return $u;
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'email' => 'doc@example.com', 'password' => 'password',
            'first_name' => 'Sara', 'last_name' => 'Hassan', 'dob' => '1986-06-21',
            'specialization' => 'Psychiatry', 'phone' => '01099988877',
            'clinic_url' => 'https://clinic.example.com', 'bio' => 'استشاري',
            'photo' => UploadedFile::fake()->image('p.jpg'),
            'id_card_img' => UploadedFile::fake()->image('id.jpg'),
            'certificate_img' => UploadedFile::fake()->image('c.jpg'),
        ], $override);
    }

    private function send(array $override = [])
    {
        return $this->post('/api/auth/doctor/resubmit', $this->payload($override), ['Accept' => 'application/json']);
    }

    public function test_login_tells_a_rejected_doctor_to_send_the_data_again(): void
    {
        $this->doctor('rejected');

        $this->postJson('/api/auth/login', ['email' => 'doc@example.com', 'password' => 'password'])
            ->assertForbidden()
            ->assertJsonPath('status', 'rejected')
            ->assertJsonPath('can_resubmit', true);
    }

    public function test_rejected_doctor_sends_everything_again_and_goes_back_to_pending(): void
    {
        $user = $this->doctor('rejected');

        $this->send()->assertOk()->assertJsonPath('status', 'pending');

        $profile = $user->doctorProfile->fresh();
        $this->assertSame('pending', $profile->status);
        $this->assertSame('Psychiatry', $profile->specialization);
        $this->assertSame('01099988877', $profile->phone);
        $this->assertSame('1986-06-21', $profile->dob->toDateString());
        $this->assertSame('Sara', $user->fresh()->first_name);

        // new files are stored, the rejected ones are deleted
        Storage::disk('public')->assertExists($profile->photo);
        Storage::disk('local')->assertExists($profile->id_card_img);
        Storage::disk('local')->assertExists($profile->certificate_img);
        Storage::disk('public')->assertMissing('doctors/photos/old.jpg');
        Storage::disk('local')->assertMissing('doctors/id_cards/old.jpg');
        Storage::disk('local')->assertMissing('doctors/certificates/old.jpg');

        // it is a normal pending doctor now: waits for the admin
        $this->postJson('/api/auth/login', ['email' => 'doc@example.com', 'password' => 'password'])
            ->assertForbidden()->assertJsonPath('status', 'pending');
    }

    public function test_after_the_admin_approves_the_second_time_the_doctor_can_log_in(): void
    {
        $user = $this->doctor('rejected');
        $this->send()->assertOk();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->postJson("/api/admin/doctors/{$user->doctorProfile->id}/approve")->assertOk();

        $this->postJson('/api/auth/login', ['email' => 'doc@example.com', 'password' => 'password'])->assertOk();
    }

    public function test_wrong_password_or_unknown_email_is_refused_and_nothing_changes(): void
    {
        $user = $this->doctor('rejected');

        $this->send(['password' => 'wrong'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->send(['email' => 'nobody@example.com'])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertSame('rejected', $user->doctorProfile->fresh()->status);
        $this->assertSame('Old', $user->doctorProfile->fresh()->specialization);
    }

    public function test_only_a_rejected_doctor_can_use_it(): void
    {
        $this->doctor('active');
        $this->send()->assertForbidden();

        DoctorProfile::query()->update(['status' => 'pending']);
        $this->send()->assertForbidden();

        $patient = User::factory()->create(['role' => 'patient', 'email' => 'pat@example.com']);
        Patient::factory()->create(['user_id' => $patient->id]);
        $this->send(['email' => 'pat@example.com'])->assertForbidden();
    }

    public function test_all_the_data_is_required_again_and_the_rules_still_apply(): void
    {
        $user = $this->doctor('rejected');

        $payload = $this->payload();
        unset($payload['id_card_img']);
        $this->post('/api/auth/doctor/resubmit', $payload, ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('id_card_img');

        $this->send(['bio' => str_repeat('ب', 301)])->assertUnprocessable()->assertJsonValidationErrors('bio');
        $this->send(['phone' => 'abc'])->assertUnprocessable()->assertJsonValidationErrors('phone');

        $this->assertSame('rejected', $user->doctorProfile->fresh()->status);
    }
}
