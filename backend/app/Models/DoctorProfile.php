<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DoctorProfile extends Model
{
    public $timestamps = false;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_REJECTED = 'rejected';

    public const BIO_MAX_LENGTH = 300;

    protected $fillable = [
        'user_id', 'specialization', 'dob', 'id_card_img', 'certificate_img',
        'phone', 'clinic_url', 'photo', 'bio', 'status',
    ];

    // Storage paths are not exposed: clients use photo_url. The ID card and certificate stay private.
    protected $hidden = ['id_card_img', 'certificate_img', 'photo'];

    protected $appends = ['photo_url'];

    protected function casts(): array
    {
        return ['dob' => 'date'];
    }

    protected function photoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->photo ? Storage::disk('public')->url($this->photo) : null);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
