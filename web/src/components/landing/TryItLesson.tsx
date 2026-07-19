import { useEffect, useMemo, useState } from 'react'
import { Icon, LinkButton } from '@/components/ui'
import { cn } from '@/lib/cn'
import type { LandingLanguage } from './languages'

/**
 * A real, playable three-round lesson on the landing page — the thing no
 * competitor in this category offers (their most interactive element is a
 * language picker). It proves the product in about fifteen seconds and asks
 * for nothing: no email, no signup.
 *
 * Deliberate echoes of the real player (components/learning/player) so this
 * previews the actual product rather than inventing a parallel design:
 *   - segmented progress, one beat per round, not a continuous bar
 *   - large, tactile option tiles rather than tiny radio controls
 *   - encouraging-only feedback (BR-5: never "wrong"/"fail")
 *   - green reserved strictly as the correctness signal, orange for momentum
 *
 * Grading is client-side here purely because this is marketing content with no
 * score to protect. The real lesson flow grades server-side (BR-5) and never
 * ships answers to the client.
 */
export function TryItLesson({ language }: { language: LandingLanguage }) {
  const rounds = language.quiz
  const [index, setIndex] = useState(0)
  const [picked, setPicked] = useState<number | null>(null)
  const [correctCount, setCorrectCount] = useState(0)

  // Switching language restarts the lesson — stale progress from another
  // language's rounds would be meaningless.
  useEffect(() => {
    setIndex(0)
    setPicked(null)
    setCorrectCount(0)
  }, [language.code])

  const round = rounds[index]
  const done = index >= rounds.length
  const answered = picked !== null
  const isRight = answered && picked === round?.answer

  const beads = useMemo(
    () => rounds.map((_, i) => (i < index ? 'done' : i === index ? 'current' : 'todo')),
    [rounds, index],
  )

  function choose(i: number) {
    if (answered) return
    setPicked(i)
    if (i === rounds[index].answer) setCorrectCount((c) => c + 1)
  }

  function next() {
    setPicked(null)
    setIndex((i) => i + 1)
  }

  function restart() {
    setIndex(0)
    setPicked(null)
    setCorrectCount(0)
  }

  return (
    <div className="landing-lesson-stage rounded-[1rem] p-6 shadow-xl sm:p-8">
      {/* Segmented progress — one beat per round, mirroring the player. */}
      <div className="flex items-center gap-2" aria-hidden="true">
        {beads.map((state, i) => (
          <span
            key={i}
            className={cn(
              'h-1.5 flex-1 rounded-full transition-colors duration-300',
              state === 'done' && 'bg-rainbow-orange',
              state === 'current' && 'bg-rainbow-orange/55',
              state === 'todo' && 'bg-white/15',
            )}
          />
        ))}
      </div>

      {!done ? (
        <div key={index} className="animate-step-in">
          <p className="mt-6 text-xs font-bold uppercase tracking-wide text-chore-200">
            {language.name} · Round {index + 1} of {rounds.length}
          </p>
          <h3 className="mt-2 font-display text-2xl font-bold text-ivory-50 sm:text-3xl">
            How do you say <span className="text-[#ffb277]">“{round.prompt}”</span>?
          </h3>

          <ul className="mt-6 flex flex-col gap-3">
            {round.options.map((option, i) => {
              const isAnswer = i === round.answer
              const isPicked = i === picked
              return (
                <li key={option}>
                  <button
                    type="button"
                    onClick={() => choose(i)}
                    disabled={answered}
                    aria-label={`${option}${answered && isAnswer ? ' — correct answer' : ''}`}
                    className={cn(
                      'group flex min-h-14 w-full items-center gap-3 rounded-xl border px-4 py-3.5 text-left transition-all',
                      'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-chore-300 focus-visible:ring-offset-2 focus-visible:ring-offset-navy-950',
                      !answered &&
                        'border-chore-300/25 bg-white/8 hover:border-chore-200/70 hover:bg-white/12',
                      // Green is the universal correctness signal.
                      answered && isAnswer && 'border-leaf-400 bg-leaf-700/25',
                      answered && isPicked && !isAnswer && 'border-clay-400 bg-clay-700/20',
                      answered && !isPicked && !isAnswer && 'border-white/10 opacity-50',
                    )}
                  >
                    <span
                      aria-hidden="true"
                      className={cn(
                        'flex size-8 shrink-0 items-center justify-center rounded-lg border text-xs font-bold transition-colors',
                        !answered && 'border-chore-300/50 text-chore-200',
                        answered && isAnswer && 'border-leaf-300 bg-leaf-500 text-white',
                        answered && isPicked && !isAnswer && 'border-clay-300 text-clay-200',
                        answered && !isPicked && !isAnswer && 'border-white/15 text-white/45',
                      )}
                    >
                      <span>
                        {answered && isAnswer ? '✓' : String.fromCharCode(65 + i)}
                      </span>
                    </span>
                    <span className="font-display text-lg font-semibold text-ivory-50">
                      {option}
                    </span>
                  </button>
                </li>
              )
            })}
          </ul>

          {answered && (
            <div className="mt-5 animate-step-in rounded-xl bg-black/20 p-4">
              {/* BR-5: encouraging feedback only — "almost", never "wrong". */}
              <p
                className={cn(
                  'font-display font-bold',
                  isRight ? 'text-leaf-300' : 'text-[#ffb277]',
                )}
              >
                {isRight ? 'Well done!' : 'Almost — here it is'}
              </p>
              <p className="mt-1 text-sm leading-relaxed text-navy-100">{round.note}</p>
              <button
                type="button"
                onClick={next}
                className="mt-4 inline-flex h-11 items-center gap-1.5 rounded-xl bg-rainbow-orange px-5 font-display font-bold text-white transition-colors hover:bg-[#d94d00] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-chore-200 focus-visible:ring-offset-2 focus-visible:ring-offset-navy-950"
              >
                {index + 1 === rounds.length ? 'See how you did' : 'Next word'}
                <Icon name="chevron" className="size-4 -rotate-90" />
              </button>
            </div>
          )}
        </div>
      ) : (
        <div className="animate-pop-in py-4 text-center">
          <span aria-hidden="true" className="text-5xl">
            🌟
          </span>
          <h3 className="mt-3 font-display text-2xl font-bold text-ivory-50">
            You just learned {correctCount === rounds.length ? 'all three' : `${correctCount} of ${rounds.length}`} —
            in {language.name}.
          </h3>
          <p className="mx-auto mt-2 max-w-sm text-sm text-navy-100">
            That took under a minute, and no sign-up. A real lesson adds video from native
            speakers, speaking practice and the culture behind the words.
          </p>
          <div className="mt-6 flex flex-col items-center justify-center gap-3 sm:flex-row">
            <LinkButton to="/register" size="lg" variant="parent">
              Start learning free
            </LinkButton>
            <button
              type="button"
              onClick={restart}
              className="inline-flex h-12 items-center rounded-xl px-5 font-display font-semibold text-chore-100 transition-colors hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-chore-200 focus-visible:ring-offset-2 focus-visible:ring-offset-navy-950"
            >
              Try again
            </button>
          </div>
        </div>
      )}
    </div>
  )
}
