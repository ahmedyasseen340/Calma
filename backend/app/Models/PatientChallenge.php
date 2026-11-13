<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class PatientChallenge extends Pivot
{
    protected $table = 'patient_challenges';

    public $incrementing = true;
    public $timestamps = false;

    protected $fillable = ['patient_id', 'challenge_id', 'completed_at'];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }
}
