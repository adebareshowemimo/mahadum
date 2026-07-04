<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelcoSubscription extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'grace_until' => 'datetime',
        'next_attempt_at' => 'datetime',
    ];


    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(TelcoBillingAttempt::class);
    }
}
