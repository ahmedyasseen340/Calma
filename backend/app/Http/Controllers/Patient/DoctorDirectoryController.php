<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\DoctorProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The patient sees ACTIVE (approved) doctors only, with what is needed to contact them
 * (phone number and clinic link). Date of birth, status and documents are never shown.
 */
class DoctorDirectoryController extends Controller
{
    // GET /api/doctors?specialization=psych
    public function index(Request $request): JsonResponse
    {
        $request->validate(['specialization' => ['nullable', 'string', 'max:255']]);

        $doctors = DoctorProfile::where('status', DoctorProfile::STATUS_ACTIVE)
            ->with('user:id,first_name,last_name')
            ->when($request->specialization, function ($q, $s) {
                $q->whereRaw('LOWER(specialization) LIKE ?', ['%'.mb_strtolower($s).'%']);
            })
            ->latest('id')
            ->paginate(15)
            ->through(fn (DoctorProfile $d) => $this->present($d));

        return response()->json($doctors);
    }

    // GET /api/doctors/{doctor}
    public function show(DoctorProfile $doctor): JsonResponse
    {
        abort_unless($doctor->status === DoctorProfile::STATUS_ACTIVE, 404);

        return response()->json(['doctor' => $this->present($doctor->load('user:id,first_name,last_name'))]);
    }

    private function present(DoctorProfile $d): array
    {
        return [
            'id' => $d->id,
            'first_name' => $d->user->first_name,
            'last_name' => $d->user->last_name,
            'specialization' => $d->specialization,
            'phone' => $d->phone,
            'clinic_url' => $d->clinic_url,
            'bio' => $d->bio,
            'photo_url' => $d->photo_url,
        ];
    }
}
