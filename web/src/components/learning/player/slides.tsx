import { useCallback, useEffect, useMemo, useRef, useState, type ChangeEvent, type ReactNode } from 'react'
import { Alert, Button3D, Icon } from '@/components/ui'
import { cn } from '@/lib/cn'
import { ApiError } from '@/lib/api'
import type { Answer, AssignmentSlide, ExerciseSlide, GameSlide, GenericSlide, PlayerService, QType, QuizSlide, Slide, SpeakingSlide, Verdict, VideoSlide } from './types'
import { youtubeEmbedUrl } from './types'

export interface SlideProps {
  slide: Slide
  service: PlayerService
  isLast: boolean
  onAdvance: () => void
  onGraded: (correct: boolean) => void
  onHearts: (hearts: number | null) => void
}

/** Routes a slide to its renderer. Each renderer owns its own action bar. */
export function SlideView(props: SlideProps) {
  switch (props.slide.kind) {
    case 'quiz':
      return <QuizSlideView {...props} slide={props.slide} />
    case 'video':
      return <VideoSlideView {...props} slide={props.slide} />
    case 'speaking':
      return <SpeakingSlideView {...props} slide={props.slide} />
    case 'assignment':
      return <AssignmentSlideView {...props} slide={props.slide} />
    case 'exercise':
      return <ExerciseSlideView {...props} slide={props.slide} />
    case 'game':
      return <GameSlideView {...props} slide={props.slide} />
    default:
      return <GenericSlideView {...props} slide={props.slide} />
  }
}

// ---- Shared layout pieces ----

/** Gold-outlined heritage kicker chip. */
function Chip({ icon, label }: { icon: string; label: string }) {
  return (
    <span className="inline-flex w-fit items-center gap-2 rounded-full border border-gold-500/40 bg-gold-500/10 px-3.5 py-1 text-xs font-bold uppercase tracking-[0.14em] text-gold-300 animate-step-in">
      <span aria-hidden="true">{icon}</span> {label}
    </span>
  )
}

/** Scrollable slide body, centered, with a heritage kicker chip. */
function SlideBody({ chip, children }: { chip: { icon: string; label: string }; children: ReactNode }) {
  return (
    <div className="flex flex-1 flex-col">
      <div className="mx-auto flex w-full max-w-2xl flex-1 flex-col justify-center gap-7 px-4 py-8 sm:px-6">
        <Chip icon={chip.icon} label={chip.label} />
        {children}
      </div>
    </div>
  )
}

type Tone = 'neutral' | 'correct' | 'incorrect'

const TONE_BAR: Record<Tone, string> = {
  neutral: 'border-border bg-background/85 backdrop-blur',
  correct: 'border-leaf-700/60 bg-gradient-to-t from-leaf-700/35 to-transparent',
  incorrect: 'border-clay-700/60 bg-gradient-to-t from-clay-700/35 to-transparent',
}

/** Sticky bottom bar that holds the primary action / feedback. */
function ActionBar({ tone = 'neutral', children }: { tone?: Tone; children: ReactNode }) {
  return (
    <div className={cn('sticky bottom-0 z-10 border-t', TONE_BAR[tone])}>
      <div className="mx-auto flex w-full max-w-2xl items-center gap-4 px-4 py-4 sm:px-6">{children}</div>
    </div>
  )
}

function Feedback({ verdict, isLast, onAdvance }: { verdict: Verdict; isLast: boolean; onAdvance: () => void }) {
  return (
    <ActionBar tone={verdict.correct ? 'correct' : 'incorrect'}>
      {/* Medallion — a gold coin for a win, a clay token for a retry. */}
      <span
        className={cn(
          'flex size-12 shrink-0 items-center justify-center rounded-full text-2xl font-bold ring-2 ring-offset-2 ring-offset-transparent animate-stamp-in',
          verdict.correct ? 'bg-gold-400 text-charcoal-900 ring-gold-200/70' : 'bg-clay-500 text-white ring-clay-300/60',
        )}
        aria-hidden="true"
      >
        {verdict.correct ? '✓' : '↺'}
      </span>

      <div className="min-w-0 flex-1 text-foreground">
        <p className="font-display text-lg font-bold">{verdict.correct ? 'Well done!' : 'Not quite'}</p>
        {verdict.correct && verdict.xpAwarded > 0 && (
          <p className="text-sm font-semibold text-gold-200">+{verdict.xpAwarded} XP earned</p>
        )}
        {!verdict.correct && verdict.correctText && (
          <p className="truncate text-sm text-foreground/80">Answer: {verdict.correctText}</p>
        )}
        {verdict.explanation && <p className="truncate text-sm text-foreground/70">{verdict.explanation}</p>}
        {verdict.attemptsExhausted && (
          <p className="truncate text-sm text-foreground/60">Practice mode — this replay won’t change your score.</p>
        )}
      </div>

      <Button3D variant="reward" onClick={onAdvance}>
        {isLast ? 'Finish' : 'Continue'}
      </Button3D>
    </ActionBar>
  )
}

// ---- Quiz ----
//
// One QuizSlideView owns the check → grade → feedback flow; the actual input is
// delegated per question type. Each input reports its current answer (or null
// when not yet answerable) so the Check button knows when to enable.

const QUIZ_CHIP: Record<QType, { icon: string; label: string }> = {
  mcq_single: { icon: '◆', label: 'Choose the answer' },
  mcq_multi: { icon: '◆', label: 'Select all that apply' },
  true_false: { icon: '◆', label: 'True or false' },
  fill_blank: { icon: '◆', label: 'Fill the blank' },
  listen_and_respond: { icon: '🔊', label: 'Listen and reply' },
  complete_the_chat: { icon: '💬', label: 'Complete the chat' },
  word_bank: { icon: '◆', label: 'Build the sentence' },
  match_pairs: { icon: '◆', label: 'Match the pairs' },
  type_what_you_hear: { icon: '◆', label: 'Type the answer' },
}

/** Fold case + diacritics, mirroring the server's fuzzy match (for verdict UI). */
const fold = (s: string) => s.trim().toLowerCase().normalize('NFD').replace(/\p{Diacritic}/gu, '')

interface QuizInputProps {
  slide: QuizSlide
  verdict: Verdict | null
  onAnswer: (a: Answer | null) => void
}

function QuizSlideView({ slide, service, isLast, onAdvance, onGraded, onHearts }: SlideProps & { slide: QuizSlide }) {
  const [answer, setAnswer] = useState<Answer | null>(null)
  const [verdict, setVerdict] = useState<Verdict | null>(null)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const chip = QUIZ_CHIP[slide.qtype] ?? QUIZ_CHIP.mcq_single

  async function check() {
    if (!answer) return
    setBusy(true)
    setError(null)
    try {
      const res = await service.gradeQuiz(slide, answer)
      setVerdict(res)
      onGraded(res.correct)
      onHearts(res.heartsRemaining)
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not check your answer.')
    } finally {
      setBusy(false)
    }
  }

  return (
    <>
      <SlideBody chip={chip}>
        <p className="font-display text-2xl font-bold leading-snug text-foreground">{slide.prompt}</p>
        <QuizInput slide={slide} verdict={verdict} onAnswer={setAnswer} />
        {error && <Alert variant="danger">{error}</Alert>}
      </SlideBody>

      {verdict ? (
        <Feedback verdict={verdict} isLast={isLast} onAdvance={onAdvance} />
      ) : (
        <ActionBar>
          <Button3D variant="reward" size="lg" fullWidth disabled={!answer || busy} onClick={check}>
            {busy ? 'Checking…' : 'Check'}
          </Button3D>
        </ActionBar>
      )}
    </>
  )
}

function QuizInput({ slide, verdict, onAnswer }: QuizInputProps) {
  switch (slide.qtype) {
    case 'mcq_multi':
      return <MultiInput slide={slide} verdict={verdict} onAnswer={onAnswer} />
    case 'word_bank':
      return <WordBankInput slide={slide} verdict={verdict} onAnswer={onAnswer} />
    case 'match_pairs':
      return <MatchInput slide={slide} verdict={verdict} onAnswer={onAnswer} />
    case 'type_what_you_hear':
      return <TextInput slide={slide} verdict={verdict} onAnswer={onAnswer} />
    default:
      // mcq_single, true_false, fill_blank, listen_and_respond, complete_the_chat
      return <OptionInput slide={slide} verdict={verdict} onAnswer={onAnswer} />
  }
}

/** Audio + image prompt affordances shown above the options. */
function PromptMedia({ slide }: { slide: QuizSlide }) {
  if (!slide.promptAudio && !slide.promptImage) return null
  return (
    <div className="flex flex-col items-start gap-3">
      {slide.promptAudio && <AudioPrompt src={slide.promptAudio} />}
      {slide.promptImage && (
        <img src={slide.promptImage} alt="" className="max-h-48 rounded-2xl border border-gold-500/20 object-contain" />
      )}
    </div>
  )
}

function AudioPrompt({ src }: { src: string }) {
  const ref = useRef<HTMLAudioElement>(null)
  return (
    <button
      type="button"
      onClick={() => {
        const a = ref.current
        if (a) {
          a.currentTime = 0
          void a.play()
        }
      }}
      className="inline-flex items-center gap-2 rounded-full border border-gold-500/40 bg-gold-500/10 px-4 py-2 text-sm font-bold text-gold-200 hover:bg-gold-500/20"
    >
      <span aria-hidden="true">🔊</span> Play audio
      <audio ref={ref} src={src} preload="auto" />
    </button>
  )
}

type OptState = 'idle' | 'chosen' | 'correct' | 'wrong' | 'dim'

function optionClasses(state: OptState): string {
  return cn(
    'flex w-full items-center gap-4 rounded-2xl border-2 px-4 py-4 text-left text-base font-semibold transition-all duration-150 active:translate-y-[3px]',
    state === 'chosen' && 'border-gold-400 bg-gold-500/12 text-foreground shadow-[0_4px_0_var(--gold-700)]',
    state === 'idle' &&
      'border-border bg-surface text-foreground shadow-[0_4px_0_rgba(0,0,0,0.35)] hover:-translate-y-0.5 hover:border-gold-500/50',
    state === 'correct' && 'border-leaf-400 bg-leaf-500/15 text-leaf-100 shadow-[0_4px_0_var(--leaf-700)]',
    state === 'wrong' && 'border-clay-400 bg-clay-500/15 text-clay-100 shadow-[0_4px_0_var(--clay-600)]',
    state === 'dim' && 'border-border bg-surface/40 text-muted opacity-60',
  )
}

function OptionButton({
  marker,
  label,
  state,
  disabled,
  onClick,
}: {
  marker: ReactNode
  label: string
  state: OptState
  disabled: boolean
  onClick: () => void
}) {
  return (
    <button type="button" disabled={disabled} onClick={onClick} className={optionClasses(state)}>
      {/* Adire diamond token holding the option marker. */}
      <span className="flex size-8 shrink-0 rotate-45 items-center justify-center rounded-[7px] border-2 border-current">
        <span className="-rotate-45 text-xs font-bold">{marker}</span>
      </span>
      <span className="flex-1">{label}</span>
      {state === 'correct' && <Icon name="cap" className="size-5 shrink-0" />}
    </button>
  )
}

/** Single-select family: mcq_single, true_false, fill_blank, listen_and_respond, complete_the_chat. */
function OptionInput({ slide, verdict, onAnswer }: QuizInputProps) {
  const [selected, setSelected] = useState<number | null>(null)
  function pick(id: number) {
    setSelected(id)
    onAnswer({ optionId: id })
  }
  return (
    <div className="flex flex-col gap-3">
      <PromptMedia slide={slide} />
      {slide.options.map((opt, i) => {
        let state: OptState = selected === opt.id ? 'chosen' : 'idle'
        if (verdict) {
          const isCorrect = verdict.correctOptionIds.includes(opt.id)
          state = isCorrect ? 'correct' : selected === opt.id ? 'wrong' : 'dim'
        }
        return (
          <OptionButton key={opt.id} marker={i + 1} label={opt.label} state={state} disabled={!!verdict} onClick={() => pick(opt.id)} />
        )
      })}
    </div>
  )
}

/** Multi-select: pick every correct option (order-independent). */
function MultiInput({ slide, verdict, onAnswer }: QuizInputProps) {
  const [sel, setSel] = useState<number[]>([])
  function toggle(id: number) {
    const next = sel.includes(id) ? sel.filter((x) => x !== id) : [...sel, id]
    setSel(next)
    onAnswer(next.length ? { optionIds: next } : null)
  }
  return (
    <div className="flex flex-col gap-3">
      <p className="text-sm text-muted">Select all that apply.</p>
      {slide.options.map((opt) => {
        const chosen = sel.includes(opt.id)
        let state: OptState = chosen ? 'chosen' : 'idle'
        if (verdict) {
          const isCorrect = verdict.correctOptionIds.includes(opt.id)
          state = isCorrect ? 'correct' : chosen ? 'wrong' : 'dim'
        }
        return (
          <OptionButton key={opt.id} marker={chosen ? '✓' : ''} label={opt.label} state={state} disabled={!!verdict} onClick={() => toggle(opt.id)} />
        )
      })}
    </div>
  )
}

/** Free-text (type_what_you_hear) — fuzzy graded server-side. */
function TextInput({ slide, verdict, onAnswer }: QuizInputProps) {
  const [text, setText] = useState('')
  return (
    <div className="flex flex-col gap-3">
      <PromptMedia slide={slide} />
      <input
        value={text}
        onChange={(e) => {
          setText(e.target.value)
          onAnswer(e.target.value.trim() ? { text: e.target.value.trim() } : null)
        }}
        disabled={!!verdict}
        placeholder="Type your answer"
        autoFocus
        className="h-14 w-full rounded-2xl border-2 border-border bg-surface px-4 text-lg text-foreground placeholder:text-muted focus:border-gold-400 focus:outline-none focus:ring-2 focus:ring-gold-400/40 disabled:opacity-70"
      />
    </div>
  )
}

/** Word bank — arrange tiles into the sentence in order. */
function WordBankInput({ slide, verdict, onAnswer }: QuizInputProps) {
  const [order, setOrder] = useState<number[]>([])
  const byId = useMemo(() => new Map(slide.options.map((o) => [o.id, o.label])), [slide.options])
  const pool = slide.options.filter((o) => !order.includes(o.id))

  function add(id: number) {
    const next = [...order, id]
    setOrder(next)
    onAnswer(next.length === slide.options.length ? { optionIds: next } : null)
  }
  function removeAt(i: number) {
    setOrder(order.filter((_, j) => j !== i))
    onAnswer(null)
  }

  return (
    <div className="flex flex-col gap-4">
      <PromptMedia slide={slide} />
      {/* Sentence tray — tap a tile to send it back. */}
      <div className="flex min-h-[3.5rem] flex-wrap items-center gap-2 rounded-2xl border-2 border-dashed border-border p-3">
        {order.length === 0 && <span className="text-sm text-muted">Tap the words in order…</span>}
        {order.map((id, i) => (
          <button
            key={`${id}-${i}`}
            type="button"
            disabled={!!verdict}
            onClick={() => removeAt(i)}
            className="rounded-xl border-2 border-gold-400 bg-gold-500/12 px-3 py-2 text-sm font-semibold text-foreground disabled:opacity-70"
          >
            {byId.get(id)}
          </button>
        ))}
      </div>
      {/* Remaining tiles. */}
      <div className="flex flex-wrap gap-2">
        {pool.map((o) => (
          <button
            key={o.id}
            type="button"
            disabled={!!verdict}
            onClick={() => add(o.id)}
            className="rounded-xl border-2 border-border bg-surface px-3 py-2 text-sm font-semibold text-foreground shadow-[0_3px_0_rgba(0,0,0,0.35)] hover:border-gold-500/50 disabled:opacity-50"
          >
            {o.label}
          </button>
        ))}
      </div>
      {verdict && !verdict.correct && (
        <p className="text-sm text-foreground/80">
          Correct order: <span className="font-semibold text-leaf-200">{verdict.correctOptionIds.map((id) => byId.get(id)).join(' ')}</span>
        </p>
      )}
    </div>
  )
}

/** Match pairs — assign each left item a target from the shuffled pool. */
function MatchInput({ slide, verdict, onAnswer }: QuizInputProps) {
  const [pairs, setPairs] = useState<Record<number, string>>({})
  function assign(optionId: number, target: string) {
    const next = { ...pairs, [optionId]: target }
    setPairs(next)
    const complete = slide.options.every((o) => next[o.id])
    onAnswer(complete ? { pairs: slide.options.map((o) => ({ option_id: o.id, match_target: next[o.id] })) } : null)
  }
  return (
    <div className="flex flex-col gap-3">
      {slide.options.map((o) => {
        const chosen = pairs[o.id] ?? ''
        const correctTarget = verdict?.correctPairs.find((p) => p.option_id === o.id)?.match_target ?? ''
        const rowCorrect = !!verdict && fold(chosen) === fold(correctTarget)
        return (
          <div
            key={o.id}
            className={cn(
              'flex items-center gap-3 rounded-2xl border-2 p-3',
              !verdict && 'border-border bg-surface',
              verdict && rowCorrect && 'border-leaf-400 bg-leaf-500/15',
              verdict && !rowCorrect && 'border-clay-400 bg-clay-500/15',
            )}
          >
            <span className="min-w-0 flex-1 font-semibold text-foreground">{o.label}</span>
            <span aria-hidden="true" className="text-muted">→</span>
            {verdict ? (
              <span className="flex-1 text-sm font-semibold text-foreground">
                {rowCorrect ? (
                  chosen
                ) : (
                  <>
                    {chosen || '—'} <span className="text-leaf-200">({correctTarget})</span>
                  </>
                )}
              </span>
            ) : (
              <select
                value={chosen}
                onChange={(e) => assign(o.id, e.target.value)}
                className="h-11 flex-1 rounded-xl border-2 border-border bg-surface px-3 text-sm text-foreground focus:border-gold-400 focus:outline-none"
              >
                <option value="">Choose…</option>
                {slide.matchPool.map((t, i) => (
                  <option key={`${t}-${i}`} value={t}>
                    {t}
                  </option>
                ))}
              </select>
            )}
          </div>
        )
      })}
    </div>
  )
}

/** Seconds → "m:ss". */
function fmtClock(seconds: number): string {
  const s = Math.max(0, Math.round(seconds))
  return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`
}

// ---- Video ----
//
// A continue button is always shown. When the video is set to "must watch", the
// button stays disabled until the clip finishes; otherwise the learner may skip.
// Every watch is tracked (xAPI Video Profile) — cumulative watch time, play
// count, playhead position, duration. YouTube videos (sourceType='youtube')
// embed via iframe instead — there is no watch-time tracking or "must watch"
// gate for those, since a cross-origin iframe fires no playback events without
// integrating the separate YouTube IFrame Player API. Continue is always
// available for them.

function VideoSlideView({ slide, service, onAdvance }: SlideProps & { slide: VideoSlide }) {
  const isYoutube = slide.sourceType === 'youtube'
  const embedUrl = useMemo(() => youtubeEmbedUrl(slide.externalUrl), [slide.externalUrl])
  const videoRef = useRef<HTMLVideoElement>(null)
  const [failed, setFailed] = useState(false)
  // Already-completed videos start unlocked (no forced re-watch).
  const [watchedToEnd, setWatchedToEnd] = useState(slide.alreadyCompleted)
  const [resumedFrom, setResumedFrom] = useState(0)
  const [busy, setBusy] = useState(false)

  // Unsent deltas + last playhead, kept in refs so listeners stay stable.
  const watchedDeltaRef = useRef(0)
  const playDeltaRef = useRef(0)
  const lastTimeRef = useRef(0)
  const durationRef = useRef<number | null>(null)
  // True while we programmatically seek to the resume point (skip its tracking).
  const resumingRef = useRef(false)

  const flush = useCallback(
    (event: 'played' | 'paused' | 'seeked' | 'heartbeat' | 'completed') => {
      const v = videoRef.current
      const watchedDelta = watchedDeltaRef.current
      const playDelta = playDeltaRef.current
      // Skip empty heartbeats to avoid needless writes.
      if (event === 'heartbeat' && watchedDelta < 1 && playDelta === 0) return
      watchedDeltaRef.current = 0
      playDeltaRef.current = 0
      void service
        .trackVideo(slide, {
          event,
          watchedDelta,
          playDelta,
          positionSeconds: v?.currentTime ?? lastTimeRef.current,
          durationSeconds: durationRef.current,
          completed: event === 'completed',
        })
        .catch(() => {})
    },
    [service, slide],
  )

  useEffect(() => {
    const v = videoRef.current
    if (!v || !slide.src) return

    const onLoaded = () => {
      durationRef.current = Number.isFinite(v.duration) ? v.duration : null
      // Resume from the saved playhead (unless already finished or near the end).
      const resumeAt = slide.resumeAt
      const dur = durationRef.current
      if (!slide.alreadyCompleted && resumeAt > 1 && (dur === null || resumeAt < dur - 1)) {
        resumingRef.current = true
        v.currentTime = resumeAt
        lastTimeRef.current = resumeAt
        setResumedFrom(resumeAt)
      }
    }
    const onPlay = () => {
      playDeltaRef.current += 1
      lastTimeRef.current = v.currentTime
      flush('played')
    }
    const onTimeUpdate = () => {
      const now = v.currentTime
      const dt = now - lastTimeRef.current
      lastTimeRef.current = now
      // Count only contiguous playback (ignore seeks / large jumps).
      if (dt > 0 && dt < 2) watchedDeltaRef.current += dt
    }
    const onPause = () => {
      if (!v.ended) flush('paused')
    }
    const onSeeked = () => {
      lastTimeRef.current = v.currentTime
      // Don't record the automatic resume seek as a learner action.
      if (resumingRef.current) {
        resumingRef.current = false
        return
      }
      flush('seeked')
    }
    const onEnded = () => {
      setWatchedToEnd(true)
      flush('completed')
    }
    const onError = () => setFailed(true)

    v.addEventListener('loadedmetadata', onLoaded)
    v.addEventListener('play', onPlay)
    v.addEventListener('timeupdate', onTimeUpdate)
    v.addEventListener('pause', onPause)
    v.addEventListener('seeked', onSeeked)
    v.addEventListener('ended', onEnded)
    v.addEventListener('error', onError)

    const heartbeat = setInterval(() => {
      if (!v.paused && !v.ended) flush('heartbeat')
    }, 15000)

    return () => {
      clearInterval(heartbeat)
      v.removeEventListener('loadedmetadata', onLoaded)
      v.removeEventListener('play', onPlay)
      v.removeEventListener('timeupdate', onTimeUpdate)
      v.removeEventListener('pause', onPause)
      v.removeEventListener('seeked', onSeeked)
      v.removeEventListener('ended', onEnded)
      v.removeEventListener('error', onError)
      // Best-effort final flush when leaving mid-clip.
      if (watchedDeltaRef.current >= 1 || playDeltaRef.current > 0) flush('paused')
    }
  }, [slide.src, flush])

  async function onContinue() {
    setBusy(true)
    try {
      await service.completeStep(slide)
      onAdvance()
    } finally {
      setBusy(false)
    }
  }

  const hasVideo = (isYoutube ? !!embedUrl : !!slide.src) && !failed
  // The gate only applies to a real, playable clip the learner could finish —
  // YouTube embeds fire no trackable events, so they're never gated.
  const locked = !isYoutube && slide.requireWatch && hasVideo && !watchedToEnd

  return (
    <>
      <SlideBody chip={{ icon: '▶', label: 'Watch' }}>
        {slide.title && <p className="font-display text-2xl font-bold text-foreground">{slide.title}</p>}
        {hasVideo && isYoutube ? (
          <iframe
            src={embedUrl ?? undefined}
            title={slide.title ?? 'Video'}
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
            allowFullScreen
            className="aspect-video w-full rounded-2xl bg-charcoal-900 ring-1 ring-gold-500/20"
          />
        ) : hasVideo ? (
          <video
            ref={videoRef}
            src={slide.src ?? undefined}
            controls
            playsInline
            poster={slide.poster ?? undefined}
            className="aspect-video w-full rounded-2xl bg-charcoal-900 ring-1 ring-gold-500/20"
          />
        ) : (
          <div className="flex aspect-video items-center justify-center rounded-2xl border border-gold-500/20 bg-charcoal-900 text-foreground/70">
            <div className="flex flex-col items-center gap-2">
              <Icon name="book" className="size-8 text-gold-400" />
              <span className="text-sm">{failed ? 'This video couldn’t load' : 'Video coming soon'}</span>
            </div>
          </div>
        )}
        <p className="text-sm text-muted">
          {resumedFrom > 0 && !watchedToEnd && (
            <span className="font-semibold text-gold-300">Resumed from {fmtClock(resumedFrom)}. </span>
          )}
          {locked
            ? 'Watch the full video to continue.'
            : slide.requireWatch && watchedToEnd
              ? 'Finished — you’re good to continue.'
              : 'Continue when you’re ready.'}
        </p>
      </SlideBody>

      <ActionBar>
        <Button3D variant="reward" size="lg" fullWidth disabled={busy || locked} onClick={onContinue}>
          {busy ? 'Saving…' : 'Continue'}
        </Button3D>
      </ActionBar>
    </>
  )
}

// ---- Speaking ----

function SpeakingSlideView({ slide, service, onAdvance }: SlideProps & { slide: SpeakingSlide }) {
  const [recording, setRecording] = useState(false)
  const [blob, setBlob] = useState<Blob | null>(null)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const recorderRef = useRef<MediaRecorder | null>(null)
  const chunksRef = useRef<Blob[]>([])
  const canRecord = typeof navigator !== 'undefined' && !!navigator.mediaDevices && typeof MediaRecorder !== 'undefined'

  async function toggleRecord() {
    if (recording) {
      recorderRef.current?.stop()
      return
    }
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true })
      const rec = new MediaRecorder(stream)
      chunksRef.current = []
      rec.ondataavailable = (e) => e.data.size && chunksRef.current.push(e.data)
      rec.onstop = () => {
        setBlob(new Blob(chunksRef.current, { type: 'audio/webm' }))
        stream.getTracks().forEach((t) => t.stop())
        setRecording(false)
      }
      recorderRef.current = rec
      rec.start()
      setRecording(true)
    } catch {
      setError('We couldn’t access your microphone. You can still continue without a recording.')
    }
  }

  async function submit() {
    setBusy(true)
    setError(null)
    try {
      await service.submitSpeaking(slide, blob)
      onAdvance()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not submit. Please try again.')
      setBusy(false)
    }
  }

  return (
    <>
      <SlideBody chip={{ icon: '◐', label: 'Say it aloud' }}>
        <div className="rounded-2xl border border-gold-500/25 bg-surface p-6 text-center ring-1 ring-inset ring-gold-500/10">
          <p className="text-sm text-muted">Say this out loud:</p>
          <p className="mt-2 font-display text-2xl font-bold text-foreground">{slide.target || slide.prompt || 'Practice speaking'}</p>
          {slide.prompt && slide.target && <p className="mt-2 text-sm text-muted">{slide.prompt}</p>}
        </div>

        {error && <Alert variant="info">{error}</Alert>}

        {canRecord && (
          <Button3D
            variant={recording ? 'danger' : 'neutral'}
            fullWidth
            leftIcon={<span aria-hidden="true">{recording ? '⏺️' : '🎤'}</span>}
            onClick={toggleRecord}
          >
            {recording ? 'Stop recording' : blob ? 'Re-record' : 'Record your voice'}
          </Button3D>
        )}
        {blob && <p className="text-center text-xs text-gold-300">Recording ready ✓</p>}
      </SlideBody>
      <ActionBar>
        <Button3D variant="reward" size="lg" fullWidth disabled={busy || recording} onClick={submit}>
          {busy ? 'Submitting…' : blob ? 'Submit recording' : 'Submit'}
        </Button3D>
      </ActionBar>
    </>
  )
}

// ---- Assignment ----
//
// The learner records/uploads a short clip. Learning is never gated on approval:
// submitting completes the step immediately, and any coin reward is escrowed
// until a parent approves the clip in their review queue.

function AssignmentSlideView({ slide, service, onAdvance }: SlideProps & { slide: AssignmentSlide }) {
  const isAudio = slide.expectedMedia === 'audio'
  const [blob, setBlob] = useState<Blob | null>(null)
  const [filename, setFilename] = useState('')
  const [recording, setRecording] = useState(false)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const recorderRef = useRef<MediaRecorder | null>(null)
  const chunksRef = useRef<Blob[]>([])
  const canRecord = isAudio && typeof navigator !== 'undefined' && !!navigator.mediaDevices && typeof MediaRecorder !== 'undefined'

  function onFile(e: ChangeEvent<HTMLInputElement>) {
    const f = e.target.files?.[0] ?? null
    if (f) {
      setBlob(f)
      setFilename(f.name)
    }
  }

  async function toggleRecord() {
    if (recording) {
      recorderRef.current?.stop()
      return
    }
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true })
      const rec = new MediaRecorder(stream)
      chunksRef.current = []
      rec.ondataavailable = (e) => e.data.size && chunksRef.current.push(e.data)
      rec.onstop = () => {
        setBlob(new Blob(chunksRef.current, { type: 'audio/webm' }))
        setFilename('assignment.webm')
        stream.getTracks().forEach((t) => t.stop())
        setRecording(false)
      }
      recorderRef.current = rec
      rec.start()
      setRecording(true)
    } catch {
      setError('We couldn’t access your microphone. You can upload a file instead.')
    }
  }

  async function submit() {
    setBusy(true)
    setError(null)
    try {
      await service.submitAssignment(slide, blob, filename || undefined)
      onAdvance()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not submit. Please try again.')
      setBusy(false)
    }
  }

  return (
    <>
      <SlideBody chip={{ icon: '📹', label: 'Your turn' }}>
        <div className="rounded-2xl border border-gold-500/25 bg-surface p-6 ring-1 ring-inset ring-gold-500/10">
          <p className="font-display text-xl font-bold text-foreground">{slide.prompt || 'Record your response'}</p>
          <p className="mt-2 text-sm text-muted">
            Record a short {isAudio ? 'audio' : 'video'} clip{slide.maxDuration ? ` (up to ${slide.maxDuration}s)` : ''}.
          </p>
          {slide.coinReward > 0 && (
            <p className="mt-3 inline-flex items-center gap-1.5 rounded-full border border-gold-500/40 bg-gold-500/10 px-3 py-1 text-sm font-bold text-gold-200">
              🪙 {slide.coinReward} on approval
            </p>
          )}
        </div>

        {error && <Alert variant="info">{error}</Alert>}

        {canRecord && (
          <Button3D
            variant={recording ? 'danger' : 'neutral'}
            fullWidth
            leftIcon={<span aria-hidden="true">{recording ? '⏺️' : '🎤'}</span>}
            onClick={toggleRecord}
          >
            {recording ? 'Stop recording' : blob ? 'Re-record' : 'Record your voice'}
          </Button3D>
        )}

        <label className="flex cursor-pointer flex-col items-center gap-2 rounded-2xl border-2 border-dashed border-gold-500/30 p-6 text-center hover:bg-surface/60">
          <span className="text-3xl" aria-hidden="true">{isAudio ? '🎧' : '🎬'}</span>
          <span className="text-sm font-medium text-foreground">
            {blob ? filename || 'Clip ready' : `Choose ${isAudio ? 'an audio' : 'a video'} file`}
          </span>
          <input type="file" accept={isAudio ? 'audio/*' : 'video/*'} className="hidden" onChange={onFile} />
        </label>
        {blob && <p className="text-center text-xs text-gold-300">Clip ready ✓ — your grown-up reviews it to release coins.</p>}
      </SlideBody>
      <ActionBar>
        <Button3D variant="reward" size="lg" fullWidth disabled={busy || recording} onClick={submit}>
          {busy ? 'Submitting…' : blob ? 'Submit clip' : 'Submit'}
        </Button3D>
      </ActionBar>
    </>
  )
}

// ---- Exercise (flashcards) ----

function ExerciseSlideView({ slide, service, onAdvance }: SlideProps & { slide: ExerciseSlide }) {
  const cards = slide.cards
  const [index, setIndex] = useState(0)
  const [flipped, setFlipped] = useState(false)
  const [busy, setBusy] = useState(false)
  const audioRef = useRef<HTMLAudioElement>(null)
  const card = cards[index]
  const isLastCard = index >= cards.length - 1

  async function finish() {
    setBusy(true)
    try {
      await service.completeStep(slide)
      onAdvance()
    } finally {
      setBusy(false)
    }
  }

  function next() {
    if (isLastCard) {
      void finish()
      return
    }
    setIndex((i) => i + 1)
    setFlipped(false)
  }

  if (cards.length === 0 || !card) {
    return (
      <>
        <SlideBody chip={{ icon: '🎴', label: 'Practice' }}>
          <div className="rounded-2xl border border-gold-500/20 bg-surface p-10 text-center text-sm text-muted">No cards in this deck yet.</div>
        </SlideBody>
        <ActionBar>
          <Button3D variant="reward" size="lg" fullWidth disabled={busy} onClick={finish}>Continue</Button3D>
        </ActionBar>
      </>
    )
  }

  return (
    <>
      <SlideBody chip={{ icon: '🎴', label: `Flashcards · ${index + 1}/${cards.length}` }}>
        <button
          type="button"
          onClick={() => setFlipped((f) => !f)}
          className="flex min-h-[12rem] w-full flex-col items-center justify-center gap-3 rounded-3xl border-2 border-gold-500/30 bg-surface p-8 text-center shadow-[0_6px_0_rgba(0,0,0,0.35)] transition-transform active:translate-y-[3px]"
        >
          <span className="text-xs font-bold uppercase tracking-[0.18em] text-gold-300">{flipped ? 'Meaning' : 'Word'}</span>
          <span className="font-display text-3xl font-bold text-foreground">{flipped ? card.back : card.front}</span>
          {flipped && card.mnemonic && <span className="text-sm text-muted">💡 {card.mnemonic}</span>}
          {!flipped && <span className="text-xs text-muted">Tap to flip</span>}
        </button>

        {card.audio && (
          <button
            type="button"
            onClick={() => {
              const a = audioRef.current
              if (a) {
                a.currentTime = 0
                void a.play()
              }
            }}
            className="inline-flex items-center gap-2 self-center rounded-full border border-gold-500/40 bg-gold-500/10 px-4 py-2 text-sm font-bold text-gold-200 hover:bg-gold-500/20"
          >
            <span aria-hidden="true">🔊</span> Hear it
            <audio ref={audioRef} src={card.audio} preload="auto" />
          </button>
        )}
      </SlideBody>
      <ActionBar>
        <Button3D variant="reward" size="lg" fullWidth disabled={busy} onClick={next}>
          {busy ? 'Saving…' : isLastCard ? 'Finish' : 'Next card'}
        </Button3D>
      </ActionBar>
    </>
  )
}

// ---- Game (memory match) ----

interface Tile {
  key: string
  pairId: number
  text: string
}

function shuffleTiles(tiles: Tile[]): Tile[] {
  const a = [...tiles]
  for (let i = a.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1))
    ;[a[i], a[j]] = [a[j], a[i]]
  }
  return a
}

function GameSlideView({ slide, service, onAdvance }: SlideProps & { slide: GameSlide }) {
  const [busy, setBusy] = useState(false)
  const playable = slide.pairs.length > 0

  const tiles = useMemo<Tile[]>(() => {
    if (!playable) return []
    const built: Tile[] = []
    slide.pairs.forEach((p, i) => {
      built.push({ key: `${i}a`, pairId: i, text: p.a })
      built.push({ key: `${i}b`, pairId: i, text: p.b })
    })
    return shuffleTiles(built)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [slide.id, playable])

  const [matched, setMatched] = useState<Set<number>>(new Set())
  const [picks, setPicks] = useState<string[]>([])
  const [locked, setLocked] = useState(false)

  const done = playable && matched.size === slide.pairs.length

  function tap(tile: Tile) {
    if (locked || matched.has(tile.pairId) || picks.includes(tile.key) || picks.length === 2) return
    const next = [...picks, tile.key]
    setPicks(next)
    if (next.length === 2) {
      const t1 = tiles.find((x) => x.key === next[0])
      const t2 = tiles.find((x) => x.key === next[1])
      if (t1 && t2 && t1.pairId === t2.pairId) {
        setMatched((m) => new Set(m).add(t1.pairId))
        setPicks([])
      } else {
        setLocked(true)
        window.setTimeout(() => {
          setPicks([])
          setLocked(false)
        }, 800)
      }
    }
  }

  async function finish() {
    setBusy(true)
    try {
      await service.completeStep(slide)
      onAdvance()
    } finally {
      setBusy(false)
    }
  }

  if (!playable) {
    return (
      <>
        <SlideBody chip={{ icon: '🎮', label: 'Game' }}>
          <div className="rounded-2xl border border-gold-500/20 bg-surface p-10 text-center">
            <span className="text-4xl" aria-hidden="true">🎮</span>
            <p className="mt-2 text-sm text-muted">A quick warm-up — continue when you’re ready.</p>
          </div>
        </SlideBody>
        <ActionBar>
          <Button3D variant="reward" size="lg" fullWidth disabled={busy} onClick={finish}>Continue</Button3D>
        </ActionBar>
      </>
    )
  }

  return (
    <>
      <SlideBody chip={{ icon: '🎮', label: 'Match the pairs' }}>
        <p className="text-sm text-muted">Tap two tiles that go together.</p>
        <div className="grid grid-cols-3 gap-2 sm:gap-3">
          {tiles.map((tile) => {
            const isMatched = matched.has(tile.pairId)
            const isUp = isMatched || picks.includes(tile.key)
            return (
              <button
                key={tile.key}
                type="button"
                disabled={isMatched || locked}
                onClick={() => tap(tile)}
                className={cn(
                  'flex min-h-[4.5rem] items-center justify-center rounded-2xl border-2 p-2 text-center text-sm font-semibold transition-all',
                  isMatched && 'border-leaf-400 bg-leaf-500/15 text-leaf-100 opacity-70',
                  !isMatched && isUp && 'border-gold-400 bg-gold-500/12 text-foreground',
                  !isMatched && !isUp && 'border-border bg-surface text-gold-500/40 shadow-[0_4px_0_rgba(0,0,0,0.35)] hover:border-gold-500/50',
                )}
              >
                {isUp ? tile.text : '◆'}
              </button>
            )
          })}
        </div>
      </SlideBody>
      <ActionBar tone={done ? 'correct' : 'neutral'}>
        {done ? (
          <>
            <span className="flex size-12 shrink-0 items-center justify-center rounded-full bg-gold-400 text-2xl font-bold text-charcoal-900 ring-2 ring-gold-200/70">✓</span>
            <p className="min-w-0 flex-1 font-display text-lg font-bold text-foreground">All matched!</p>
            <Button3D variant="reward" onClick={finish} disabled={busy}>
              {busy ? 'Saving…' : 'Continue'}
            </Button3D>
          </>
        ) : (
          <p className="w-full text-center text-sm text-muted">{matched.size}/{slide.pairs.length} pairs found</p>
        )}
      </ActionBar>
    </>
  )
}

// ---- Generic / unsupported ----

function GenericSlideView({ slide, service, onAdvance }: SlideProps & { slide: GenericSlide }) {
  const [busy, setBusy] = useState(false)

  async function done() {
    setBusy(true)
    try {
      await service.completeStep(slide)
      onAdvance()
    } finally {
      setBusy(false)
    }
  }

  return (
    <>
      <SlideBody chip={{ icon: '✦', label: slide.activity }}>
        <div className="rounded-2xl border border-gold-500/20 bg-surface p-10 text-center">
          <span className="text-4xl" aria-hidden="true">🎮</span>
          <p className="mt-2 text-sm text-muted">This {slide.activity} activity is interactive in the full app.</p>
        </div>
      </SlideBody>
      <ActionBar>
        <Button3D variant="reward" size="lg" fullWidth disabled={busy} onClick={done}>
          {busy ? 'Saving…' : 'Continue'}
        </Button3D>
      </ActionBar>
    </>
  )
}
