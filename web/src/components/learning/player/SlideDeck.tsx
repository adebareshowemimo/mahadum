import { useMemo, useState, type ReactNode } from 'react'
import { Button3D, Icon, IconButton } from '@/components/ui'
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
  /** Resume: index of the first incomplete slide (0 = fresh start). */
  startIndex?: number
  /** Resume: correct answers already earned among the skipped-past slides. */
  initialCorrect?: number
  onExit: () => void
  /** Render the end-of-deck screen from the run's stats. */
  renderComplete: (stats: DeckStats) => ReactNode
}

type Phase = 'start' | 'play' | 'quiz-complete' | 'complete'

interface QuizRun {
  answered: number
  correct: number
  xp: number
}

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
  startIndex = 0,
  initialCorrect = 0,
  onExit,
  renderComplete,
}: SlideDeckProps) {
  const [phase, setPhase] = useState<Phase>('start')
  const [index, setIndex] = useState(0)
  const [hearts, setHearts] = useState<number | null>(initialHearts)
  const [correct, setCorrect] = useState(0)
  const [quizRuns, setQuizRuns] = useState<Record<number, QuizRun>>({})
  const [summaryComponentId, setSummaryComponentId] = useState<number | null>(null)

  const total = slides.length
  const quizTotal = countQuiz(slides)
  const items = useMemo(() => componentItems(slides), [slides])
  const canResume = startIndex > 0 && startIndex < total

  function begin(fromIndex: number, fromCorrect: number) {
    setIndex(fromIndex)
    setCorrect(fromCorrect)
    setPhase(total === 0 ? 'complete' : 'play')
  }

  function advance() {
    const current = slides[index]
    const following = slides[index + 1]
    if (current?.kind === 'quiz' && following?.componentId !== current.componentId) {
      setSummaryComponentId(current.componentId)
      setPhase('quiz-complete')
      return
    }

    setIndex((i) => {
      const next = i + 1
      if (next >= total) setPhase('complete')
      return next
    })
  }

  function recordQuizResult(componentId: number, ok: boolean, xpAwarded: number) {
    setQuizRuns((runs) => {
      const previous = runs[componentId] ?? { answered: 0, correct: 0, xp: 0 }
      return {
        ...runs,
        [componentId]: {
          answered: previous.answered + 1,
          correct: previous.correct + (ok ? 1 : 0),
          xp: previous.xp + xpAwarded,
        },
      }
    })
    if (ok) setCorrect((value) => value + 1)
  }

  function continueAfterQuiz() {
    const next = index + 1
    setSummaryComponentId(null)
    if (next >= total) setPhase('complete')
    else {
      setIndex(next)
      setPhase('play')
    }
  }

  function retryQuiz(componentId: number, fromIndex: number) {
    const run = quizRuns[componentId]
    if (run) setCorrect((value) => Math.max(0, value - run.correct))
    setQuizRuns((runs) => {
      const next = { ...runs }
      delete next[componentId]
      return next
    })
    setSummaryComponentId(null)
    setIndex(fromIndex)
    setPhase('play')
  }

  const stats: DeckStats = { total, quizTotal, correct, hearts }
  const headerFilled = phase === 'start' ? startIndex : phase === 'complete' ? total : Math.min(index + (phase === 'quiz-complete' ? 1 : 0), total)
  const summarySlides = summaryComponentId == null
    ? []
    : slides.filter((slide): slide is Extract<Slide, { kind: 'quiz' }> => slide.kind === 'quiz' && slide.componentId === summaryComponentId)
  const priorSummaryCorrect = summarySlides.filter((slide) => slide.completed && slide.wasCorrect).length
  const priorSummaryAnswered = summarySlides.filter((slide) => slide.completed).length
  const currentRun = summaryComponentId == null ? undefined : quizRuns[summaryComponentId]
  const summaryCorrect = priorSummaryCorrect + (currentRun?.correct ?? 0)
  const summaryAnswered = priorSummaryAnswered + (currentRun?.answered ?? 0)
  const summaryTotal = summarySlides.length
  const summaryThreshold = summarySlides[0]?.passThreshold ?? 0.7
  const summaryPassed = summaryTotal > 0 && summaryCorrect / summaryTotal >= summaryThreshold
  const firstQuizIndex = summaryComponentId == null ? -1 : slides.findIndex((slide) => slide.kind === 'quiz' && slide.componentId === summaryComponentId)
  let reviewVideoIndex = -1
  for (let candidateIndex = firstQuizIndex - 1; candidateIndex >= 0; candidateIndex -= 1) {
    if (slides[candidateIndex]?.kind === 'video') {
      reviewVideoIndex = candidateIndex
      break
    }
  }

  return (
    <div className="dark heritage-stage flex min-h-screen flex-col text-foreground">
      <Header filled={headerFilled} total={total} hearts={hearts} onExit={onExit} />

      {phase === 'start' && (
        <StartScreen
          icon={canResume ? '⏳' : startIcon}
          title={title}
          subtitle={subtitle}
          cta={startCta}
          empty={total === 0}
          canResume={canResume}
          items={items}
          onResume={() => begin(startIndex, initialCorrect)}
          onStartOver={() => begin(0, 0)}
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
          onGraded={(ok, xpAwarded) => recordQuizResult(slides[index].componentId, ok, xpAwarded)}
          onHearts={setHearts}
        />
      )}

      {phase === 'quiz-complete' && summaryComponentId !== null && (
        <QuizCompleteScreen
          correct={summaryCorrect}
          answered={summaryAnswered}
          total={summaryTotal}
          xp={currentRun?.xp ?? 0}
          passed={summaryPassed}
          hasNext={index + 1 < total}
          canReviewVideo={reviewVideoIndex >= 0}
          onContinue={continueAfterQuiz}
          onRetry={() => retryQuiz(summaryComponentId, firstQuizIndex)}
          onReview={() => retryQuiz(summaryComponentId, reviewVideoIndex >= 0 ? reviewVideoIndex : firstQuizIndex)}
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

function QuizCompleteScreen({
  correct,
  answered,
  total,
  xp,
  passed,
  hasNext,
  canReviewVideo,
  onContinue,
  onRetry,
  onReview,
}: {
  correct: number
  answered: number
  total: number
  xp: number
  passed: boolean
  hasNext: boolean
  canReviewVideo: boolean
  onContinue: () => void
  onRetry: () => void
  onReview: () => void
}) {
  const percentage = total > 0 ? Math.round((correct / total) * 100) : 0
  return (
    <div className="mx-auto flex w-full max-w-md flex-1 flex-col items-center justify-center gap-5 px-4 py-10 text-center">
      <span className="text-6xl" aria-hidden="true">{passed ? '🎉' : '📚'}</span>
      <div>
        <p className="text-xs font-bold uppercase tracking-wide text-gold-300">Quiz complete</p>
        <h1 className="mt-2 font-display text-3xl font-bold text-foreground">
          {correct} out of {total} correct
        </h1>
        <p className="mt-2 text-muted">{percentage}% · {answered} answered · +{xp} XP this run</p>
      </div>
      {!passed && (
        <p className="rounded-2xl bg-foreground/5 px-4 py-3 text-sm text-muted">
          Review the lesson, then try this quiz again when you’re ready.
        </p>
      )}
      <div className="flex w-full flex-col gap-2">
        {passed ? (
          <Button3D variant="reward" size="lg" fullWidth onClick={onContinue}>
            {hasNext ? 'Continue to next activity' : 'Finish lesson'}
          </Button3D>
        ) : (
          <>
            {canReviewVideo && <Button3D variant="reward" size="lg" fullWidth onClick={onReview}>Review lesson video</Button3D>}
            <Button3D variant="neutral" size="lg" fullWidth onClick={onRetry}>Retry quiz</Button3D>
            <button type="button" className="min-h-11 text-sm font-semibold text-muted underline" onClick={onContinue}>
              Continue without retrying
            </button>
          </>
        )}
      </div>
    </div>
  )
}

function Header({
  filled,
  total,
  hearts,
  onExit,
}: {
  filled: number
  total: number
  hearts: number | null
  onExit: () => void
}) {
  return (
    <header className="sticky top-0 z-20 flex items-center gap-4 px-4 py-4 sm:px-6">
      <IconButton onClick={onExit} variant="overlay" shape="circle" aria-label="Exit">
        <Icon name="close" className="size-5" />
      </IconButton>

      <SegmentedProgress total={total} filled={filled} />

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
  canResume,
  items,
  onResume,
  onStartOver,
  onExit,
}: {
  icon: string
  title: string
  subtitle?: string
  cta: string
  empty: boolean
  canResume: boolean
  items: CompItem[]
  onResume: () => void
  onStartOver: () => void
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
      {canResume && <p className="text-sm font-semibold text-gold-300">Pick up where you left off.</p>}

      {empty ? (
        <>
          <p className="text-sm text-muted">There’s no content to play here yet.</p>
          <Button3D variant="neutral" size="lg" fullWidth onClick={onExit}>
            Go back
          </Button3D>
        </>
      ) : (
        <div className="mt-1 flex w-full flex-col gap-3">
          {items.length > 0 && <CompletionChecklist items={items} />}
          {canResume ? (
            <>
              <Button3D variant="reward" size="lg" fullWidth onClick={onResume}>
                Resume
              </Button3D>
              <button
                type="button"
                onClick={onStartOver}
                className="text-sm font-medium text-muted underline underline-offset-2 hover:text-foreground"
              >
                Start over from the beginning
              </button>
            </>
          ) : (
            <Button3D variant="reward" size="lg" fullWidth onClick={onResume}>
              {cta}
            </Button3D>
          )}
        </div>
      )}
    </div>
  )
}

interface CompItem {
  componentId: number
  kind: Slide['kind']
  total: number
  done: number
}

/** Collapse the flat slide list into per-component rows (quiz = one row, N questions). */
function componentItems(slides: Slide[]): CompItem[] {
  const byId = new Map<number, CompItem>()
  const order: number[] = []
  for (const s of slides) {
    let item = byId.get(s.componentId)
    if (!item) {
      item = { componentId: s.componentId, kind: s.kind, total: 0, done: 0 }
      byId.set(s.componentId, item)
      order.push(s.componentId)
    }
    item.total += 1
    if (s.completed) item.done += 1
  }
  return order.map((id) => byId.get(id) as CompItem)
}

const KIND_META: Record<Slide['kind'], { icon: string; label: string }> = {
  video: { icon: '🎬', label: 'Video' },
  quiz: { icon: '❓', label: 'Quiz' },
  speaking: { icon: '🎙️', label: 'Speaking' },
  exercise: { icon: '🎯', label: 'Practice' },
  game: { icon: '🎮', label: 'Game' },
  assignment: { icon: '📝', label: 'Assignment' },
  generic: { icon: '•', label: 'Activity' },
}

/** Shows which steps are already done (✓) so the learner can see their progress. */
function CompletionChecklist({ items }: { items: CompItem[] }) {
  return (
    <ul className="flex flex-col gap-1.5 rounded-2xl bg-foreground/5 p-3 text-left ring-1 ring-foreground/10">
      {items.map((it) => {
        const meta = KIND_META[it.kind]
        const done = it.done >= it.total
        return (
          <li key={it.componentId} className="flex items-center gap-3 text-sm">
            <span
              className={cn(
                'flex size-6 shrink-0 items-center justify-center rounded-full text-xs',
                done ? 'bg-leaf-400/20 text-leaf-300 ring-1 ring-leaf-400/40' : 'bg-foreground/10',
              )}
            >
              {done ? '✓' : meta.icon}
            </span>
            <span className={cn('flex-1', done ? 'text-foreground' : 'text-muted')}>
              {meta.label}
              {it.kind === 'quiz' && it.total > 1 && (
                <span className="text-xs text-muted"> · {it.done}/{it.total} answered</span>
              )}
            </span>
            {done && <span className="text-xs font-semibold text-leaf-300">Completed</span>}
          </li>
        )
      })}
    </ul>
  )
}
