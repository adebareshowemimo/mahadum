<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Scope;

/**
 * Row-level tenancy. Any model using this trait is automatically
 * scoped to the current organization (tenant) and has organization_id
 * auto-filled on create.
 *
 * Resolve the current tenant id however you wire it (stancl/tenancy
 * tenant(), a request-scoped container binding, or auth()->user()).
 * Super-admin requests should bypass the scope (see withoutTenancy()).
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model) {
            if (! $model->getAttribute('organization_id') && ($id = static::currentTenantId())) {
                $model->setAttribute('organization_id', $id);
            }
        });
    }

    public static function currentTenantId(): ?int
    {
        // Wire this to stancl/tenancy: optional(tenant())->id
        // or to the authenticated user's active organization.
        return app()->bound('currentTenantId') ? app('currentTenantId') : null;
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** Query helper to bypass tenancy (e.g. super admin). */
    public function scopeWithoutTenancy(Builder $query): Builder
    {
        return $query->withoutGlobalScope(TenantScope::class);
    }
}

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if ($id = BelongsToTenant::currentTenantId()) {
            $builder->where($model->getTable().'.organization_id', $id);
        }
    }
}
