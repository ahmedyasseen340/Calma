<?php

namespace App\Http\Middleware;

use App\Models\DoctorProfile;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Usage: ->middleware('role:admin') or ->middleware('role:patient,doctor')
     *
     * A doctor is let in only while the account is ACTIVE (approved by the admin). If the admin rejects
     * a doctor, or the doctor changes the specialization and goes back to pending, the doctor is
     * blocked here even with an old token.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, $roles, true)) {
            return response()->json(['message' => 'غير مصرح لك بالوصول.'], 403);
        }

        if ($user->isDoctor()) {
            $status = optional($user->doctorProfile)->status;

            if ($status !== DoctorProfile::STATUS_ACTIVE) {
                return response()->json([
                    'message' => 'حسابك مش مفعّل. استنى موافقة الأدمن.',
                    'status' => $status,
                ], 403);
            }
        }

        return $next($request);
    }
}
