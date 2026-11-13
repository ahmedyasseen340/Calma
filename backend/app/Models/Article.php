<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class Article extends Model
{
    public $timestamps = false;

    protected $fillable = ['title', 'summary', 'url', 'cover'];

    protected $appends = ['cover_url'];

    protected function coverUrl(): Attribute
    {
        return Attribute::get(fn () => $this->cover ? Storage::disk('public')->url($this->cover) : null);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'article_category');
    }
}
