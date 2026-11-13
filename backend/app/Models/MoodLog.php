<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoodLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['patient_id', 'challenge_id', 'mood_score', 'logged_at'];

    protected function casts(): array
    {
        return ['mood_score' => 'integer', 'logged_at' => 'datetime'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }
}
