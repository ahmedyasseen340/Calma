<?php

namespace Tests\Feature;

use App\Mail\ResetCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ResetCodeSenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_with_a_brevo_key_the_code_goes_through_the_https_api_not_smtp(): void
    {
        config(['mailapi.brevo_key' => 'test-key', 'mail.from.address' => 'no-reply@calma.test', 'mail.from.name' => 'Calma']);
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => 'abc'], 201)]);
        Mail::fake();
        $user = User::factory()->create();

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])->assertOk();

        Http::assertSent(function ($request) use ($user) {
            return $request->url() === 'https://api.brevo.com/v3/smtp/email'
                && $request->hasHeader('api-key', 'test-key')
                && $request['sender']['email'] === 'no-reply@calma.test'
                && $request['to'][0]['email'] === $user->email
                && preg_match('/\b\d{6}\b/', $request['textContent']) === 1;
        });
        Mail::assertNothingSent();
    }

    public function test_the_code_in_the_api_mail_is_the_one_that_resets_the_password(): void
    {
        config(['mailapi.brevo_key' => 'test-key']);
        Http::fake(['api.brevo.com/*' => Http::response([], 201)]);
        $user = User::factory()->create();

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])->assertOk();

        $code = null;
        Http::assertSent(function ($request) use (&$code) {
            preg_match('/\b(\d{6})\b/', $request['textContent'], $m);
            $code = $m[1] ?? null;

            return true;
        });

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email, 'code' => $code,
            'password' => 'new-password-123', 'password_confirmation' => 'new-password-123',
        ])->assertOk();
    }

    public function test_if_brevo_is_down_the_answer_is_still_the_same_and_nothing_breaks(): void
    {
        config(['mailapi.brevo_key' => 'test-key']);
        Http::fake(['api.brevo.com/*' => Http::response(['message' => 'error'], 500)]);
        $user = User::factory()->create();

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])->assertOk();
    }

    public function test_without_a_key_the_normal_mailer_is_used(): void
    {
        config(['mailapi.brevo_key' => null]);
        Http::fake();
        Mail::fake();
        $user = User::factory()->create();

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])->assertOk();

        Mail::assertSent(ResetCodeMail::class, 1);
        Http::assertNothingSent();
    }
}
