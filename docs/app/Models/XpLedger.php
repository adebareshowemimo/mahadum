<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class XpLedger extends Model
{
    use HasFactory;

    // Table is singular `xp_ledger` (an append-only ledger), not the
    // auto-pluralized `xp_ledgers`.
    protected $table = 'xp_ledger';

    protected $guarded = [];


    public function learnerProfile(): BelongsTo
    {
        return $this->belongsTo(LearnerProfile::class, 'learner_profile_id');
    }
}
