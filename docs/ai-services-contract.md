# Contract between the Laravel backend and `ai-services/`

The backend does not analyze text or sound itself. It calls the Python service below and saves the answer.
Base URL = `CHATBOT_URL` in the backend `.env`. If it is empty the backend answers with a built-in stub, so the apps can be developed before the AI is ready.

**Authentication:** `Authorization: Bearer <CHATBOT_API_KEY>` (if a key is configured).
**Timeouts:** 20 seconds for `/chat`, 60 seconds for `/voice`. The backend retries once.
**If the AI fails** (timeout, 5xx, bad JSON): the patient's message is still saved, the patient gets a fallback reply, and the API answers `ai_available: false`.

## 1) `POST /chat`: text message
Request (JSON)
```json
{
  "message": "أنا حاسس بقلق النهارده",
  "history": [
    { "role": "user", "content": "..." },
    { "role": "assistant", "content": "..." }
  ]
}
```
`history` = the last 10 messages of this patient, oldest first.

Response (JSON)
```json
{ "reply": "أنا معاك، إيه اللي مقلقك؟", "is_danger": false }
```

## 2) `POST /voice`: voice message (speech-to-text + tone of voice)
Request (`multipart/form-data`)
| Field | Type | Notes |
|---|---|---|
| `audio` | file | the recording (mp3, wav, m4a, aac, ogg, webm, 3gp, amr), up to 10 MB |
| `history` | text | the same `history` array as above, as a JSON string |

Response (JSON)
```json
{
  "transcript": "أنا حاسس إني تعبان قوي",
  "reply": "أنا معاك، احكيلي أكتر",
  "is_danger": false,
  "emotion": "sad",
  "emotion_score": 0.87
}
```
- `transcript`: what the patient said (Modern Standard + Egyptian Arabic). The backend stores it as the message text.
- `emotion`: exactly one of `happy`, `sad`, `angry`, `anxious`, `neutral`. Any other value is stored as `null`.
- `emotion_score`: the model's confidence from 0 to 1 (values outside the range are clamped).
- `is_danger`: decided from the transcript (and optionally the tone) by the crisis classifier.

## `is_danger`
This flag is the only crisis signal the backend receives, and it is saved in `chat_messages.is_danger`.
The backend does not run its own keyword check, so the classifier's recall matters: agree on its test cases with the psychology team.

## Notes for the AI team
- Tone-of-voice analysis is probabilistic. Evaluate it on Egyptian Arabic recordings and report its accuracy in the project documentation.
- Recordings are private health data. Do not log or keep them after the response.
