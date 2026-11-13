<?php

namespace Tests\Feature;

use App\Models\DoctorProfile;
use App\Models\Patient;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    private function patient(): User
    {
        $user = User::factory()->create(['role' => 'patient']);
        Patient::factory()->create(['user_id' => $user->id]);

        return $user;
    }

    private function doctor(): User
    {
        $user = User::factory()->create(['role' => 'doctor']);
        DoctorProfile::create([
            'user_id' => $user->id,
            'specialization' => 'Psychiatry',
            'dob' => '1985-05-20',
            'id_card_img' => 'x.jpg',
            'certificate_img' => 'y.jpg',
            'phone' => '01000000000',
            'clinic_url' => 'https://clinic.example.com',
            'photo' => 'doctors/photos/a.jpg',
            'bio' => 'استشاري طب نفسي',
            'status' => 'active',
        ]);

        return $user;
    }

    public function test_patient_and_doctor_can_both_write_posts(): void
    {
        Sanctum::actingAs($this->patient());
        $this->postJson('/api/posts', ['content' => 'أنا حاسس بتحسن النهارده'])->assertCreated();

        Sanctum::actingAs($this->doctor());
        $this->postJson('/api/posts', ['content' => 'نصيحة: النوم بدري بيفرق'])->assertCreated();

        $this->assertDatabaseCount('posts', 2);
    }

    public function test_both_roles_see_all_posts_and_doctor_posts_show_the_clinic_info(): void
    {
        $doctor = $this->doctor();
        Post::create(['user_id' => $doctor->id, 'content' => 'مرحباً']);
        Post::create(['user_id' => $this->patient()->id, 'content' => 'شكراً']);

        Sanctum::actingAs($this->patient());
        $this->getJson('/api/posts')->assertOk()->assertJsonCount(2, 'data');

        Sanctum::actingAs($doctor);
        $response = $this->getJson('/api/posts')->assertOk();
        $response->assertJsonCount(2, 'data');

        $doctorPost = collect($response->json('data'))->firstWhere('user.role', 'doctor');
        $this->assertSame('Psychiatry', $doctorPost['user']['doctor_profile']['specialization']);
        $this->assertSame('01000000000', $doctorPost['user']['doctor_profile']['phone']);
        $this->assertSame('https://clinic.example.com', $doctorPost['user']['doctor_profile']['clinic_url']);
        $this->assertStringContainsString('doctors/photos/a.jpg', $doctorPost['user']['doctor_profile']['photo_url']);
        $this->assertArrayNotHasKey('photo', $doctorPost['user']['doctor_profile']);
        $this->assertArrayNotHasKey('email', $doctorPost['user']);
    }

    public function test_content_is_required_and_admin_or_guest_cannot_use_posts(): void
    {
        Sanctum::actingAs($this->patient());
        $this->postJson('/api/posts', ['content' => ''])->assertUnprocessable();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson('/api/posts')->assertForbidden();
    }

    public function test_guest_gets_401(): void
    {
        $this->getJson('/api/posts')->assertUnauthorized();
    }

    public function test_author_can_edit_and_delete_their_own_post(): void
    {
        $patient = $this->patient();
        $post = Post::create(['user_id' => $patient->id, 'content' => 'قبل التعديل']);
        Sanctum::actingAs($patient);

        $this->postJson("/api/posts/{$post->id}", ['content' => 'بعد التعديل'])
            ->assertOk()->assertJsonPath('post.content', 'بعد التعديل');
        $this->assertSame('بعد التعديل', $post->fresh()->content);

        $this->postJson("/api/posts/{$post->id}", ['content' => ''])->assertUnprocessable();

        $this->deleteJson("/api/posts/{$post->id}")->assertOk();
        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
        $this->getJson("/api/posts/{$post->id}")->assertNotFound();
    }

    public function test_doctor_can_edit_and_delete_their_own_post(): void
    {
        $doctor = $this->doctor();
        $post = Post::create(['user_id' => $doctor->id, 'content' => 'نصيحة']);
        Sanctum::actingAs($doctor);

        $this->postJson("/api/posts/{$post->id}", ['content' => 'نصيحة محدّثة'])->assertOk();
        $this->deleteJson("/api/posts/{$post->id}")->assertOk();
    }

    public function test_nobody_can_edit_or_delete_someone_elses_post(): void
    {
        $doctor = $this->doctor();
        $post = Post::create(['user_id' => $doctor->id, 'content' => 'بوست الدكتور']);

        Sanctum::actingAs($this->patient());
        $this->postJson("/api/posts/{$post->id}", ['content' => 'اختراق'])->assertForbidden();
        $this->deleteJson("/api/posts/{$post->id}")->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->deleteJson("/api/posts/{$post->id}")->assertForbidden();   // admins use the patient/doctor endpoints only

        $this->assertSame('بوست الدكتور', $post->fresh()->content);
    }
}
