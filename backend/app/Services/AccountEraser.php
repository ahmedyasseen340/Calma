<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes an account for good: the user row (the database removes the profile, chat messages, mood logs,
 * completed challenges and posts through ON DELETE CASCADE), the login tokens, the reset code,
 * and the private files (voice recordings, ID card, certificate) and the doctor's photo.
 */
class AccountEraser
{
    public function erase(User $user): void
    {
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
    }
}
