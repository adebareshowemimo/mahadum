import { useEffect, useRef, useState } from 'react'
import { useAdsAllowed, useActiveAdvert, useRecordClick, useRecordImpression } from '@/lib/adverts/queries'
import type { AdvertPosition } from '@/lib/api'
import { useAuth } from '@/lib/auth/AuthProvider'
import { useActiveProfile } from '@/lib/profile/ActiveProfile'

/**
 * Reveal-on-scroll banner slot, droppable into any page's content flow.
 * One-shot fade-in the first time it scrolls into view (does not fade back
 * out), unlike the leaderboard which fades both ways.
 * Never shown to staff roles (admin portal, content authoring, teaching, school ops).
 */
export function InlineAdvert({ position = 'inline', childProfile = false }: { position?: AdvertPosition; childProfile?: boolean }) {
  const adsAllowed = useAdsAllowed()
  const { user, hasRole } = useAuth()
  const { activeLearner } = useActiveProfile()
  const canBuyData = user?.user.capabilities?.includes('billing.databundles.manage') ?? hasRole('parent', 'super_admin')
  const childOnly = childProfile || activeLearner?.is_child === true || !canBuyData
  const { data: advert } = useActiveAdvert(position)
  const [visible, setVisible] = useState(false)
  const ref = useRef<HTMLDivElement>(null)
  const recordImpression = useRecordImpression()
  const recordClick = useRecordClick()

  useEffect(() => {
    if (!advert || !ref.current) return

    const el = ref.current
    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          setVisible(true)
          recordImpression.mutate(advert.id)
          observer.disconnect()
        }
      },
      { threshold: 0.25 },
    )
    observer.observe(el)
    return () => observer.disconnect()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [advert?.id])

  if (!adsAllowed || !advert) return null

  return (
    <div
      ref={ref}
      className={`flex flex-col items-center gap-1 rounded-2xl border border-border bg-surface p-3 transition-[transform,opacity] duration-200 ease-out ${
        visible ? 'translate-y-0 opacity-100' : 'translate-y-2 opacity-0'
      }`}
      data-testid="advert-inline"
    >
      {position === 'profile_data_topup' && childOnly ? <div className="flex flex-col items-center gap-2 text-center"><img src={advert.image_url} alt="Top up mobile data" className="mx-auto max-h-64 w-full rounded-xl object-contain" /><p className="text-sm text-muted">Ask your grown-up to buy a mobile data bundle so you can keep learning online.</p></div> : <a
        href={position === 'profile_data_topup' ? '/billing/data' : advert.target_url}
        target="_blank"
        rel="noopener sponsored"
        onClick={() => recordClick.mutate(advert.id)}
        className="block w-full"
      >
        <img src={advert.image_url} alt={position === 'profile_data_topup' ? 'Buy data / top-up' : 'Advertisement'} className="mx-auto max-h-64 w-full rounded-xl object-contain" />
      </a>}
      {position === 'profile_data_topup' && !childOnly && <p className="text-center text-xs text-muted">Browse mobile data plans. Availability is shown in the store.</p>}
      <span className="text-[10px] uppercase tracking-wide text-subtle">Advertisement</span>
    </div>
  )
}
