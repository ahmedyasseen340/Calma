<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Models\DoctorProfile;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Patients and doctors edit their own data. Send only the fields you want to change.
 *
 * PATIENT can edit : first_name, last_name, dob (still older than 16), marital_status.
 *         cannot   : email, gender.
 * DOCTOR  can edit : first_name, last_name, phone, clinic_url, bio (max 300), photo, specialization.
 *         cannot   : email, dob, ID card, certificate, status.
 *
 * Changing the specialization sends the doctor back to `pending` until an admin approves again
 * (the doctor is logged out meanwhile). Any other change stays active.
 * Fields that are not allowed are simply ignored if they are sent.
 */
class ProfileController extends Controller
{
    // POST /api/patient/profile
    public function updatePatient(Request $request): JsonResponse
    {
        $user = $request->user();
        $patient = $user->patient;
        abort_unless($patient, 403);

        $oldestAllowedBirthDate = now()->subYears(Patient::MIN_AGE)->toDateString();

        $data = $request->validate([
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'dob' => ['sometimes', 'required', 'date', 'after:1900-01-01', 'before:'.$oldestAllowedBirthDate],
            'marital_status' => ['sometimes', 'required', Rule::in(Patient::MARITAL_STATUSES)],
        ], [
            'dob.before' => 'لازم السن يكون أكبر من '.Patient::MIN_AGE.' سنة.',
        ]);

        DB::transaction(function () use ($user, $patient, $data) {
            $user->fill(Arr::only($data, ['first_name', 'last_name']))->save();
            $patient->fill(Arr::only($data, ['dob', 'marital_status']))->save();
        });

        return response()->json([
            'message' => 'تم تحديث بياناتك.',
            'user' => $user->fresh()->load('patient'),
        ]);
    }

    // POST /api/doctor/profile   (multipart/form-data when sending a new photo)
    public function updateDoctor(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->doctorProfile;
        abort_unless($profile, 403);

        $data = $request->validate([
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'specialization' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'required', 'string', 'regex:/^\+?[0-9]{8,15}$/'],
            'clinic_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'photo' => ['sometimes', 'image', 'max:4096'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:'.DoctorProfile::BIO_MAX_LENGTH],
        ], [
            'phone.regex' => 'رقم التليفون لازم يكون أرقام بس (8 لـ 15 رقم)، ويقدر يبدأ بـ +.',
            'bio.max' => 'البايو لازم ميزيدش عن '.DoctorProfile::BIO_MAX_LENGTH.' حرف.',
        ]);

        $needsReapproval = isset($data['specialization']) && $data['specialization'] !== $profile->specialization;

        $oldPhoto = $profile->photo;
        $newPhoto = $request->hasFile('photo') ? $request->file('photo')->store('doctors/photos', 'public') : null;

        DB::transaction(function () use ($user, $profile, $data, $newPhoto, $needsReapproval) {
            $user->fill(Arr::only($data, ['first_name', 'last_name']))->save();

            $profile->fill(Arr::only($data, ['specialization', 'phone', 'clinic_url', 'bio']));
            if ($newPhoto) {
                $profile->photo = $newPhoto;
            }
            if ($needsReapproval) {
                $profile->status = DoctorProfile::STATUS_PENDING;
            }
            $profile->save();
        });

        if ($newPhoto && $oldPhoto) {
            Storage::disk('public')->delete($oldPhoto);
        }

        if ($needsReapproval) {
            $user->tokens()->delete(); // back to "pending": no login until the admin approves
        }

        return response()->json([
            'message' => $needsReapproval
                ? 'تم تحديث بياناتك. التخصص الجديد محتاج موافقة الأدمن، وهيتسجّل خروجك لحد ما يوافق.'
                : 'تم تحديث بياناتك.',
            'requires_approval' => $needsReapproval,
            'user' => $user->fresh()->load('doctorProfile'),
        ]);
    }
}
