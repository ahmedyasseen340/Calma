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

    private function patient(string $first = 'Zzyzx', string $last = 'Quuxford'): User
    {
        $user = User::factory()->create(['role' => 'patient', 'first_name' => $first, 'last_name' => $last, 'email' => strtolower($first).'@secret.test']);
        Patient::factory()->create(['user_id' => $user->id]);

        return $user;
    }

    private function doctor(string $status = 'active'): User
    {
        $user = User::factory()->create(['role' => 'doctor', 'first_name' => 'Sara', 'last_name' => 'Hassan']);
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
            'status' => $status,
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

    public function test_a_patient_post_is_anonymous_for_everybody_else(): void
    {
        $patient = $this->patient();
        Post::create(['user_id' => $patient->id, 'content' => 'تجربتي مع القلق']);

        foreach ([$this->doctor(), $this->patient('Other', 'Person')] as $viewer) {
            Sanctum::actingAs($viewer);
            $response = $this->getJson('/api/posts')->assertOk();

            $post = $response->json('data.0');
            $this->assertSame('patient', $post['type']);
            $this->assertTrue($post['is_anonymous']);
            $this->assertNull($post['author']);
            $this->assertFalse($post['is_mine']);

            // nothing that could identify the author is in the answer
            $this->assertStringNotContainsString('Zzyzx', $response->getContent());
            $this->assertStringNotContainsString('Quuxford', $response->getContent());
            $this->assertStringNotContainsString('@secret.test', $response->getContent());
            $this->assertStringNotContainsString('user_id', $response->getContent());
        }
    }

    public function test_the_author_of_an_anonymous_post_still_knows_it_is_theirs(): void
    {
        $patient = $this->patient();
        Sanctum::actingAs($patient);

        $created = $this->postJson('/api/posts', ['content' => 'بوستي'])->assertCreated();
        $created->assertJsonPath('post.is_mine', true)->assertJsonPath('post.author', null);

        $this->getJson('/api/posts')->assertJsonPath('data.0.is_mine', true);
    }

    public function test_a_doctor_post_shows_the_doctor_and_the_clinic_info(): void
    {
        $doctor = $this->doctor();
        Post::create(['user_id' => $doctor->id, 'content' => 'مرحباً']);

        Sanctum::actingAs($this->patient());
        $post = $this->getJson('/api/posts')->assertOk()->json('data.0');

        $this->assertSame('doctor', $post['type']);
        $this->assertFalse($post['is_anonymous']);
        $this->assertSame('Sara', $post['author']['first_name']);
        $this->assertSame('Psychiatry', $post['author']['specialization']);
        $this->assertSame('01000000000', $post['author']['phone']);
        $this->assertSame('https://clinic.example.com', $post['author']['clinic_url']);
        $this->assertStringContainsString('doctors/photos/a.jpg', $post['author']['photo_url']);
        $this->assertArrayNotHasKey('email', $post['author']);
    }

    public function test_the_feed_can_be_filtered_by_type(): void
    {
        Post::create(['user_id' => $this->doctor()->id, 'content' => 'doctor post']);
        Post::create(['user_id' => $this->patient()->id, 'content' => 'patient post']);

        Sanctum::actingAs($this->patient('Viewer', 'Person'));

        $this->getJson('/api/posts')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/posts?type=doctor')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.type', 'doctor');
        $this->getJson('/api/posts?type=patient')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.type', 'patient');
        $this->getJson('/api/posts?type=admin')->assertUnprocessable();
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
        $this->deleteJson("/api/posts/{$post->id}")->assertForbidden();   // the admin deletes through /api/admin/posts

        $this->assertSame('بوست الدكتور', $post->fresh()->content);
    }

    public function test_a_doctor_who_is_not_active_cannot_use_the_posts(): void
    {
        $pending = $this->doctor('pending');
        Sanctum::actingAs($pending);

        $this->postJson('/api/posts', ['content' => 'x'])->assertForbidden()->assertJsonPath('status', 'pending');
        $this->getJson('/api/posts')->assertForbidden();
    }
}
