export interface PresentablePlan {
  audience?: string | null
  max_profiles: number | null
  features?: Record<string, string | number | boolean> | null
}

/** One benefit-copy source shared by public pricing and signed-in billing. */
export function planFeatures(plan: PresentablePlan): string[] {
  const features = plan.features ?? {}
  const out: string[] = []

  if (plan.audience === 'family') out.push('All Individual plan benefits')
  else if (plan.audience === 'individual') out.push('All Free plan benefits')

  out.push(plan.max_profiles == null
    ? 'Unlimited profiles (per seat)'
    : `Up to ${plan.max_profiles} profile${plan.max_profiles === 1 ? '' : 's'}`)
  out.push(features.ads === false ? 'Ad-free learning' : 'Supported by age-appropriate ads')
  if (features.offline_download) out.push('Offline lesson downloads')
  if (features.unlimited_hearts) out.push('Unlimited hearts')
  if (features.family_dashboard) out.push('Family progress dashboard, chores and approvals')
  if (features.teacher_analytics) out.push('Teacher analytics')
  if (features.seats) out.push('Classroom seats')

  return out
}

export const FREE_PLAN_FEATURES = [
  'Every lesson and quiz',
  'Speaking practice',
  'XP, streaks and badges',
  'Supported by age-appropriate ads',
] as const
