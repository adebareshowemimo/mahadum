<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Commission extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'escrow_until' => 'datetime',
        'cleared_at' => 'datetime',
    ];


    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class, 'referral_id');
    }

    public function beneficiary(): MorphTo
    {
        return $this->morphTo();
    }
}
