<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    private function patientUser(): User
    {
        $user = User::factory()->create(['role' => 'patient']);
        Patient::factory()->create(['user_id' => $user->id]);

        return $user;
    }

    public function test_stub_reply_is_saved_when_no_ai_url(): void
    {
        config(['chatbot.url' => null]);
        Sanctum::actingAs($this->patientUser());

        $this->postJson('/api/patient/chat', ['message' => 'أنا متضايق النهارده'])
            ->assertCreated()
            ->assertJsonPath('chat.is_danger', false);

        $this->assertDatabaseCount('chat_messages', 1);
    }

    public function test_ai_reply_and_danger_flag_are_saved(): void
    {
        config(['chatbot.url' => 'http://ai.test']);
        Http::fake(['ai.test/*' => Http::response(['reply' => 'أنا معاك', 'is_danger' => true])]);
        Sanctum::actingAs($this->patientUser());

        $this->postJson('/api/patient/chat', ['message' => 'مش قادر أكمل'])
            ->assertCreated()
            ->assertJsonPath('chat.reply', 'أنا معاك')
            ->assertJsonPath('chat.is_danger', true);
    }

    public function test_message_is_saved_even_if_ai_is_down(): void
    {
        config(['chatbot.url' => 'http://ai.test']);
        Http::fake(['ai.test/*' => Http::response([], 500)]);
        Sanctum::actingAs($this->patientUser());

        $this->postJson('/api/patient/chat', ['message' => 'مساء الخير'])
            ->assertCreated()
            ->assertJsonPath('ai_available', false);

        $this->assertDatabaseCount('chat_messages', 1);
    }

    public function test_doctor_cannot_use_patient_chat(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'doctor']));

        $this->postJson('/api/patient/chat', ['message' => 'hi'])->assertForbidden();
    }
}
