<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherInvitation extends Model
{
    protected $guarded = [];

    protected $hidden = ['token_hash'];

    protected $casts = ['expires_at' => 'datetime', 'revoked_at' => 'datetime', 'accepted_at' => 'datetime'];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
