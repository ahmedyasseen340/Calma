<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VoiceChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function patientUser(): User
    {
        $user = User::factory()->create(['role' => 'patient']);
        Patient::factory()->create(['user_id' => $user->id]);

        return $user;
    }

    private function voice(string $name = 'voice.m4a', int $kb = 100): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kb, 'audio/mp4');
    }

    public function test_voice_message_is_analyzed_and_saved(): void
    {
        config(['chatbot.url' => 'http://ai.test']);
        Http::fake(['ai.test/*' => Http::response([
            'transcript' => 'أنا حاسس إني تعبان قوي',
            'reply' => 'أنا معاك، احكيلي أكتر',
            'is_danger' => false,
            'emotion' => 'sad',
            'emotion_score' => 0.87,
        ])]);
        Sanctum::actingAs($this->patientUser());

        $this->post('/api/patient/chat/voice', ['audio' => $this->voice()], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('chat.message', 'أنا حاسس إني تعبان قوي')
            ->assertJsonPath('chat.emotion', 'sad')
            ->assertJsonPath('chat.emotion_score', 0.87)
            ->assertJsonPath('chat.has_audio', true)
            ->assertJsonMissingPath('chat.audio_path');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/voice'));
        Storage::disk('local')->assertExists(ChatMessage::first()->audio_path);
    }

    public function test_unknown_emotion_label_is_stored_as_null_and_score_is_clamped(): void
    {
        config(['chatbot.url' => 'http://ai.test']);
        Http::fake(['ai.test/*' => Http::response([
            'reply' => 'تمام', 'emotion' => 'excited', 'emotion_score' => 4,
        ])]);
        Sanctum::actingAs($this->patientUser());

        $this->post('/api/patient/chat/voice', ['audio' => $this->voice()], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('chat.emotion', null)
            ->assertJsonPath('chat.message', '🎤 رسالة صوتية');

        $this->assertEquals(1.0, ChatMessage::first()->emotion_score);
    }

    public function test_recording_is_kept_even_if_the_ai_is_down(): void
    {
        config(['chatbot.url' => 'http://ai.test']);
        Http::fake(['ai.test/*' => Http::response([], 500)]);
        Sanctum::actingAs($this->patientUser());

        $this->post('/api/patient/chat/voice', ['audio' => $this->voice()], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('ai_available', false)
            ->assertJsonPath('chat.emotion', null);

        $this->assertDatabaseCount('chat_messages', 1);
    }

    public function test_stub_is_used_when_no_ai_url(): void
    {
        config(['chatbot.url' => null]);
        Sanctum::actingAs($this->patientUser());

        $this->post('/api/patient/chat/voice', ['audio' => $this->voice()], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('chat.emotion', 'neutral');
    }

    public function test_only_audio_files_up_to_10mb_are_accepted(): void
    {
        Sanctum::actingAs($this->patientUser());

        $this->post('/api/patient/chat/voice', ['audio' => UploadedFile::fake()->create('virus.exe', 10)], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('audio');

        $this->post('/api/patient/chat/voice', ['audio' => $this->voice('big.mp3', 11000)], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('audio');

        $this->post('/api/patient/chat/voice', [], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('audio');
    }

    public function test_patient_can_replay_only_their_own_recording(): void
    {
        config(['chatbot.url' => null]);
        $owner = $this->patientUser();
        Sanctum::actingAs($owner);

        $id = $this->post('/api/patient/chat/voice', ['audio' => $this->voice()], ['Accept' => 'application/json'])
            ->json('chat.id');

        $this->get("/api/patient/chat/{$id}/audio")->assertOk();

        Sanctum::actingAs($this->patientUser());
        $this->getJson("/api/patient/chat/{$id}/audio")->assertNotFound();
    }

    public function test_text_messages_have_no_audio_and_doctors_cannot_send_voice(): void
    {
        config(['chatbot.url' => null]);
        Sanctum::actingAs($this->patientUser());
        $this->postJson('/api/patient/chat', ['message' => 'مرحبا'])
            ->assertCreated()->assertJsonPath('chat.has_audio', false)->assertJsonPath('chat.emotion', null);

        Sanctum::actingAs(User::factory()->create(['role' => 'doctor']));
        $this->post('/api/patient/chat/voice', ['audio' => $this->voice()], ['Accept' => 'application/json'])->assertForbidden();
    }
}
