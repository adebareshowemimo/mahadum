<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChoreSubmission extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'submitted_at' => 'datetime',
        'decided_at' => 'datetime',
    ];


    public function chore(): BelongsTo
    {
        return $this->belongsTo(Chore::class, 'chore_id');
    }

    public function evidenceMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'evidence_media_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
