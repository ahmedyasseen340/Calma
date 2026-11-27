<?php

namespace Tests\Feature;

use App\Models\DoctorProfile;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DoctorDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private function doctor(string $status, string $specialization = 'Psychiatry', string $first = 'Sara'): DoctorProfile
    {
        $u = User::factory()->create(['role' => 'doctor', 'first_name' => $first, 'last_name' => 'Hassan']);

        return DoctorProfile::create([
            'user_id' => $u->id, 'specialization' => $specialization, 'dob' => '1985-05-20',
            'id_card_img' => 'private/id.jpg', 'certificate_img' => 'private/cert.jpg',
            'phone' => '01012345678', 'clinic_url' => 'https://clinic.example.com',
            'photo' => 'doctors/photos/p.jpg', 'bio' => 'bio text', 'status' => $status,
        ]);
    }

    private function asPatient(): void
    {
        $u = User::factory()->create(['role' => 'patient']);
        Patient::factory()->create(['user_id' => $u->id]);
        Sanctum::actingAs($u);
    }

    public function test_patient_sees_only_active_doctors(): void
    {
        $this->doctor('active', 'Psychiatry', 'Active');
        $this->doctor('pending', 'Psychiatry', 'Pending');
        $this->doctor('rejected', 'Psychiatry', 'Rejected');
        $this->asPatient();

        $response = $this->getJson('/api/doctors')->assertOk()->assertJsonCount(1, 'data');

        $this->assertSame('Active', $response->json('data.0.first_name'));
        $this->assertStringNotContainsString('Pending', $response->getContent());
        $this->assertStringNotContainsString('Rejected', $response->getContent());
    }

    public function test_the_card_has_the_contact_info_and_nothing_private(): void
    {
        $this->doctor('active');
        $this->asPatient();

        $response = $this->getJson('/api/doctors')->assertOk();

        $doctor = $response->json('data.0');
        $this->assertSame(['id', 'first_name', 'last_name', 'specialization', 'phone', 'clinic_url', 'bio', 'photo_url'], array_keys($doctor));
        $this->assertSame('01012345678', $doctor['phone']);
        $this->assertSame('https://clinic.example.com', $doctor['clinic_url']);

        foreach (['dob', 'status', 'private/id.jpg', 'private/cert.jpg', 'email', '1985'] as $secret) {
            $this->assertStringNotContainsString($secret, $response->getContent());
        }
    }

    public function test_search_by_specialization(): void
    {
        $this->doctor('active', 'Child Psychiatry', 'A');
        $this->doctor('active', 'Clinical Psychology', 'B');
        $this->asPatient();

        $this->getJson('/api/doctors?specialization=PSYCHIATRY')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.first_name', 'A');
        $this->getJson('/api/doctors')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_one_doctor_is_shown_only_if_active(): void
    {
        $active = $this->doctor('active');
        $pending = $this->doctor('pending');
        $this->asPatient();

        $this->getJson("/api/doctors/{$active->id}")->assertOk()->assertJsonPath('doctor.specialization', 'Psychiatry');
        $this->getJson("/api/doctors/{$pending->id}")->assertNotFound();
    }

    public function test_only_patients_can_browse_doctors(): void
    {
        $this->getJson('/api/doctors')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson('/api/doctors')->assertForbidden();
    }
}
