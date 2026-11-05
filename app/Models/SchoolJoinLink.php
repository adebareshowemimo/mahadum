<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolJoinLink extends Model
{
    protected $guarded = [];

    protected $casts = ['token_ciphertext' => 'encrypted', 'expires_at' => 'datetime', 'revoked_at' => 'datetime'];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
