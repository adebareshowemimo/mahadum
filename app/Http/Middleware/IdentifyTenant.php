<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active tenant for the request and binds it to the
 * BelongsToTenant global scope via app('currentTenantId'), so tenant-scoped
 * models are automatically filtered to the active organization (Architecture §1.2).
 *
 * Roles/permissions are GLOBAL capabilities (spatie teams off); this middleware
 * provides the *scope*. Cross-tenant access is rejected here (403) and refined
 * per record by policies (sameTenant()).
 *
 * Resolution order:
 *   1. super_admin               → no tenant (global, unscoped)
 *   2. X-Organization-Id header  → validated against active memberships
 *   3. any org membership(s)     → first active membership derived automatically
 *                                  (deterministic default; the client's org switcher
 *                                  can send the header to pick a different one)
 *   4. no membership             → direct-consumer (family) context
 *
 * Register after `auth:sanctum`:
 *   ->middleware(['auth:sanctum', 'identify.tenant'])
 */
class IdentifyTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // 1) Super admin runs unscoped/global.
        if ($user && $user->hasRole('super_admin')) {
            return $next($request);
        }

        $orgId = $this->resolveOrganizationId($request, $user);

        if ($orgId !== null) {
            app()->instance('currentTenantId', $orgId);
        }

        return $next($request);
    }

    /**
     * @return int|null validated organization id, or null for direct consumers.
     */
    private function resolveOrganizationId(Request $request, $user): ?int
    {
        if (! $user) {
            return null;
        }

        $membershipIds = $user->organizations()
            ->wherePivot('status', 'active')
            ->orderBy('organizations.id')
            ->pluck('organizations.id');

        // 2) Explicit header — must be one the user actually belongs to.
        if ($request->hasHeader('X-Organization-Id')) {
            $requested = (int) $request->header('X-Organization-Id');

            abort_unless($membershipIds->contains($requested), 403, 'Cross-tenant access denied.');

            return $requested;
        }

        // 3) One or more memberships, no header → default to the first (deterministic;
        // avoids leaving multi-org staff in a null-tenant limbo where tenant-scoped
        // reads run unscoped and tenant-scoped writes fail a NOT NULL constraint).
        if ($membershipIds->isNotEmpty()) {
            return (int) $membershipIds->first();
        }

        // 4) No membership at all → direct-consumer (family) context.
        return null;
    }
}
