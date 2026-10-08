import { useLocation } from 'react-router-dom'
import { InlineAdvert } from './InlineAdvert'

/**
 * Consumer surfaces where an inline advert is useful without interrupting a
 * lesson, payment, review, or support task. Premium and staff suppression is
 * still enforced by InlineAdvert/useAdsAllowed.
 */
const CONSUMER_ADVERT_ROUTES = [
  /^\/home$/,
  /^\/learn(?:\/courses)?$/,
  /^\/family(?:\/children\/\d+)?$/,
  /^\/achievements$/,
  /^\/leaderboard$/,
  /^\/competitions(?:\/\d+)?$/,
  /^\/referrals$/,
]

export function isConsumerAdvertRoute(pathname: string): boolean {
  return CONSUMER_ADVERT_ROUTES.some((pattern) => pattern.test(pathname))
}

export function ConsumerPageAdvert() {
  const { pathname } = useLocation()

  if (!isConsumerAdvertRoute(pathname)) return null

  const position = 'profile_data_topup'

  return (
    <aside className="mt-8" aria-label="Sponsored content">
      <InlineAdvert key={pathname} position={position} childProfile={/^\/family\/children\/\d+$/.test(pathname)} />
    </aside>
  )
}
