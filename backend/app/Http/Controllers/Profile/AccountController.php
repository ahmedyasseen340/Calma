<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Services\AccountEraser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * A patient or a doctor deletes their own account, for good (see AccountEraser).
 */
class AccountController extends Controller
{
    // POST /api/auth/delete-account   body: { current_password }
    public function destroy(Request $request, AccountEraser $eraser): JsonResponse
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

        $eraser->erase($user);

        return response()->json(['message' => 'تم مسح حسابك وكل بياناتك نهائياً.']);
    }
}
