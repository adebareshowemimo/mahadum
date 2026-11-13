<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LibrarySetLevel extends Model
{
    protected $guarded = [];

    protected $casts = ['is_free' => 'boolean'];

    /** @return BelongsTo<LibrarySet, $this> */
    public function set(): BelongsTo
    {
        return $this->belongsTo(LibrarySet::class, 'library_set_id');
    }

    /** @return HasMany<Lesson, $this> */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('position');
    }
}
