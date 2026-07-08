import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { Alert, Button, Modal } from '@/components/ui'
import type { AdPlacement } from '@/lib/api'
import { useCompleteAd, useRequestAd } from '@/lib/gamification/queries'

const AD_SECONDS = 5

type Phase = 'requesting' | 'ineligible' | 'playing' | 'rewarding' | 'done'

/**
 * Placeholder rewarded/interstitial ad screen (Rule 10: never interrupts an
 * active lesson — only opened between lesson nodes or from the hearts card).
 * Requests + verifies the impression server-side via /ads/*, so the reward
 * this unlocks (currently a hearts refill) can't be claimed without an ad
 * actually "playing". No ad-network vendor is wired yet (NullAdGateway
 * always fills), so this is the on-device shell the real SDK will render
 * into once one is chosen.
 */
export function AdModal({
  open,
  learnerId,
  placement,
  onClose,
  onRewarded,
}: {
  open: boolean
  learnerId: number
  placement: AdPlacement
  onClose: () => void
  onRewarded: (impressionId: number) => void
}) {
  const requestAd = useRequestAd(learnerId)
  const completeAd = useCompleteAd()
  const [phase, setPhase] = useState<Phase>('requesting')
  const [secondsLeft, setSecondsLeft] = useState(AD_SECONDS)
  const [reason, setReason] = useState<'coppa' | 'unavailable' | null>(null)
  const [impressionId, setImpressionId] = useState<number | null>(null)

  useEffect(() => {
    if (!open) return
    setPhase('requesting')
    setSecondsLeft(AD_SECONDS)
    setReason(null)
    setImpressionId(null)
    requestAd.mutate(placement, {
      onSuccess: (res) => {
        if (res.eligible && res.impression_id) {
          setImpressionId(res.impression_id)
          setPhase('playing')
        } else {
          setReason(res.reason ?? 'unavailable')
          setPhase('ineligible')
        }
      },
      onError: () => {
        setReason('unavailable')
        setPhase('ineligible')
      },
    })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, placement])

  useEffect(() => {
    if (phase !== 'playing' || secondsLeft <= 0) return
    const timer = setTimeout(() => setSecondsLeft((s) => s - 1), 1000)
    return () => clearTimeout(timer)
  }, [phase, secondsLeft])

  useEffect(() => {
    if (phase !== 'done') return
    const timer = setTimeout(onClose, 1200)
    return () => clearTimeout(timer)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [phase])

  function finishAd() {
    if (!impressionId) return
    setPhase('rewarding')
    completeAd.mutate(impressionId, {
      onSuccess: (res) => {
        if (res.shown) {
          setPhase('done')
          onRewarded(impressionId)
        } else {
          setReason('unavailable')
          setPhase('ineligible')
        }
      },
      onError: () => {
        setReason('unavailable')
        setPhase('ineligible')
      },
    })
  }

  return (
    <Modal open={open} onClose={onClose} title="Watch an ad">
      {phase === 'requesting' && <p className="py-8 text-center text-sm text-muted">Finding an ad…</p>}

      {phase === 'ineligible' && (
        <div className="flex flex-col gap-4">
          <Alert variant="warning">
            {reason === 'coppa'
              ? 'Ads aren’t available on this profile.'
              : 'No ad is available right now — please try again shortly.'}
          </Alert>
          <Button fullWidth onClick={onClose}>
            Close
          </Button>
        </div>
      )}

      {(phase === 'playing' || phase === 'rewarding') && (
        <div className="flex flex-col gap-4">
          <div className="flex aspect-video items-center justify-center rounded-xl bg-charcoal-900 text-white">
            <span className="text-4xl" aria-hidden="true">
              📺
            </span>
          </div>
          <p className="text-center text-sm text-muted">
            {secondsLeft > 0 ? `Ad plays for ${secondsLeft}s…` : 'Ad finished!'}
          </p>
          <Button fullWidth disabled={secondsLeft > 0} loading={phase === 'rewarding'} onClick={finishAd}>
            {secondsLeft > 0 ? `Skip in ${secondsLeft}s` : 'Continue'}
          </Button>
          <Link to="/billing" className="text-center text-xs text-muted underline">
            Remove ads with Premium
          </Link>
        </div>
      )}

      {phase === 'done' && (
        <div className="flex flex-col items-center gap-3 py-6">
          <span className="text-4xl" aria-hidden="true">
            🎉
          </span>
          <p className="text-sm text-muted">Reward claimed!</p>
        </div>
      )}
    </Modal>
  )
}
