import { useEffect } from 'react'
import { useAdsAllowed, useActiveAdvert, useRecordClick, useRecordImpression } from '@/lib/adverts/queries'

/**
 * Site-wide leaderboard banner. Self-contained: reads its own entitlements +
 * advert data, so it's simply dropped into any layout shell. It stays in
 * normal document flow and naturally scrolls away; changing its height from a
 * scroll handler would create a feedback loop when it sits above sticky nav.
 * Never shown to staff roles (admin portal, content authoring, teaching, school ops).
 */
export function AdvertLeaderboard() {
  const adsAllowed = useAdsAllowed()
  const { data: advert } = useActiveAdvert('leaderboard')
  const recordImpression = useRecordImpression()
  const recordClick = useRecordClick()

  useEffect(() => {
    if (advert) recordImpression.mutate(advert.id)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [advert?.id])

  if (!adsAllowed || !advert) return null

  return (
    <div className="w-full overflow-hidden" data-testid="advert-leaderboard">
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
            width={970}
            height={90}
            className="mx-auto max-h-[90px] w-full rounded-lg object-contain sm:max-h-[100px]"
          />
        </a>
        <span className="text-[10px] uppercase tracking-wide text-subtle">Advertisement</span>
      </div>
    </div>
  )
}
