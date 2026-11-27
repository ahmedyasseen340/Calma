<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DoctorProfile;
use App\Services\AccountEraser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DoctorApprovalController extends Controller
{
    // GET /api/admin/doctors?status=pending
    public function index(Request $request): JsonResponse
    {
        $request->validate(['status' => ['nullable', 'in:pending,active,rejected']]);

        $doctors = DoctorProfile::with('user:id,first_name,last_name,email')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest('id')
            ->paginate(15);

        return response()->json($doctors);
    }

    // GET /api/admin/doctors/{doctor} : the doctor's file, with links to the ID card and the certificate
    public function show(DoctorProfile $doctor): JsonResponse
    {
        $doctor->load('user:id,first_name,last_name,email');

        return response()->json([
            'doctor' => $doctor,
            'documents' => [
                'id_card' => url("/api/admin/doctors/{$doctor->id}/documents/id_card"),
                'certificate' => url("/api/admin/doctors/{$doctor->id}/documents/certificate"),
            ],
        ]);
    }

    // GET /api/admin/doctors/{doctor}/documents/{type}   (type: id_card | certificate) : private files, admin only
    public function document(DoctorProfile $doctor, string $type): StreamedResponse
    {
        abort_unless(in_array($type, ['id_card', 'certificate'], true), 404);

        $path = $type === 'id_card' ? $doctor->id_card_img : $doctor->certificate_img;
        abort_unless($path && Storage::exists($path), 404);

        return Storage::response($path);
    }

    // POST or PATCH /api/admin/doctors/{doctor}/approve
    public function approve(DoctorProfile $doctor): JsonResponse
    {
        $doctor->update(['status' => DoctorProfile::STATUS_ACTIVE]);

        return response()->json(['message' => 'تم تفعيل الدكتور.', 'doctor' => $doctor]);
    }

    // POST or PATCH /api/admin/doctors/{doctor}/reject
    public function reject(DoctorProfile $doctor): JsonResponse
    {
        $doctor->update(['status' => DoctorProfile::STATUS_REJECTED]);
        $doctor->user->tokens()->delete();   // a doctor who is logged in is logged out at once

        return response()->json(['message' => 'تم رفض الطلب.', 'doctor' => $doctor]);
    }

    // DELETE /api/admin/doctors/{doctor} : removes the doctor's account and all its data
    public function destroy(DoctorProfile $doctor, AccountEraser $eraser): JsonResponse
    {
        $eraser->erase($doctor->user);

        return response()->json(['message' => 'تم مسح الدكتور وكل بياناته.']);
    }
}
