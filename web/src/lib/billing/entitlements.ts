import type { EntitlementFeature, Entitlements } from '@/lib/api'
import { useAuth } from '@/lib/auth/AuthProvider'

/** Free-tier defaults, used until /me resolves (matches the backend). */
export const FREE_ENTITLEMENTS: Entitlements = {
  tier: 'free',
  tier_name: 'Free',
  ads: true,
  offline_download: false,
  unlimited_hearts: false,
  family_dashboard: false,
  teacher_analytics: false,
  max_profiles: 1,
}

/** Human labels + the plan that unlocks each gated feature (for paywall copy). */
export const FEATURE_META: Record<EntitlementFeature, { label: string; unlockedBy: string }> = {
  family_dashboard: { label: 'Family dashboard, chores & monitoring', unlockedBy: 'Premium (Family)' },
  offline_download: { label: 'Offline lesson downloads', unlockedBy: 'Premium' },
  unlimited_hearts: { label: 'Unlimited hearts', unlockedBy: 'Premium' },
  teacher_analytics: { label: 'Teacher analytics', unlockedBy: 'School' },
}

export function useEntitlements(): Entitlements {
  const { user } = useAuth()
  return user?.entitlements ?? FREE_ENTITLEMENTS
}

/** Whether the active plan grants a given premium feature. */
export function useHasFeature(feature: EntitlementFeature): boolean {
  return !!useEntitlements()[feature]
}
