import { useState, type ReactNode } from 'react'
import { Button3D, Icon } from '@/components/ui'
import { cn } from '@/lib/cn'
import { countQuiz, type PlayerService, type Slide } from './types'
import { SlideView } from './slides'

export interface DeckStats {
  total: number
  quizTotal: number
  correct: number
  hearts: number | null
}

interface SlideDeckProps {
  /** Shown on the start screen (e.g. unit or lesson title). */
  title: string
  subtitle?: string
  startIcon?: string
  startCta?: string
  slides: Slide[]
  service: PlayerService
  initialHearts: number | null
  onExit: () => void
  /** Render the end-of-deck screen from the run's stats. */
  renderComplete: (stats: DeckStats) => ReactNode
}

type Phase = 'start' | 'play' | 'complete'

/**
 * Immersive, slide-based player shell on a heritage "stage" (deep navy + gold
 * adire lattice). One screen at a time with a beaded gold progress, a per-unit
 * /lesson start screen, and a caller-supplied end screen. Grading is delegated
 * to the injected `service` (live API vs local preview).
 *
 * The whole stage is forced into the dark token set for a consistent cinematic
 * look regardless of the surrounding app theme.
 */
export function SlideDeck({
  title,
  subtitle,
  startIcon = '🚀',
  startCta = 'Start',
  slides,
  service,
  initialHearts,
  onExit,
  renderComplete,
}: SlideDeckProps) {
  const [phase, setPhase] = useState<Phase>('start')
  const [index, setIndex] = useState(0)
  const [hearts, setHearts] = useState<number | null>(initialHearts)
  const [correct, setCorrect] = useState(0)

  const total = slides.length
  const quizTotal = countQuiz(slides)

  function advance() {
    setIndex((i) => {
      const next = i + 1
      if (next >= total) setPhase('complete')
      return next
    })
  }

  const stats: DeckStats = { total, quizTotal, correct, hearts }

  return (
    <div className="dark heritage-stage flex min-h-screen flex-col text-foreground">
      <Header phase={phase} filled={Math.min(index, total)} total={total} hearts={hearts} onExit={onExit} />

      {phase === 'start' && (
        <StartScreen
          icon={startIcon}
          title={title}
          subtitle={subtitle}
          cta={startCta}
          empty={total === 0}
          onStart={() => (total === 0 ? setPhase('complete') : setPhase('play'))}
          onExit={onExit}
        />
      )}

      {phase === 'play' && slides[index] && (
        <SlideView
          key={slides[index].id}
          slide={slides[index]}
          service={service}
          isLast={index === total - 1}
          onAdvance={advance}
          onGraded={(ok) => ok && setCorrect((c) => c + 1)}
          onHearts={setHearts}
        />
      )}

      {phase === 'complete' && (
        <div className="mx-auto flex w-full max-w-2xl flex-1 flex-col justify-center px-4 py-10 sm:px-6">
          {renderComplete(stats)}
        </div>
      )}
    </div>
  )
}

function Header({
  phase,
  filled,
  total,
  hearts,
  onExit,
}: {
  phase: Phase
  filled: number
  total: number
  hearts: number | null
  onExit: () => void
}) {
  return (
    <header className="sticky top-0 z-20 flex items-center gap-4 px-4 py-4 sm:px-6">
      <button
        onClick={onExit}
        className="flex size-9 shrink-0 items-center justify-center rounded-full text-foreground/60 transition-colors hover:bg-foreground/10 hover:text-foreground"
        aria-label="Exit"
      >
        <Icon name="close" className="size-5" />
      </button>

      <SegmentedProgress total={total} filled={phase === 'start' ? 0 : filled} />

      {hearts !== null && (
        <span
          className="flex shrink-0 items-center gap-1.5 rounded-full bg-foreground/5 px-3 py-1.5 text-sm font-bold text-foreground ring-1 ring-foreground/10"
          aria-label={`${hearts} hearts`}
        >
          <span aria-hidden="true">{hearts > 0 ? '❤️' : '🤍'}</span> {hearts}
        </span>
      )}
    </header>
  )
}

/** Gilded bead string — one segment per slide, filled gold as the learner advances. */
function SegmentedProgress({ total, filled }: { total: number; filled: number }) {
  const segments = Math.max(total, 1)
  return (
    <div className="flex flex-1 items-center gap-1">
      {Array.from({ length: segments }).map((_, i) => (
        <span
          key={i}
          className={cn(
            'h-2.5 flex-1 rounded-full transition-all duration-300',
            i < filled
              ? 'bg-gradient-to-r from-gold-500 to-gold-300 shadow-[0_0_8px_rgba(241,197,68,0.45)]'
              : 'bg-foreground/10',
          )}
        />
      ))}
    </div>
  )
}

function StartScreen({
  icon,
  title,
  subtitle,
  cta,
  empty,
  onStart,
  onExit,
}: {
  icon: string
  title: string
  subtitle?: string
  cta: string
  empty: boolean
  onStart: () => void
  onExit: () => void
}) {
  return (
    <div className="mx-auto flex w-full max-w-md flex-1 flex-col items-center justify-center gap-4 px-4 py-10 text-center">
      <span className="relative mb-1 flex size-28 items-center justify-center" aria-hidden="true">
        <span className="absolute inset-0 rounded-full bg-gold-500/15" />
        <span className="absolute inset-2 rounded-full border-2 border-dashed border-gold-500/40" />
        <span className="text-5xl">{icon}</span>
      </span>
      <h1 className="font-display text-3xl font-bold text-foreground">{title}</h1>
      {subtitle && <p className="text-muted">{subtitle}</p>}
      {empty ? (
        <>
          <p className="text-sm text-muted">There’s no content to play here yet.</p>
          <Button3D variant="neutral" size="lg" fullWidth onClick={onExit}>
            Go back
          </Button3D>
        </>
      ) : (
        <Button3D variant="reward" size="lg" fullWidth className="mt-2" onClick={onStart}>
          {cta}
        </Button3D>
      )}
    </div>
  )
}
