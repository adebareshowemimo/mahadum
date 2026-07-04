import { Alert } from '@/components/ui'
import { useReferralCode } from '@/lib/referral/queries'

const SUPPORT_EMAIL = 'support@mahadum360.app'

/**
 * Fraud-review notice: when the referral code is `flagged` / `frozen` (velocity
 * guard FR-7.5), rewards pause pending review. Amber, with a support CTA.
 * Renders nothing while loading or when the code is active.
 */
export function ReferralStatusAlert() {
  const { data } = useReferralCode()
  if (!data || data.status === 'active') return null

  return (
    <Alert variant="warning" title="Referral account under review">
      <p>
        Some recent referral activity was flagged, so new referral rewards are paused while we take a
        look. If you think this is a mistake, our team can help.
      </p>
      <a
        href={`mailto:${SUPPORT_EMAIL}?subject=Referral%20review`}
        className="mt-1 inline-block font-semibold underline"
      >
        Contact support
      </a>
    </Alert>
  )
}
