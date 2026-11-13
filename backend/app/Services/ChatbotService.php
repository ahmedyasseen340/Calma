<?php

namespace App\Services;

use App\Models\ChatMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Talks to the AI team's service.
 *
 * Expected contract (agree on it with the AI team):
 *   POST {CHATBOT_URL}/chat
 *   body:     { "message": "...", "history": [ {"role":"user|assistant","content":"..."} ] }
 *   response: { "reply": "...", "is_danger": false }
 *
 *   POST {CHATBOT_URL}/voice   (multipart/form-data)
 *   fields:   audio = the recording, history = JSON string of the same history array
 *   response: { "transcript": "...", "reply": "...", "is_danger": false,
 *               "emotion": "happy|sad|angry|anxious|neutral", "emotion_score": 0.82 }
 */
class ChatbotService
{
    /**
     * @param  Collection  $history  previous ChatMessage models, oldest first
     * @return array{reply:string,is_danger:bool,ai_available:bool}
     */
    public function reply(string $message, Collection $history): array
    {
        $url = config('chatbot.url');

        if (! $url) {
            return ['reply' => config('chatbot.stub_reply'), 'is_danger' => false, 'ai_available' => true];
        }

        try {
            $response = Http::timeout(config('chatbot.timeout'))
                ->acceptJson()
                ->withToken((string) config('chatbot.api_key'))
                ->retry(1, 300)
                ->post(rtrim($url, '/').'/chat', [
                    'message' => $message,
                    'history' => $this->formatHistory($history),
                ]);

            $data = $response->json();
            $reply = is_array($data) ? ($data['reply'] ?? null) : null;

            if (is_string($reply) && trim($reply) !== '') {
                return [
                    'reply' => $reply,
                    'is_danger' => (bool) ($data['is_danger'] ?? false),
                    'ai_available' => true,
                ];
            }
        } catch (Throwable $e) {
            Log::warning('Chatbot request failed: '.$e->getMessage());
        }

        return ['reply' => config('chatbot.fallback_reply'), 'is_danger' => false, 'ai_available' => false];
    }

    /**
     * Voice message: the AI service transcribes the speech, detects the emotion in the tone of voice
     * and answers.
     *
     * @param  Collection  $history  previous ChatMessage models, oldest first
     * @return array{transcript:?string,reply:string,is_danger:bool,emotion:?string,emotion_score:?float,ai_available:bool}
     */
    public function replyToVoice(UploadedFile $audio, Collection $history): array
    {
        $url = config('chatbot.url');

        if (! $url) {
            return [
                'transcript' => null, 'reply' => config('chatbot.stub_voice_reply'), 'is_danger' => false,
                'emotion' => 'neutral', 'emotion_score' => 0.5, 'ai_available' => true, // fixed stub values for development
            ];
        }

        try {
            $response = Http::timeout(config('chatbot.voice_timeout'))
                ->acceptJson()
                ->withToken((string) config('chatbot.api_key'))
                ->retry(1, 300)
                ->attach('audio', file_get_contents($audio->getRealPath()), $audio->getClientOriginalName())
                ->post(rtrim($url, '/').'/voice', [
                    'history' => json_encode($this->formatHistory($history), JSON_UNESCAPED_UNICODE),
                ]);

            $data = $response->json();
            $reply = is_array($data) ? ($data['reply'] ?? null) : null;

            if (is_string($reply) && trim($reply) !== '') {
                $emotion = strtolower((string) ($data['emotion'] ?? ''));
                $score = $data['emotion_score'] ?? null;
                $transcript = $data['transcript'] ?? null;

                return [
                    'transcript' => is_string($transcript) && trim($transcript) !== '' ? $transcript : null,
                    'reply' => $reply,
                    'is_danger' => (bool) ($data['is_danger'] ?? false),
                    'emotion' => in_array($emotion, ChatMessage::EMOTIONS, true) ? $emotion : null,
                    'emotion_score' => is_numeric($score) ? max(0.0, min(1.0, (float) $score)) : null,
                    'ai_available' => true,
                ];
            }
        } catch (Throwable $e) {
            Log::warning('Chatbot voice request failed: '.$e->getMessage());
        }

        return [
            'transcript' => null, 'reply' => config('chatbot.fallback_reply'), 'is_danger' => false,
            'emotion' => null, 'emotion_score' => null, 'ai_available' => false,
        ];
    }

    private function formatHistory(Collection $history): array
    {
        $out = [];

        foreach ($history as $item) {
            $out[] = ['role' => 'user', 'content' => $item->message];
            if ($item->reply) {
                $out[] = ['role' => 'assistant', 'content' => $item->reply];
            }
        }

        return $out;
    }
}
