import { useEffect, useRef, useState } from 'react'
import { useAdsAllowed, useActiveAdvert, useRecordClick, useRecordImpression } from '@/lib/adverts/queries'
import type { AdvertPosition } from '@/lib/api'

/**
 * Reveal-on-scroll banner slot, droppable into any page's content flow.
 * One-shot fade-in the first time it scrolls into view (does not fade back
 * out), unlike the leaderboard which fades both ways.
 * Never shown to staff roles (admin portal, content authoring, teaching, school ops).
 */
export function InlineAdvert({ position = 'inline' }: { position?: AdvertPosition }) {
  const adsAllowed = useAdsAllowed()
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
      className={`flex flex-col items-center gap-1 rounded-2xl border border-border bg-surface p-3 transition-all duration-500 ease-out ${
        visible ? 'translate-y-0 opacity-100' : 'translate-y-2 opacity-0'
      }`}
      data-testid="advert-inline"
    >
      <a
        href={advert.target_url}
        target="_blank"
        rel="noopener sponsored"
        onClick={() => recordClick.mutate(advert.id)}
        className="block w-full"
      >
        <img src={advert.image_url} alt="Advertisement" className="mx-auto max-h-64 w-full rounded-xl object-contain" />
      </a>
      <span className="text-[10px] uppercase tracking-wide text-subtle">Advertisement</span>
    </div>
  )
}
