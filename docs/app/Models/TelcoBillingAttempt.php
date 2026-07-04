<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelcoBillingAttempt extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'attempted_at' => 'datetime',
    ];


    public function telcoSubscription(): BelongsTo
    {
        return $this->belongsTo(TelcoSubscription::class, 'telco_subscription_id');
    }
}
