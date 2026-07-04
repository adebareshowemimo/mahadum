<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ReferralCode extends Model
{
    use HasFactory;

    protected $guarded = [];


    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class);
    }
}
