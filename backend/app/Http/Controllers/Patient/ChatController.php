<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Services\ChatbotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    // GET /api/patient/chat : chat history (newest first, paginated)
    public function index(Request $request): JsonResponse
    {
        $patient = $request->user()->patient;
        abort_unless($patient, 403);

        return response()->json($patient->chatMessages()->latest('id')->paginate(30));
    }

    // POST /api/patient/chat : send a message and get the bot's reply
    public function send(Request $request, ChatbotService $bot): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $patient = $request->user()->patient;
        abort_unless($patient, 403);

        $history = $patient->chatMessages()
            ->latest('id')
            ->limit(config('chatbot.history_limit'))
            ->get()
            ->reverse()
            ->values();

        $result = $bot->reply($data['message'], $history);

        $chat = $patient->chatMessages()->create([
            'message' => $data['message'],
            'reply' => $result['reply'],
            'is_danger' => $result['is_danger'],
        ]);

        return response()->json(['chat' => $chat, 'ai_available' => $result['ai_available']], 201);
    }

    // POST /api/patient/chat/voice   (multipart/form-data: audio)
    public function sendVoice(Request $request, ChatbotService $bot): JsonResponse
    {
        $request->validate([
            'audio' => ['required', 'file', 'extensions:mp3,wav,m4a,aac,ogg,webm,3gp,amr', 'max:10240'], // 10 MB
        ]);

        $patient = $request->user()->patient;
        abort_unless($patient, 403);

        $history = $patient->chatMessages()
            ->latest('id')
            ->limit(config('chatbot.history_limit'))
            ->get()
            ->reverse()
            ->values();

        $audio = $request->file('audio');
        $result = $bot->replyToVoice($audio, $history);

        // Recordings are private health data: stored on the private disk, never publicly reachable.
        $chat = $patient->chatMessages()->create([
            'message' => $result['transcript'] ?? '🎤 رسالة صوتية',
            'reply' => $result['reply'],
            'is_danger' => $result['is_danger'],
            'audio_path' => $audio->store('chat-audio'),
            'emotion' => $result['emotion'],
            'emotion_score' => $result['emotion_score'],
        ]);

        return response()->json(['chat' => $chat, 'ai_available' => $result['ai_available']], 201);
    }

    // GET /api/patient/chat/{message}/audio : the patient plays back their own recording
    public function audio(Request $request, ChatMessage $message): StreamedResponse
    {
        $patient = $request->user()->patient;

        abort_unless($patient && $message->patient_id === $patient->id, 404);
        abort_unless($message->audio_path && Storage::exists($message->audio_path), 404);

        return Storage::response($message->audio_path);
    }
}
