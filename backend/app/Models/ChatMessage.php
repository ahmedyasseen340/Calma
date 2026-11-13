<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    // The ERD has only created_at on this table.
    public const UPDATED_AT = null;

    /** Emotions the voice analysis can return (anything else is stored as null). */
    public const EMOTIONS = ['happy', 'sad', 'angry', 'anxious', 'neutral'];

    protected $fillable = [
        'patient_id', 'message', 'reply', 'is_danger',
        'audio_path', 'emotion', 'emotion_score',
    ];

    // The storage path is private: clients use has_audio + GET /api/patient/chat/{id}/audio.
    protected $hidden = ['audio_path'];

    protected $appends = ['has_audio'];

    protected function casts(): array
    {
        return [
            'is_danger' => 'boolean',
            'emotion_score' => 'float',
            'created_at' => 'datetime',
        ];
    }

    public function getHasAudioAttribute(): bool
    {
        return $this->audio_path !== null;
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
