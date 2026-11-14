<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * A patient or a doctor deletes their own account, for good.
 *
 * The database does most of the work: every table that belongs to the user (patient / doctor profile,
 * chat messages, mood logs, completed challenges, posts) is linked with ON DELETE CASCADE.
 * Here we also remove the login tokens, the reset code and the private files (voice recordings,
 * ID card, certificate) and the doctor's photo, because they are personal data too.
 */
class AccountController extends Controller
{
    // POST /api/auth/delete-account   body: { current_password }
    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['كلمة المرور غير صحيحة.'],
            ]);
        }

        // Remember the files first: after the rows are gone we would not know their paths.
        $privateFiles = [];
        $publicFiles = [];

        if ($user->isPatient() && $user->patient) {
            $privateFiles = $user->patient->chatMessages()->whereNotNull('audio_path')->pluck('audio_path')->all();
        }

        if ($user->isDoctor() && $user->doctorProfile) {
            $profile = $user->doctorProfile;
            $privateFiles = [$profile->id_card_img, $profile->certificate_img];
            $publicFiles = [$profile->photo];
        }

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            $user->delete();
        });

        Storage::delete(array_filter($privateFiles));
        Storage::disk('public')->delete(array_filter($publicFiles));

        return response()->json(['message' => 'تم مسح حسابك وكل بياناتك نهائياً.']);
    }
}
