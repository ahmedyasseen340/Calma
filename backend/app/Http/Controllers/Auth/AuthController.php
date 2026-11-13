<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\DoctorProfile;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $isPatient = $request->input('role') === User::ROLE_PATIENT;
        $oldestAllowedBirthDate = now()->subYears(Patient::MIN_AGE)->toDateString();

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in([User::ROLE_PATIENT, User::ROLE_DOCTOR])],

            // date of birth: patients must be older than Patient::MIN_AGE (16) years
            'dob' => ['required', 'date', 'after:1900-01-01', $isPatient ? 'before:'.$oldestAllowedBirthDate : 'before:today'],

            // patient-only fields
            'gender' => ['required_if:role,patient', Rule::in(Patient::GENDERS)],
            'marital_status' => ['required_if:role,patient', Rule::in(Patient::MARITAL_STATUSES)],

            // doctor-only fields
            'specialization' => ['required_if:role,doctor', 'string', 'max:255'],
            'id_card_img' => ['required_if:role,doctor', 'image', 'max:4096'],
            'certificate_img' => ['required_if:role,doctor', 'image', 'max:4096'],
            'phone' => ['required_if:role,doctor', 'string', 'regex:/^\+?[0-9]{8,15}$/'],
            'clinic_url' => ['nullable', 'url', 'max:255'],
            'photo' => ['required_if:role,doctor', 'image', 'max:4096'],
            'bio' => ['nullable', 'string', 'max:'.DoctorProfile::BIO_MAX_LENGTH],
        ], [
            'phone.regex' => 'رقم التليفون لازم يكون أرقام بس (8 لـ 15 رقم)، ويقدر يبدأ بـ +.',
            'bio.max' => 'البايو لازم ميزيدش عن '.DoctorProfile::BIO_MAX_LENGTH.' حرف.',
            'dob.before' => $isPatient
                ? 'لازم السن يكون أكبر من '.Patient::MIN_AGE.' سنة.'
                : 'تاريخ الميلاد لازم يكون قبل النهارده.',
        ]);

        $user = DB::transaction(function () use ($data, $request) {
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'password' => $data['password'], // hashed by the model cast
                'role' => $data['role'],
            ]);

            if ($user->isPatient()) {
                Patient::create([
                    'user_id' => $user->id,
                    'dob' => $data['dob'],
                    'gender' => $data['gender'],
                    'marital_status' => $data['marital_status'],
                ]);
            } else {
                // Sensitive documents go to the private (non-public) disk.
                DoctorProfile::create([
                    'user_id' => $user->id,
                    'specialization' => $data['specialization'],
                    'dob' => $data['dob'],
                    'id_card_img' => $request->file('id_card_img')->store('doctors/id_cards'),
                    'certificate_img' => $request->file('certificate_img')->store('doctors/certificates'),
                    'phone' => $data['phone'],
                    'clinic_url' => $data['clinic_url'] ?? null,
                    'photo' => $request->file('photo')->store('doctors/photos', 'public'), // shown to patients, so public disk
                    'bio' => $data['bio'] ?? null,
                    'status' => DoctorProfile::STATUS_PENDING,
                ]);
            }

            return $user;
        });

        if ($user->isDoctor()) {
            return response()->json([
                'message' => 'تم استلام طلبك، هيتم مراجعته من الإدارة قبل التفعيل.',
                'user' => $user->load('doctorProfile'),
            ], 201);
        }

        return response()->json([
            'message' => 'تم إنشاء الحساب بنجاح.',
            'user' => $user->load('patient'),
            'token' => $user->createToken('auth')->plainTextToken,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['البريد الإلكتروني أو كلمة المرور غير صحيحة.'],
            ]);
        }

        if ($user->isDoctor()) {
            $status = optional($user->doctorProfile)->status;

            if ($status === DoctorProfile::STATUS_PENDING) {
                return response()->json(['message' => 'حسابك لسه تحت المراجعة.', 'status' => 'pending'], 403);
            }
            if ($status === DoctorProfile::STATUS_REJECTED) {
                // The app shows a "register my data again" button: POST /api/auth/doctor/resubmit
                return response()->json([
                    'message' => 'تم رفض طلب التسجيل. تقدر تعيد إرسال بياناتك للمراجعة.',
                    'status' => 'rejected',
                    'can_resubmit' => true,
                ], 403);
            }
        }

        return response()->json([
            'user' => $user->load($user->isDoctor() ? 'doctorProfile' : ($user->isPatient() ? 'patient' : [])),
            'token' => $user->createToken('auth')->plainTextToken,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load($user->isDoctor() ? 'doctorProfile' : ($user->isPatient() ? 'patient' : []));

        return response()->json(['user' => $user]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'تم تسجيل الخروج.']);
    }

    // POST /api/auth/change-password   body: { current_password, password, password_confirmation }
    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ], [
            'password.confirmed' => 'كلمة المرور الجديدة وتأكيدها لازم يكونوا متطابقين.',
            'password.different' => 'كلمة المرور الجديدة لازم تكون مختلفة عن الحالية.',
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['كلمة المرور الحالية غير صحيحة.'],
            ]);
        }

        $user->password = $data['password']; // hashed by the model cast
        $user->save();

        // Log out every other device, keep the one used for this request.
        $current = $user->currentAccessToken();
        $user->tokens()
            ->when($current instanceof PersonalAccessToken, fn ($q) => $q->where('id', '!=', $current->id))
            ->delete();

        return response()->json(['message' => 'تم تغيير كلمة المرور.']);
    }
}
