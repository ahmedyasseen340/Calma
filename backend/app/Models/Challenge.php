<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Challenge extends Model
{
    public $timestamps = false;

    protected $fillable = ['text', 'type'];

    public function patients(): BelongsToMany
    {
        return $this->belongsToMany(Patient::class, 'patient_challenges')
            ->using(PatientChallenge::class)
            ->withPivot('id', 'completed_at');
    }

    public function moodLogs(): HasMany
    {
        return $this->hasMany(MoodLog::class);
    }
}
