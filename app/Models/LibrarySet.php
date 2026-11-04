<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LibrarySet extends Model
{
    protected $guarded = [];

    /** @return BelongsTo<Language, $this> */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    /** @return HasMany<LibrarySetLevel, $this> */
    public function levels(): HasMany
    {
        return $this->hasMany(LibrarySetLevel::class)->orderBy('position');
    }
}
