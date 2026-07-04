<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $key
 * @property string $subject
 * @property string|null $greeting
 * @property string $body
 * @property string|null $action_text
 * @property string|null $action_url
 * @property int|null $updated_by
 *
 * @mixin \Eloquent
 */
class EmailTemplateOverride extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
