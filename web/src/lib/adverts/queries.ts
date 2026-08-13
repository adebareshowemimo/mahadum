import { useMutation, useQuery } from '@tanstack/react-query'
import { advertsApi, type AdvertPosition } from '@/lib/api'
import { useAuth } from '@/lib/auth/AuthProvider'
import { useEntitlements } from '@/lib/billing/entitlements'

/** Staff/operational roles run the admin portal and internal tooling — never advertising targets. */
const STAFF_ROLES = ['super_admin', 'content_owner', 'teacher', 'school_admin'] as const

/** Whether banner adverts may show at all: free-tier entitlements, and never for staff roles. */
export function useAdsAllowed(): boolean {
  const { hasRole } = useAuth()
  const entitlements = useEntitlements()
  return entitlements.ads && !hasRole(...STAFF_ROLES)
}

/** Publicly-served active advert for a position. Works logged-out (no `enabled: !!user` gate). */
export function useActiveAdvert(position: AdvertPosition) {
  return useQuery({
    queryKey: ['advert-active', position],
    queryFn: () => advertsApi.active(position),
    staleTime: 5 * 60 * 1000,
    refetchOnWindowFocus: false,
  })
}

/** Fire-and-forget impression/click beacons — failures are non-fatal, no UI depends on them. */
export function useRecordImpression() {
  return useMutation({ mutationFn: (id: number) => advertsApi.impression(id) })
}

export function useRecordClick() {
  return useMutation({ mutationFn: (id: number) => advertsApi.click(id) })
}
