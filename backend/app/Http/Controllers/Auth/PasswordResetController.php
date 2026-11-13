<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\ResetCodeMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Forgot / reset password with a 6-digit code sent by email.
 * The hashed code is kept in Laravel's built-in `password_reset_tokens` table (no new table needed).
 */
class PasswordResetController extends Controller
{
    private const CODE_TTL_MINUTES = 15;
    private const MAX_WRONG_ATTEMPTS = 5;

    // POST /api/auth/forgot-password   body: { email }
    public function forgot(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', $data['email'])->first();

        if ($user) {
            $code = (string) random_int(100000, 999999);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                ['token' => Hash::make($code), 'created_at' => now()]
            );
            Cache::forget($this->attemptsKey($user->email));

            try {
                Mail::to($user->email)->send(new ResetCodeMail($code, self::CODE_TTL_MINUTES));
            } catch (Throwable $e) {
                report($e); // check storage/logs/laravel.log if emails don't arrive
            }
        }

        // Same answer whether or not the email exists, so nobody can probe which emails are registered.
        return response()->json([
            'message' => 'لو الإيميل مسجل عندنا، هيوصلك كود إعادة تعيين كلمة المرور.',
        ]);
    }

    // POST /api/auth/verify-reset-code   body: { email, code }
    // Screen 2: "Confirm". Checks the code only. It does NOT use it up: screen 3 sends it again.
    public function verifyCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
        ], [
            'code.digits' => 'الكود لازم يكون 6 أرقام.',
        ]);

        $this->assertCodeIsValid($data['email'], $data['code']);

        return response()->json(['message' => 'الكود صحيح.', 'valid' => true]);
    }

    // POST /api/auth/reset-password   body: { email, code, password, password_confirmation }
    // Screen 3: new password + confirmation.
    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'code.digits' => 'الكود لازم يكون 6 أرقام.',
            'password.confirmed' => 'كلمة المرور الجديدة وتأكيدها لازم يكونوا متطابقين.',
        ]);

        $this->assertCodeIsValid($data['email'], $data['code']);

        $user = User::where('email', $data['email'])->firstOrFail();
        $user->password = $data['password']; // hashed by the model cast
        $user->save();

        // The code works once, and any old login tokens stop working.
        DB::table('password_reset_tokens')->where('email', $data['email'])->delete();
        Cache::forget($this->attemptsKey($data['email']));
        $user->tokens()->delete();

        return response()->json(['message' => 'تم تغيير كلمة المرور. سجّل دخول بالباسورد الجديد.']);
    }

    /**
     * Throws one single error for "unknown email", "wrong code", "expired code" and "too many tries",
     * so nobody can find out which emails are registered.
     */
    private function assertCodeIsValid(string $email, string $code): void
    {
        $invalid = ValidationException::withMessages(['code' => ['الإيميل أو الكود غير صحيح.']]);
        $attemptsKey = $this->attemptsKey($email);

        $row = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (! $row || ! $row->created_at
            || Carbon::parse($row->created_at)->addMinutes(self::CODE_TTL_MINUTES)->isPast()) {
            throw $invalid;
        }

        // Too many wrong guesses: burn the code, the user must request a new one.
        if ((int) Cache::get($attemptsKey, 0) >= self::MAX_WRONG_ATTEMPTS) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            throw $invalid;
        }

        if (! Hash::check($code, $row->token)) {
            Cache::put($attemptsKey, (int) Cache::get($attemptsKey, 0) + 1, now()->addMinutes(self::CODE_TTL_MINUTES));
            throw $invalid;
        }
    }

    private function attemptsKey(string $email): string
    {
        return 'pwd-reset-attempts:'.sha1(strtolower($email));
    }
}
