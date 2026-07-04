<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $learner_profile_id
 * @property string $verb
 * @property string|null $object_iri
 * @property array<array-key, mixed>|null $raw
 * @property Carbon|null $lrs_synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read LearnerProfile|null $learnerProfile
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|XapiStatement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|XapiStatement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|XapiStatement query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|XapiStatement whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|XapiStatement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|XapiStatement whereLearnerProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|XapiStatement whereLrsSyncedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|XapiStatement whereObjectIri($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|XapiStatement whereRaw($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|XapiStatement whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|XapiStatement whereVerb($value)
 *
 * @mixin \Eloquent
 */
class XapiStatement extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'raw' => 'array',
        'lrs_synced_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<LearnerProfile, $this>
     */
    public function learnerProfile(): BelongsTo
    {
        return $this->belongsTo(LearnerProfile::class, 'learner_profile_id');
    }
}
