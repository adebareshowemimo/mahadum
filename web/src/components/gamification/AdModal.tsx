import { useEffect, useRef, useState } from 'react'
import { Alert, Button, Modal } from '@/components/ui'
import { gamificationApi, type AdEligibility, type AdPlacement } from '@/lib/api'
import { useCompleteAd, useRequestAd } from '@/lib/gamification/queries'

type Phase = 'requesting' | 'ineligible' | 'playing' | 'rewarding' | 'done' | 'error'

/** Uploaded managed video; rewards depend on server-accepted contiguous playback. */
export function AdModal({ open, learnerId, placement, onClose, onRewarded }: {
  open: boolean
  learnerId: number
  placement: AdPlacement
  onClose: () => void
  onRewarded: (impressionId: number) => unknown | Promise<unknown>
}) {
  const requestAd = useRequestAd(learnerId)
  const completeAd = useCompleteAd()
  const [phase, setPhase] = useState<Phase>('requesting')
  const [session, setSession] = useState<AdEligibility | null>(null)
  const generation = useRef(0)
  const position = useRef(0)
  const pending = useRef<Promise<void>>(Promise.resolve())
  const videoRef = useRef<HTMLVideoElement>(null)
  const failed = useRef(false)
  const finishing = useRef(false)

  useEffect(() => {
    const current = ++generation.current
    if (!open) return
    setPhase('requesting')
    setSession(null)
    position.current = 0
    pending.current = Promise.resolve()
    failed.current = false
    finishing.current = false
    requestAd.mutate(placement, {
      onSuccess: (res) => {
        if (generation.current !== current) return
        setSession(res)
        setPhase(res.eligible && res.impression_id && res.video ? 'playing' : 'ineligible')
      },
      onError: () => { if (generation.current === current) setPhase('ineligible') },
    })
    return () => { generation.current++ }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, placement, learnerId])

  useEffect(() => {
    function pauseHiddenVideo() {
      if (document.hidden) videoRef.current?.pause()
    }
    document.addEventListener('visibilitychange', pauseHiddenVideo)
    return () => document.removeEventListener('visibilitychange', pauseHiddenVideo)
  }, [])

  function fail() {
    failed.current = true
    videoRef.current?.pause()
    setPhase('error')
  }

  function report(force = false): Promise<void> {
    const video = videoRef.current
    if (!video || !session?.impression_id || failed.current || document.hidden || video.seeking || video.playbackRate !== 1) return pending.current
    const next = Math.min(video.currentTime, session.video!.duration_seconds)
    if (next <= position.current || (!force && (video.paused || next - position.current < 2))) return pending.current
    const current = generation.current
    const impressionId = session.impression_id
    position.current = next
    pending.current = pending.current.then(async () => {
      if (generation.current !== current || failed.current) return
      await gamificationApi.recordAdProgress(impressionId, next)
    }).catch(() => { if (generation.current === current) fail() })
    return pending.current
  }

  async function finish() {
    if (!session?.impression_id || failed.current || finishing.current || document.hidden) return
    finishing.current = true
    const current = generation.current
    await report(true)
    if (failed.current || generation.current !== current) return
    setPhase('rewarding')
    try {
      const result = await completeAd.mutateAsync(session.impression_id)
      if (generation.current !== current) return
      if (!result.shown) { fail(); return }
      await onRewarded(session.impression_id)
      if (generation.current === current) setPhase('done')
    } catch {
      if (generation.current === current) fail()
    }
  }

  return (
    <Modal open={open} onClose={onClose} title="Watch a video to refill hearts">
      {phase === 'requesting' && <p className="py-8 text-center text-sm text-muted">Finding a video…</p>}
      {phase === 'ineligible' && <Alert variant="warning">{session?.reason === 'coppa' ? 'Ads aren’t available on this profile.' : 'No video is available right now. You can wait for your hearts to refill or view plans.'}</Alert>}
      {(phase === 'playing' || phase === 'rewarding') && session?.video && <div className="flex flex-col gap-4">
        <video
          ref={videoRef}
          src={session.video.url}
          controls playsInline preload="metadata"
          aria-label="Rewarded video"
          className="aspect-video w-full rounded-xl bg-charcoal-900"
          onLoadedMetadata={(event) => {
            if (!Number.isFinite(event.currentTarget.duration) || Math.abs(event.currentTarget.duration - session.video!.duration_seconds) > 1) fail()
          }}
          onTimeUpdate={() => { void report() }}
          onSeeking={(event) => { if (Math.abs(event.currentTarget.currentTime - position.current) > 0.5) fail() }}
          onRateChange={(event) => { if (event.currentTarget.playbackRate !== 1) event.currentTarget.playbackRate = 1 }}
          onEnded={() => { void finish() }}
          onError={fail}
        />
        <p className="text-center text-sm text-muted">{phase === 'rewarding' ? 'Verifying playback and refilling hearts…' : 'Watch the whole video without seeking to refill all five hearts. Pausing is fine.'}</p>
      </div>}
      {phase === 'error' && <Alert variant="warning">We couldn’t verify playback or finish the refill. Close this window and check your hearts before trying again.</Alert>}
      {phase === 'done' && <Alert variant="success">{placement === 'rewarded_heart' ? 'All five hearts are refilled!' : 'Video completed.'}</Alert>}
      <Button fullWidth className="mt-4" onClick={onClose}>Close</Button>
    </Modal>
  )
}
