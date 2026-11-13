<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    use HasFactory;

    /** A patient must be older than this many years at registration. */
    public const MIN_AGE = 16;

    public const GENDERS = ['male', 'female'];
    public const MARITAL_STATUSES = ['single', 'married'];

    public $timestamps = false;

    protected $fillable = ['user_id', 'dob', 'gender', 'marital_status'];

    /** `age` is calculated from dob, so it never goes out of date. */
    protected $appends = ['age'];

    protected function casts(): array
    {
        return ['dob' => 'date'];
    }

    public function getAgeAttribute(): ?int
    {
        return $this->dob?->age;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function moodLogs(): HasMany
    {
        return $this->hasMany(MoodLog::class);
    }

    public function challenges(): BelongsToMany
    {
        return $this->belongsToMany(Challenge::class, 'patient_challenges')
            ->using(PatientChallenge::class)
            ->withPivot('id', 'completed_at');
    }
}
