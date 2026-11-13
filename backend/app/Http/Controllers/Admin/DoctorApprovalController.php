<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DoctorProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    // POST /api/admin/doctors/{doctor}/approve
    public function approve(DoctorProfile $doctor): JsonResponse
    {
        $doctor->update(['status' => DoctorProfile::STATUS_ACTIVE]);

        return response()->json(['message' => 'تم تفعيل الدكتور.', 'doctor' => $doctor]);
    }

    // POST /api/admin/doctors/{doctor}/reject
    public function reject(DoctorProfile $doctor): JsonResponse
    {
        $doctor->update(['status' => DoctorProfile::STATUS_REJECTED]);

        return response()->json(['message' => 'تم رفض الطلب.', 'doctor' => $doctor]);
    }
}
