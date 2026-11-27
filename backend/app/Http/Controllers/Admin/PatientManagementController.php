<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Services\AccountEraser;
use Illuminate\Http\JsonResponse;

class PatientManagementController extends Controller
{
    // GET /api/admin/patients
    public function index(): JsonResponse
    {
        return response()->json(
            Patient::with('user:id,first_name,last_name,email')->latest('id')->paginate(15)
        );
    }

    // DELETE /api/admin/patients/{patient} : removes the patient's account and all its data
    public function destroy(Patient $patient, AccountEraser $eraser): JsonResponse
    {
        $eraser->erase($patient->user);

        return response()->json(['message' => 'تم مسح المريض وكل بياناته.']);
    }
}
