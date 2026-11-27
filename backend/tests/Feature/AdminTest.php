<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\DoctorProfile;
use App\Models\Patient;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        return $admin;
    }

    private function doctor(string $status = 'pending', string $email = 'doc@example.com'): User
    {
        $u = User::factory()->create(['role' => 'doctor', 'email' => $email, 'first_name' => 'Sara', 'last_name' => 'Hassan']);
        Storage::disk('public')->put("doctors/photos/{$email}.jpg", 'x');
        Storage::disk('local')->put("doctors/id_cards/{$email}.jpg", 'id-card-bytes');
        Storage::disk('local')->put("doctors/certificates/{$email}.jpg", 'certificate-bytes');
        DoctorProfile::create([
            'user_id' => $u->id, 'specialization' => 'Psychiatry', 'dob' => '1985-05-20',
            'id_card_img' => "doctors/id_cards/{$email}.jpg", 'certificate_img' => "doctors/certificates/{$email}.jpg",
            'phone' => '01012345678', 'photo' => "doctors/photos/{$email}.jpg", 'status' => $status,
        ]);

        return $u;
    }

    private function patient(string $email = 'pat@example.com'): User
    {
        $u = User::factory()->create(['role' => 'patient', 'email' => $email, 'first_name' => 'Ali', 'last_name' => 'Mostafa']);
        Patient::factory()->create(['user_id' => $u->id]);

        return $u;
    }

    // -------------------------------------------------------------- statistics
    public function test_stats_count_patients_doctors_and_content(): void
    {
        $this->patient('a@a.com');
        $this->patient('b@b.com');
        $this->doctor('active', 'd1@x.com');
        $this->doctor('pending', 'd2@x.com');
        $this->doctor('pending', 'd3@x.com');
        $this->doctor('rejected', 'd4@x.com');
        Post::create(['user_id' => $this->patient('c@c.com')->id, 'content' => 'x']);
        $this->admin();

        $this->getJson('/api/admin/stats')->assertOk()
            ->assertJsonPath('patients', 3)
            ->assertJsonPath('doctors.active', 1)
            ->assertJsonPath('doctors.pending', 2)
            ->assertJsonPath('doctors.rejected', 1)
            ->assertJsonPath('posts', 1);
    }

    public function test_only_an_admin_can_use_the_admin_endpoints(): void
    {
        Sanctum::actingAs($this->patient());
        $this->getJson('/api/admin/stats')->assertForbidden();
        $this->getJson('/api/admin/patients')->assertForbidden();
        $this->getJson('/api/admin/posts')->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/admin/stats')->assertUnauthorized();
    }

    // -------------------------------------------------------------- doctor file and documents
    public function test_admin_opens_the_doctor_file_and_sees_the_id_card_and_certificate(): void
    {
        $doctor = $this->doctor();
        $id = $doctor->doctorProfile->id;
        $this->admin();

        $this->getJson("/api/admin/doctors/{$id}")->assertOk()
            ->assertJsonPath('doctor.specialization', 'Psychiatry')
            ->assertJsonPath('doctor.user.email', 'doc@example.com')
            ->assertJsonStructure(['documents' => ['id_card', 'certificate']]);

        $this->get("/api/admin/doctors/{$id}/documents/id_card")->assertOk();
        $this->get("/api/admin/doctors/{$id}/documents/certificate")->assertOk();
        $this->getJson("/api/admin/doctors/{$id}/documents/passport")->assertNotFound();
    }

    public function test_the_documents_are_private(): void
    {
        $doctor = $this->doctor();
        $id = $doctor->doctorProfile->id;

        Sanctum::actingAs($this->patient());
        $this->getJson("/api/admin/doctors/{$id}/documents/id_card")->assertForbidden();

        Sanctum::actingAs($doctor->fresh());   // even the doctor's own documents are not served to a non-admin
        $this->getJson("/api/admin/doctors/{$id}/documents/id_card")->assertForbidden();
    }

    // -------------------------------------------------------------- approve / reject / delete
    public function test_approve_and_reject_work_with_post_and_with_patch(): void
    {
        $a = $this->doctor('pending', 'a@x.com')->doctorProfile->id;
        $b = $this->doctor('pending', 'b@x.com')->doctorProfile->id;
        $this->admin();

        $this->postJson("/api/admin/doctors/{$a}/approve")->assertOk();
        $this->patchJson("/api/admin/doctors/{$b}/reject")->assertOk();
        $this->assertSame('active', DoctorProfile::find($a)->status);
        $this->assertSame('rejected', DoctorProfile::find($b)->status);

        $this->patchJson("/api/admin/doctors/{$b}/approve")->assertOk();
        $this->assertSame('active', DoctorProfile::find($b)->status);
    }

    public function test_rejecting_a_doctor_logs_them_out_and_blocks_the_old_token(): void
    {
        $doctor = $this->doctor('active');
        $token = $doctor->createToken('phone')->plainTextToken;

        $this->admin();
        $this->patchJson("/api/admin/doctors/{$doctor->doctorProfile->id}/reject")->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/posts', ['content' => 'x'])->assertUnauthorized();
    }

    public function test_an_old_token_of_a_doctor_who_is_no_longer_active_is_refused(): void
    {
        $doctor = $this->doctor('active');
        $token = $doctor->createToken('phone')->plainTextToken;
        $doctor->doctorProfile->update(['status' => 'pending']);   // e.g. changed in the database

        $this->withToken($token)->postJson('/api/posts', ['content' => 'x'])
            ->assertForbidden()->assertJsonPath('status', 'pending');
    }

    public function test_admin_deletes_a_doctor_with_the_files_and_the_posts(): void
    {
        $doctor = $this->doctor('active');
        Post::create(['user_id' => $doctor->id, 'content' => 'نصيحة']);
        $other = $this->doctor('active', 'other@x.com');
        $this->admin();

        $this->deleteJson("/api/admin/doctors/{$doctor->doctorProfile->id}")->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $doctor->id]);
        $this->assertDatabaseMissing('doctor_profiles', ['user_id' => $doctor->id]);
        $this->assertDatabaseMissing('posts', ['user_id' => $doctor->id]);
        Storage::disk('local')->assertMissing('doctors/id_cards/doc@example.com.jpg');
        Storage::disk('public')->assertMissing('doctors/photos/doc@example.com.jpg');
        $this->assertDatabaseHas('users', ['id' => $other->id]);
    }

    // -------------------------------------------------------------- patients
    public function test_admin_lists_and_deletes_patients(): void
    {
        $patient = $this->patient();
        Storage::disk('local')->put('chat-audio/a.m4a', 'sound');
        ChatMessage::create(['patient_id' => $patient->patient->id, 'message' => 'hi', 'audio_path' => 'chat-audio/a.m4a']);
        $this->admin();

        $this->getJson('/api/admin/patients')->assertOk()
            ->assertJsonPath('data.0.user.email', 'pat@example.com')
            ->assertJsonStructure(['data' => [['age', 'gender', 'marital_status']]]);

        $this->deleteJson("/api/admin/patients/{$patient->patient->id}")->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $patient->id]);
        $this->assertDatabaseMissing('chat_messages', ['message' => 'hi']);
        Storage::disk('local')->assertMissing('chat-audio/a.m4a');
    }

    // -------------------------------------------------------------- moderation
    public function test_admin_sees_the_real_author_of_every_post_and_can_delete_any_post(): void
    {
        $patient = $this->patient();
        $doctor = $this->doctor('active');
        $anonymous = Post::create(['user_id' => $patient->id, 'content' => 'patient post']);
        Post::create(['user_id' => $doctor->id, 'content' => 'doctor post']);
        $this->admin();

        $this->getJson('/api/admin/posts')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/admin/posts?type=patient')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user.first_name', 'Ali')       // the admin sees who wrote it
            ->assertJsonPath('data.0.user.email', 'pat@example.com');

        $this->deleteJson("/api/admin/posts/{$anonymous->id}")->assertOk();
        $this->assertDatabaseMissing('posts', ['id' => $anonymous->id]);
        $this->assertDatabaseCount('posts', 1);
    }
}
