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

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function patient(array $user = []): User
    {
        $u = User::factory()->create(array_merge(['role' => 'patient'], $user));
        Patient::factory()->create(['user_id' => $u->id, 'dob' => '1995-03-10', 'gender' => 'male', 'marital_status' => 'single']);

        return $u;
    }

    private function doctor(): User
    {
        $u = User::factory()->create(['role' => 'doctor']);
        Storage::disk('public')->put('doctors/photos/old.jpg', 'x');
        DoctorProfile::create([
            'user_id' => $u->id, 'specialization' => 'Psychiatry', 'dob' => '1985-05-20',
            'id_card_img' => 'id.jpg', 'certificate_img' => 'cert.jpg',
            'phone' => '01012345678', 'clinic_url' => 'https://clinic.example.com',
            'photo' => 'doctors/photos/old.jpg', 'bio' => 'old bio', 'status' => 'active',
        ]);

        return $u;
    }

    // ================================================================== patient
    public function test_patient_edits_names_birth_date_and_marital_status(): void
    {
        $user = $this->patient();
        Sanctum::actingAs($user);

        $this->postJson('/api/patient/profile', [
            'first_name' => 'Omar', 'last_name' => 'Hassan', 'dob' => '2001-01-01', 'marital_status' => 'married',
        ])->assertOk()
          ->assertJsonPath('user.first_name', 'Omar')
          ->assertJsonPath('user.last_name', 'Hassan')
          ->assertJsonPath('user.patient.marital_status', 'married');

        $this->assertSame('2001-01-01', $user->patient->fresh()->dob->toDateString());
    }

    public function test_patient_can_send_only_the_fields_that_changed(): void
    {
        $user = $this->patient();
        Sanctum::actingAs($user);

        $this->postJson('/api/patient/profile', ['marital_status' => 'married'])
            ->assertOk()
            ->assertJsonPath('user.patient.marital_status', 'married')
            ->assertJsonPath('user.first_name', $user->first_name)
            ->assertJsonPath('user.patient.gender', 'male');
    }

    public function test_patient_must_stay_older_than_16_when_editing_the_birth_date(): void
    {
        Sanctum::actingAs($this->patient());

        $this->postJson('/api/patient/profile', ['dob' => now()->subYears(16)->toDateString()])
            ->assertUnprocessable()->assertJsonValidationErrors('dob');
        $this->postJson('/api/patient/profile', ['dob' => now()->subYears(10)->toDateString()])
            ->assertUnprocessable()->assertJsonValidationErrors('dob');
        $this->postJson('/api/patient/profile', ['dob' => now()->subYears(16)->subDay()->toDateString()])
            ->assertOk();
    }

    public function test_patient_cannot_change_email_gender_or_role(): void
    {
        $user = $this->patient(['email' => 'me@example.com']);
        Sanctum::actingAs($user);

        $this->postJson('/api/patient/profile', [
            'email' => 'other@example.com', 'gender' => 'female', 'role' => 'admin', 'first_name' => 'Same',
        ])->assertOk();

        $this->assertSame('me@example.com', $user->fresh()->email);
        $this->assertSame('male', $user->patient->fresh()->gender);
        $this->assertSame('patient', $user->fresh()->role);
        $this->assertSame('Same', $user->fresh()->first_name);
    }

    public function test_patient_values_are_validated(): void
    {
        Sanctum::actingAs($this->patient());

        $this->postJson('/api/patient/profile', ['marital_status' => 'divorced'])
            ->assertUnprocessable()->assertJsonValidationErrors('marital_status');
        $this->postJson('/api/patient/profile', ['first_name' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors('first_name');
    }

    // ================================================================== doctor
    public function test_doctor_edits_names_phone_clinic_link_and_bio(): void
    {
        $user = $this->doctor();
        Sanctum::actingAs($user);

        $this->postJson('/api/doctor/profile', [
            'first_name' => 'Sara', 'last_name' => 'Hassan', 'phone' => '+201099988877',
            'clinic_url' => 'https://new-clinic.example.com', 'bio' => 'استشاري طب نفسي',
        ])->assertOk()
          ->assertJsonPath('requires_approval', false)
          ->assertJsonPath('user.first_name', 'Sara')
          ->assertJsonPath('user.doctor_profile.phone', '+201099988877')
          ->assertJsonPath('user.doctor_profile.clinic_url', 'https://new-clinic.example.com')
          ->assertJsonPath('user.doctor_profile.bio', 'استشاري طب نفسي')
          ->assertJsonPath('user.doctor_profile.status', 'active');   // stays active
    }

    public function test_doctor_can_clear_the_clinic_link_and_the_bio(): void
    {
        $user = $this->doctor();
        Sanctum::actingAs($user);

        $this->postJson('/api/doctor/profile', ['clinic_url' => null, 'bio' => null])->assertOk();

        $this->assertNull($user->doctorProfile->fresh()->clinic_url);
        $this->assertNull($user->doctorProfile->fresh()->bio);
    }

    public function test_bio_is_limited_to_300_characters_and_phone_is_validated(): void
    {
        Sanctum::actingAs($this->doctor());

        $this->postJson('/api/doctor/profile', ['bio' => str_repeat('ب', 300)])->assertOk();
        $this->postJson('/api/doctor/profile', ['bio' => str_repeat('ب', 301)])
            ->assertUnprocessable()->assertJsonValidationErrors('bio');
        $this->postJson('/api/doctor/profile', ['phone' => 'abc'])
            ->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->postJson('/api/doctor/profile', ['clinic_url' => 'not a link'])
            ->assertUnprocessable()->assertJsonValidationErrors('clinic_url');
    }

    public function test_new_photo_replaces_the_old_one_and_stays_active(): void
    {
        $user = $this->doctor();
        Sanctum::actingAs($user);

        $this->post('/api/doctor/profile', ['photo' => UploadedFile::fake()->image('new.jpg')], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('requires_approval', false);

        $profile = $user->doctorProfile->fresh();
        $this->assertNotSame('doctors/photos/old.jpg', $profile->photo);
        $this->assertSame('active', $profile->status);
        Storage::disk('public')->assertExists($profile->photo);
        Storage::disk('public')->assertMissing('doctors/photos/old.jpg');

        $this->post('/api/doctor/profile', ['photo' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('photo');
    }

    public function test_doctor_cannot_change_email_dob_status_or_role(): void
    {
        $user = $this->doctor();
        Sanctum::actingAs($user);

        $this->postJson('/api/doctor/profile', [
            'email' => 'hacker@example.com', 'dob' => '1990-01-01', 'status' => 'rejected', 'role' => 'admin', 'bio' => 'ok',
        ])->assertOk()->assertJsonPath('requires_approval', false);

        $profile = $user->doctorProfile->fresh();
        $this->assertNotSame('hacker@example.com', $user->fresh()->email);
        $this->assertSame('1985-05-20', $profile->dob->toDateString());
        $this->assertSame('active', $profile->status);
        $this->assertSame('doctor', $user->fresh()->role);
    }

    public function test_changing_the_specialization_sends_the_doctor_back_to_pending_and_logs_them_out(): void
    {
        $user = $this->doctor();
        $user->createToken('phone');
        Sanctum::actingAs($user);

        $this->postJson('/api/doctor/profile', ['specialization' => 'Clinical Psychology'])
            ->assertOk()
            ->assertJsonPath('requires_approval', true)
            ->assertJsonPath('user.doctor_profile.specialization', 'Clinical Psychology')
            ->assertJsonPath('user.doctor_profile.status', 'pending');

        $this->assertDatabaseCount('personal_access_tokens', 0);

        // cannot log in while pending ...
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertForbidden()->assertJsonPath('status', 'pending');

        // ... until the admin approves again
        $user->doctorProfile->update(['status' => 'active']);
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
    }

    public function test_sending_the_same_specialization_does_not_need_approval(): void
    {
        $user = $this->doctor();
        Sanctum::actingAs($user);

        $this->postJson('/api/doctor/profile', ['specialization' => 'Psychiatry', 'phone' => '01111111111'])
            ->assertOk()->assertJsonPath('requires_approval', false);

        $this->assertSame('active', $user->doctorProfile->fresh()->status);
    }

    // ================================================================== permissions
    public function test_each_role_can_only_use_its_own_profile_endpoint(): void
    {
        Sanctum::actingAs($this->doctor());
        $this->postJson('/api/patient/profile', ['first_name' => 'X'])->assertForbidden();

        Sanctum::actingAs($this->patient());
        $this->postJson('/api/doctor/profile', ['first_name' => 'X'])->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->postJson('/api/patient/profile', ['first_name' => 'X'])->assertForbidden();
    }

    public function test_guest_gets_401(): void
    {
        $this->postJson('/api/patient/profile', ['first_name' => 'X'])->assertUnauthorized();
        $this->postJson('/api/doctor/profile', ['first_name' => 'X'])->assertUnauthorized();
        $this->postJson('/api/auth/change-password', [])->assertUnauthorized();
    }

    // ================================================================== change password
    public function test_change_password_checks_the_current_one_and_logs_out_other_devices(): void
    {
        $user = $this->patient();
        $token = $user->createToken('this-phone')->plainTextToken;
        $user->createToken('old-laptop');

        $payload = ['current_password' => 'password', 'password' => 'brand-new-pass-1', 'password_confirmation' => 'brand-new-pass-1'];

        $this->withToken($token)->postJson('/api/auth/change-password', array_merge($payload, ['current_password' => 'wrong']))
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->withToken($token)->postJson('/api/auth/change-password', $payload)->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 1);   // only this device is still logged in
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'brand-new-pass-1'])->assertOk();
    }

    public function test_new_password_must_be_confirmed_long_enough_and_different(): void
    {
        Sanctum::actingAs($this->patient());

        $this->postJson('/api/auth/change-password', ['current_password' => 'password', 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/api/auth/change-password', ['current_password' => 'password', 'password' => 'another-pass-1', 'password_confirmation' => 'nope'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/api/auth/change-password', ['current_password' => 'password', 'password' => 'password', 'password_confirmation' => 'password'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
    }
}
