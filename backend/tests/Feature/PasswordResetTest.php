<?php

namespace Tests\Feature;

use App\Mail\ResetCodeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The 3-screen flow:
 *   screen 1: email + "Send code"      -> POST /api/auth/forgot-password
 *   screen 2: 6-digit code + "Confirm" -> POST /api/auth/verify-reset-code
 *   screen 3: new password + confirm   -> POST /api/auth/reset-password   (then the app opens the login screen)
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const WRONG_MESSAGE = 'الإيميل أو الكود غير صحيح.';

    private function requestCode(User $user): string
    {
        Mail::fake();

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email])->assertOk();

        $code = null;
        Mail::assertSent(ResetCodeMail::class, function ($mail) use ($user, &$code) {
            $code = $mail->code;

            return $mail->hasTo($user->email);
        });

        return $code;
    }

    private function wrongCodeFor(string $code): string
    {
        return $code === '123456' ? '654321' : '123456';
    }

    private function reset(User $user, string $code, string $password = 'new-password-123', ?string $confirmation = null)
    {
        return $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'code' => $code,
            'password' => $password,
            'password_confirmation' => $confirmation ?? $password,
        ]);
    }

    // -------------------------------------------------------------- screen 1
    public function test_forgot_password_gives_the_same_answer_for_unknown_emails_and_sends_nothing(): void
    {
        Mail::fake();
        $known = User::factory()->create();

        $a = $this->postJson('/api/auth/forgot-password', ['email' => $known->email])->assertOk()->json('message');
        $b = $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com'])->assertOk()->json('message');

        $this->assertSame($a, $b);
        Mail::assertSent(ResetCodeMail::class, 1);
    }

    // -------------------------------------------------------------- screen 2
    public function test_a_wrong_code_shows_the_email_or_code_is_wrong_message(): void
    {
        $user = User::factory()->create();
        $code = $this->requestCode($user);

        $this->postJson('/api/auth/verify-reset-code', ['email' => $user->email, 'code' => $this->wrongCodeFor($code)])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', self::WRONG_MESSAGE);
    }

    public function test_an_unknown_email_gets_the_very_same_message(): void
    {
        $this->postJson('/api/auth/verify-reset-code', ['email' => 'nobody@example.com', 'code' => '123456'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', self::WRONG_MESSAGE);
    }

    public function test_the_code_must_have_6_digits(): void
    {
        $this->postJson('/api/auth/verify-reset-code', ['email' => 'a@a.com', 'code' => '12345'])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->postJson('/api/auth/verify-reset-code', ['email' => 'a@a.com', 'code' => 'abcdef'])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    public function test_the_right_code_is_accepted_and_not_used_up(): void
    {
        $user = User::factory()->create();
        $code = $this->requestCode($user);

        $this->postJson('/api/auth/verify-reset-code', ['email' => $user->email, 'code' => $code])
            ->assertOk()->assertJsonPath('valid', true);
        $this->postJson('/api/auth/verify-reset-code', ['email' => $user->email, 'code' => $code])->assertOk();

        $this->reset($user, $code)->assertOk();   // still works on screen 3
    }

    // -------------------------------------------------------------- screen 3
    public function test_the_full_three_screen_flow_changes_the_password_and_revokes_old_tokens(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $user->createToken('old-device');

        $code = $this->requestCode($user);                                                              // screen 1
        $this->postJson('/api/auth/verify-reset-code', ['email' => $user->email, 'code' => $code])->assertOk();   // screen 2
        $this->reset($user, $code)->assertOk();                                                          // screen 3

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'new-password-123'])->assertOk();   // login screen
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])->assertUnprocessable();
    }

    public function test_two_different_passwords_show_the_must_match_message_and_keep_the_code_valid(): void
    {
        $user = User::factory()->create();
        $code = $this->requestCode($user);

        $this->reset($user, $code, 'new-password-123', 'different-password')
            ->assertUnprocessable()
            ->assertJsonPath('errors.password.0', 'كلمة المرور الجديدة وتأكيدها لازم يكونوا متطابقين.');

        // the user just fixes the confirmation and tries again with the same code
        $this->reset($user, $code, 'new-password-123')->assertOk();
    }

    public function test_the_new_password_must_be_at_least_8_characters(): void
    {
        $user = User::factory()->create();
        $code = $this->requestCode($user);

        $this->reset($user, $code, 'short')->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    // -------------------------------------------------------------- safety
    public function test_code_works_only_once(): void
    {
        $user = User::factory()->create();
        $code = $this->requestCode($user);

        $this->reset($user, $code)->assertOk();
        $this->reset($user, $code, 'another-pass-456')
            ->assertUnprocessable()->assertJsonPath('errors.code.0', self::WRONG_MESSAGE);
    }

    public function test_five_wrong_guesses_burn_the_code_even_on_the_confirm_screen(): void
    {
        $user = User::factory()->create();
        $code = $this->requestCode($user);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/verify-reset-code', ['email' => $user->email, 'code' => $this->wrongCodeFor($code)])
                ->assertUnprocessable();
        }

        // Even the right code is refused now: a new code must be requested.
        $this->postJson('/api/auth/verify-reset-code', ['email' => $user->email, 'code' => $code])->assertUnprocessable();
        $this->reset($user, $code)->assertUnprocessable();
    }

    public function test_code_expires_after_15_minutes(): void
    {
        $user = User::factory()->create();
        $code = $this->requestCode($user);

        $this->travel(16)->minutes();

        $this->postJson('/api/auth/verify-reset-code', ['email' => $user->email, 'code' => $code])->assertUnprocessable();
        $this->reset($user, $code)->assertUnprocessable();
    }
}
