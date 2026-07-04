import { useEffect, useMemo, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { Alert, Button3D, Skeleton } from '@/components/ui'
import { ApiError, learningApi, type CompleteResult, type LessonPlay } from '@/lib/api'
import { useActiveProfile } from '@/lib/profile/ActiveProfile'
import { learningKeys } from '@/lib/learning/queries'
import { SlideDeck, createLiveService, playToSlides } from '@/components/learning/player'

/** Immersive, slide-based lesson player for learners (server-graded). */
export function LessonPlayerPage() {
  const { lessonId } = useParams()
  const id = Number(lessonId)
  const navigate = useNavigate()
  const { activeLearner } = useActiveProfile()

  const [play, setPlay] = useState<LessonPlay | null>(null)
  const [loadError, setLoadError] = useState(false)

  useEffect(() => {
    if (!activeLearner) {
      navigate('/learn', { replace: true })
      return
    }
    let cancelled = false
    learningApi
      .play(id, activeLearner.id)
      .then((p) => !cancelled && setPlay(p))
      .catch(() => !cancelled && setLoadError(true))
    return () => {
      cancelled = true
    }
  }, [id, activeLearner, navigate])

  const slides = useMemo(() => (play ? playToSlides(play) : []), [play])
  const service = useMemo(
    () => (activeLearner ? createLiveService(id, activeLearner.id) : null),
    [id, activeLearner],
  )

  if (!activeLearner || !service) return null
  if (loadError) {
    return (
      <div className="mx-auto max-w-md p-6">
        <Alert variant="danger">We couldn’t open this lesson. Please go back and try again.</Alert>
      </div>
    )
  }
  if (!play) return <Skeleton className="m-6 h-72" />

  return (
    <SlideDeck
      title={play.lesson.title}
      subtitle={play.lesson.est_minutes ? `About ${play.lesson.est_minutes} min` : undefined}
      startIcon="📚"
      startCta="Start lesson"
      slides={slides}
      service={service}
      initialHearts={null}
      onExit={() => navigate('/learn')}
      renderComplete={() => <LessonComplete lessonId={id} learnerId={activeLearner.id} onExit={() => navigate('/learn')} />}
    />
  )
}

function LessonComplete({ lessonId, learnerId, onExit }: { lessonId: number; learnerId: number; onExit: () => void }) {
  const qc = useQueryClient()
  const [result, setResult] = useState<CompleteResult | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    let cancelled = false
    learningApi
      .complete(lessonId, learnerId)
      .then((r) => {
        if (cancelled) return
        setResult(r)
        void qc.invalidateQueries({ queryKey: learningKeys.path(learnerId) })
      })
      .catch((err) => !cancelled && setError(err instanceof ApiError ? err.message : 'Could not finish the lesson.'))
    return () => {
      cancelled = true
    }
  }, [lessonId, learnerId, qc])

  if (error) {
    return (
      <div className="flex flex-col gap-4">
        <Alert variant="warning" title="Almost there">{error}</Alert>
        <Button3D variant="neutral" fullWidth onClick={onExit}>Back to journey</Button3D>
      </div>
    )
  }

  if (!result) return <Skeleton className="h-60" />

  const badgeCount = result.badges_unlocked?.length ?? 0

  return (
    <div className="flex flex-col items-center gap-5 text-center animate-pop-in">
      <span className="text-7xl" aria-hidden="true">🎉</span>
      <h1 className="font-display text-3xl font-bold text-foreground">Lesson complete!</h1>
      <div className="flex flex-wrap justify-center gap-3">
        <Stat label="Score" value={`${Math.round(result.lesson_score * 100)}%`} />
        <Stat label="XP earned" value={`+${result.xp_total}`} />
        <Stat label="Streak" value={`🔥 ${result.streak.count}`} />
      </div>
      {badgeCount > 0 && (
        <p className="text-sm font-semibold text-primary">🏅 {badgeCount} new badge{badgeCount === 1 ? '' : 's'}!</p>
      )}
      <Button3D variant="reward" size="lg" fullWidth onClick={onExit}>Back to journey</Button3D>
    </div>
  )
}

function Stat({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-2xl bg-surface-muted px-5 py-4">
      <p className="font-display text-2xl font-bold text-foreground">{value}</p>
      <p className="text-xs text-muted">{label}</p>
    </div>
  )
}
