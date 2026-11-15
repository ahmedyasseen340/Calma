<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * MySQL treats "A@x.com" and "a@x.com" as the same email, PostgreSQL does not.
 * To behave the same on both (and to stop two accounts for one person), every email
 * that arrives is trimmed and converted to lower case before anything else uses it.
 */
class NormalizeEmail
{
    public function handle(Request $request, Closure $next): Response
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => mb_strtolower(trim($request->input('email')))]);
        }

        return $next($request);
    }
}
