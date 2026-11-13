<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Models\Audio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AudioController extends Controller
{
    private const FILE_RULES = ['file', 'mimetypes:audio/mpeg,audio/wav,audio/x-wav,audio/mp4,audio/x-m4a,audio/aac,audio/ogg', 'max:20480']; // 20 MB

    // GET /api/audios  (any logged-in user)
    public function index(): JsonResponse
    {
        return response()->json(Audio::latest('id')->paginate(20));
    }

    // GET /api/audios/{audio}
    public function show(Audio $audio): JsonResponse
    {
        return response()->json(['audio' => $audio]);
    }

    // POST /api/admin/audios   (multipart/form-data: title, file)
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'file' => array_merge(['required'], self::FILE_RULES),
        ]);

        $audio = Audio::create([
            'title' => $data['title'],
            'file_path' => $request->file('file')->store('audios', 'public'),
        ]);

        return response()->json(['audio' => $audio], 201);
    }

    // POST /api/admin/audios/{audio}   (send only what you want to change)
    public function update(Request $request, Audio $audio): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'file' => array_merge(['sometimes'], self::FILE_RULES),
        ]);

        if (isset($data['title'])) {
            $audio->title = $data['title'];
        }

        if ($request->hasFile('file')) {
            Storage::disk('public')->delete($audio->file_path);
            $audio->file_path = $request->file('file')->store('audios', 'public');
        }

        $audio->save();

        return response()->json(['audio' => $audio]);
    }

    // DELETE /api/admin/audios/{audio}
    public function destroy(Audio $audio): JsonResponse
    {
        Storage::disk('public')->delete($audio->file_path);
        $audio->delete();

        return response()->json(['message' => 'تم الحذف.']);
    }
}
