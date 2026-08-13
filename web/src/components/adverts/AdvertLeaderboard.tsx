import { useEffect, useRef, useState } from 'react'
import { useAdsAllowed, useActiveAdvert, useRecordClick, useRecordImpression } from '@/lib/adverts/queries'

const SCROLL_THRESHOLD = 24

/**
 * Site-wide leaderboard banner. Self-contained: reads its own entitlements +
 * advert data, so it's simply dropped into any layout shell. Fades out on
 * scroll-down past a small threshold, fades back in on scroll-up — animated
 * via opacity/max-height rather than unmounting, so the transition is smooth.
 * Never shown to staff roles (admin portal, content authoring, teaching, school ops).
 */
export function AdvertLeaderboard() {
  const adsAllowed = useAdsAllowed()
  const { data: advert } = useActiveAdvert('leaderboard')
  const [visible, setVisible] = useState(true)
  const lastScrollY = useRef(0)
  const recordImpression = useRecordImpression()
  const recordClick = useRecordClick()

  useEffect(() => {
    let ticking = false

    function onScroll() {
      if (ticking) return
      ticking = true
      requestAnimationFrame(() => {
        const y = window.scrollY
        const goingDown = y > lastScrollY.current
        if (goingDown && y > SCROLL_THRESHOLD) {
          setVisible(false)
        } else if (!goingDown || y <= SCROLL_THRESHOLD) {
          setVisible(true)
        }
        lastScrollY.current = y
        ticking = false
      })
    }

    window.addEventListener('scroll', onScroll, { passive: true })
    return () => window.removeEventListener('scroll', onScroll)
  }, [])

  useEffect(() => {
    if (advert) recordImpression.mutate(advert.id)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [advert?.id])

  if (!adsAllowed || !advert) return null

  return (
    <div
      className="w-full overflow-hidden transition-all duration-300 ease-out"
      style={{ maxHeight: visible ? 120 : 0, opacity: visible ? 1 : 0 }}
      data-testid="advert-leaderboard"
    >
      <div className="flex flex-col items-center gap-0.5 py-1.5">
        <a
          href={advert.target_url}
          target="_blank"
          rel="noopener sponsored"
          onClick={() => recordClick.mutate(advert.id)}
          className="block w-full max-w-3xl"
        >
          <img
            src={advert.image_url}
            alt="Advertisement"
            className="mx-auto max-h-[90px] w-full rounded-lg object-contain sm:max-h-[100px]"
          />
        </a>
        <span className="text-[10px] uppercase tracking-wide text-subtle">Advertisement</span>
      </div>
    </div>
  )
}
