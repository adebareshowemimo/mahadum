<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Language extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'rtl' => 'boolean',
        'is_active' => 'boolean',
    ];


    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }
}
