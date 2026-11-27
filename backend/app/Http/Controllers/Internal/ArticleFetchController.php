<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class ArticleFetchController extends Controller
{
    // POST /api/internal/articles/fetch   header: X-Cron-Token
    public function __invoke(Request $request): JsonResponse
    {
        $token = config('articles.cron_token');

        // wrong or missing secret: pretend the address does not exist
        abort_unless($token && hash_equals((string) $token, (string) $request->header('X-Cron-Token')), 404);

        Artisan::call('articles:fetch');

        return response()->json(['output' => trim(Artisan::output())]);
    }
}
