<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Audio;
use App\Models\DoctorProfile;
use App\Models\Patient;
use App\Models\Post;
use Illuminate\Http\JsonResponse;

class StatsController extends Controller
{
    // GET /api/admin/stats : numbers for the admin dashboard
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'patients' => Patient::count(),
            'doctors' => [
                'active' => DoctorProfile::where('status', DoctorProfile::STATUS_ACTIVE)->count(),
                'pending' => DoctorProfile::where('status', DoctorProfile::STATUS_PENDING)->count(),
                'rejected' => DoctorProfile::where('status', DoctorProfile::STATUS_REJECTED)->count(),
            ],
            'posts' => Post::count(),
            'articles' => Article::count(),
            'audios' => Audio::count(),
        ]);
    }
}
