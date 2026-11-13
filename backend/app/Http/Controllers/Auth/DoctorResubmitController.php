<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\DoctorProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * A doctor whose registration was REJECTED by the admin can send all the data again.
 * Not logged in (a rejected doctor gets no token), so the doctor proves who they are with the
 * email and password they registered with. The profile goes back to `pending` for a new review.
 */
class DoctorResubmitController extends Controller
{
    // POST /api/auth/doctor/resubmit   (multipart/form-data)
    public function __invoke(Request $request): JsonResponse
    {
        // 1) who is this? (cheap check first, before looking at any file)
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['البريد الإلكتروني أو كلمة المرور غير صحيحة.'],
            ]);
        }

        $profile = $user->isDoctor() ? $user->doctorProfile : null;

        abort_unless(
            $profile && $profile->status === DoctorProfile::STATUS_REJECTED,
            403,
            'إعادة إرسال البيانات متاحة للدكتور اللي طلبه اتّرفض بس.'
        );

        // 2) the new data: same fields as the registration
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'dob' => ['required', 'date', 'after:1900-01-01', 'before:today'],
            'specialization' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{8,15}$/'],
            'clinic_url' => ['nullable', 'url', 'max:255'],
            'bio' => ['nullable', 'string', 'max:'.DoctorProfile::BIO_MAX_LENGTH],
            'photo' => ['required', 'image', 'max:4096'],
            'id_card_img' => ['required', 'image', 'max:4096'],
            'certificate_img' => ['required', 'image', 'max:4096'],
        ], [
            'phone.regex' => 'رقم التليفون لازم يكون أرقام بس (8 لـ 15 رقم)، ويقدر يبدأ بـ +.',
            'bio.max' => 'البايو لازم ميزيدش عن '.DoctorProfile::BIO_MAX_LENGTH.' حرف.',
        ]);

        $old = [
            'public' => $profile->photo,
            'local' => [$profile->id_card_img, $profile->certificate_img],
        ];

        DB::transaction(function () use ($request, $user, $profile, $data) {
            $user->update(['first_name' => $data['first_name'], 'last_name' => $data['last_name']]);

            $profile->update([
                'specialization' => $data['specialization'],
                'dob' => $data['dob'],
                'phone' => $data['phone'],
                'clinic_url' => $data['clinic_url'] ?? null,
                'bio' => $data['bio'] ?? null,
                'photo' => $request->file('photo')->store('doctors/photos', 'public'),
                'id_card_img' => $request->file('id_card_img')->store('doctors/id_cards'),
                'certificate_img' => $request->file('certificate_img')->store('doctors/certificates'),
                'status' => DoctorProfile::STATUS_PENDING,
            ]);
        });

        // the rejected files are not needed any more
        if ($old['public']) {
            Storage::disk('public')->delete($old['public']);
        }
        Storage::delete(array_filter($old['local']));

        return response()->json([
            'message' => 'تم إرسال بياناتك من جديد، هتتراجع من الإدارة.',
            'status' => 'pending',
        ]);
    }
}
