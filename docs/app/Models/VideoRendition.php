<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoRendition extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'ready' => 'boolean',
    ];


    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class, 'video_id');
    }
}
