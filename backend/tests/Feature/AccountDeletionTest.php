<?php

namespace Tests\Feature;

use App\Models\Challenge;
use App\Models\ChatMessage;
use App\Models\DoctorProfile;
use App\Models\MoodLog;
use App\Models\Patient;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
    }

    /** A patient with data in every table that belongs to a patient. */
    private function patientWithData(string $email = 'pat@example.com'): array
    {
        $user = User::factory()->create(['role' => 'patient', 'email' => $email]);
        $patient = Patient::factory()->create(['user_id' => $user->id]);

        Storage::disk('local')->put("chat-audio/{$email}.m4a", 'sound');
        ChatMessage::create(['patient_id' => $patient->id, 'message' => 'hi', 'reply' => 'hello', 'audio_path' => "chat-audio/{$email}.m4a"]);
        ChatMessage::create(['patient_id' => $patient->id, 'message' => 'text only', 'reply' => 'ok']);
        MoodLog::create(['patient_id' => $patient->id, 'mood_score' => 3, 'logged_at' => now()]);
        $challenge = Challenge::create(['text' => 'تنفس', 'type' => 'breathing']);
        $patient->challenges()->attach($challenge->id, ['completed_at' => now()]);
        Post::create(['user_id' => $user->id, 'content' => 'بوست']);

        return [$user, $patient];
    }

    private function doctor(string $email = 'doc@example.com'): User
    {
        $user = User::factory()->create(['role' => 'doctor', 'email' => $email]);
        Storage::disk('public')->put('doctors/photos/p.jpg', 'x');
        Storage::disk('local')->put('doctors/id_cards/i.jpg', 'x');
        Storage::disk('local')->put('doctors/certificates/c.jpg', 'x');
        DoctorProfile::create([
            'user_id' => $user->id, 'specialization' => 'Psychiatry', 'dob' => '1985-05-20',
            'id_card_img' => 'doctors/id_cards/i.jpg', 'certificate_img' => 'doctors/certificates/c.jpg',
            'phone' => '01012345678', 'photo' => 'doctors/photos/p.jpg', 'status' => 'active',
        ]);
        Post::create(['user_id' => $user->id, 'content' => 'نصيحة']);

        return $user;
    }

    public function test_patient_deletes_the_account_and_all_the_personal_data_goes(): void
    {
        [$user, $patient] = $this->patientWithData();
        [$other, $otherPatient] = $this->patientWithData('other@example.com');   // must stay untouched
        $token = $user->createToken('phone')->plainTextToken;

        $this->withToken($token)->postJson('/api/auth/delete-account', ['current_password' => 'password'])
            ->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('patients', ['id' => $patient->id]);
        $this->assertDatabaseMissing('chat_messages', ['patient_id' => $patient->id]);
        $this->assertDatabaseMissing('mood_logs', ['patient_id' => $patient->id]);
        $this->assertDatabaseMissing('patient_challenges', ['patient_id' => $patient->id]);
        $this->assertDatabaseMissing('posts', ['user_id' => $user->id]);
        Storage::disk('local')->assertMissing('chat-audio/pat@example.com.m4a');   // the voice recording is gone too

        // the other patient is not affected
        $this->assertDatabaseHas('users', ['id' => $other->id]);
        $this->assertDatabaseHas('chat_messages', ['patient_id' => $otherPatient->id, 'message' => 'hi']);
        $this->assertDatabaseHas('posts', ['user_id' => $other->id]);
        Storage::disk('local')->assertExists('chat-audio/other@example.com.m4a');
    }

    public function test_the_old_token_stops_working_and_the_email_can_register_again(): void
    {
        [$user] = $this->patientWithData();
        $token = $user->createToken('phone')->plainTextToken;

        $this->withToken($token)->postJson('/api/auth/delete-account', ['current_password' => 'password'])->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
        $this->postJson('/api/auth/login', ['email' => 'pat@example.com', 'password' => 'password'])->assertUnprocessable();

        $this->postJson('/api/auth/register', [
            'first_name' => 'New', 'last_name' => 'Start', 'email' => 'pat@example.com',
            'password' => 'password123', 'password_confirmation' => 'password123', 'role' => 'patient',
            'dob' => now()->subYears(25)->toDateString(), 'gender' => 'male', 'marital_status' => 'single',
        ])->assertCreated();
    }

    public function test_doctor_deletes_the_account_with_the_profile_files_and_posts(): void
    {
        $doctor = $this->doctor();
        $profileId = $doctor->doctorProfile->id;
        $other = $this->doctor('other-doc@example.com');
        $token = $doctor->createToken('phone')->plainTextToken;

        $this->withToken($token)->postJson('/api/auth/delete-account', ['current_password' => 'password'])->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $doctor->id]);
        $this->assertDatabaseMissing('doctor_profiles', ['id' => $profileId]);
        $this->assertDatabaseMissing('posts', ['user_id' => $doctor->id]);
        Storage::disk('public')->assertMissing('doctors/photos/p.jpg');
        Storage::disk('local')->assertMissing('doctors/id_cards/i.jpg');
        Storage::disk('local')->assertMissing('doctors/certificates/c.jpg');

        $this->assertDatabaseHas('users', ['id' => $other->id]);   // another doctor stays
    }

    public function test_the_password_must_be_right_and_nothing_is_deleted_otherwise(): void
    {
        [$user, $patient] = $this->patientWithData();
        $token = $user->createToken('phone')->plainTextToken;

        $this->withToken($token)->postJson('/api/auth/delete-account', ['current_password' => 'wrong'])
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->withToken($token)->postJson('/api/auth/delete-account', [])
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('chat_messages', ['patient_id' => $patient->id]);
    }

    public function test_admin_and_guests_cannot_use_it(): void
    {
        $this->postJson('/api/auth/delete-account', ['current_password' => 'password'])->assertUnauthorized();

        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('x')->plainTextToken;
        $this->withToken($token)->postJson('/api/auth/delete-account', ['current_password' => 'password'])->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }
}
