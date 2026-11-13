<?php

namespace Tests\Feature;

use App\Models\DoctorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DoctorRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'first_name' => 'Sara',
            'last_name' => 'Hassan',
            'email' => 'sara@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'doctor',
            'dob' => '1985-05-20',
            'specialization' => 'Psychiatry',
            'phone' => '01012345678',
            'clinic_url' => 'https://clinic.example.com',
            'bio' => 'استشاري طب نفسي',
            'photo' => UploadedFile::fake()->image('me.jpg'),
            'id_card_img' => UploadedFile::fake()->image('id.jpg'),
            'certificate_img' => UploadedFile::fake()->image('cert.jpg'),
        ], $override);
    }

    private function register(array $override = [])
    {
        return $this->post('/api/auth/register', $this->payload($override), ['Accept' => 'application/json']);
    }

    public function test_doctor_registers_with_phone_clinic_link_photo_and_bio(): void
    {
        $this->register()
            ->assertCreated()
            ->assertJsonPath('user.doctor_profile.phone', '01012345678')
            ->assertJsonPath('user.doctor_profile.clinic_url', 'https://clinic.example.com')
            ->assertJsonPath('user.doctor_profile.status', 'pending')
            ->assertJsonStructure(['user' => ['doctor_profile' => ['photo_url']]])
            ->assertJsonMissingPath('user.doctor_profile.photo');

        $profile = DoctorProfile::first();
        Storage::disk('public')->assertExists($profile->photo);   // visible to patients
        Storage::disk('local')->assertExists($profile->id_card_img); // private
    }

    public function test_clinic_link_and_bio_are_optional(): void
    {
        $this->register(['clinic_url' => null, 'bio' => null])->assertCreated();

        $this->assertNull(DoctorProfile::first()->clinic_url);
        $this->assertNull(DoctorProfile::first()->bio);
    }

    public function test_bio_can_be_300_characters_but_not_301(): void
    {
        $this->register(['bio' => str_repeat('ب', 300)])->assertCreated();

        $this->register(['email' => 'other@example.com', 'bio' => str_repeat('ب', 301),
                         'photo' => UploadedFile::fake()->image('me2.jpg'),
                         'id_card_img' => UploadedFile::fake()->image('id2.jpg'),
                         'certificate_img' => UploadedFile::fake()->image('c2.jpg')])
            ->assertUnprocessable()->assertJsonValidationErrors('bio');
    }

    public function test_photo_and_phone_are_required_and_validated(): void
    {
        $payload = $this->payload();
        unset($payload['photo']);
        $this->post('/api/auth/register', $payload, ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('photo');

        $this->register(['photo' => UploadedFile::fake()->create('me.pdf', 10, 'application/pdf')])
            ->assertUnprocessable()->assertJsonValidationErrors('photo');

        $this->register(['phone' => 'not-a-phone'])
            ->assertUnprocessable()->assertJsonValidationErrors('phone');

        $this->register(['clinic_url' => 'not a link'])
            ->assertUnprocessable()->assertJsonValidationErrors('clinic_url');
    }
}
