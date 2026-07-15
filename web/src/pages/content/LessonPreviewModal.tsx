import { Modal } from '@/components/ui'
import { cn } from '@/lib/cn'
import { youtubeEmbedUrl } from '@/components/learning/player'
import type { AuthorComponent, AuthorLesson } from '@/lib/api'

export const TYPE_ICON: Record<string, string> = { video: '🎬', quiz: '❓', speaking: '🎙️', exercise: '🎯', game: '🎮', assignment: '📝' }

interface QuizOption { id: number; label: string; is_correct: boolean }
interface QuizQ { id: number; type: string; prompt: string; target_text: string | null; options: QuizOption[] }

/**
 * Read-only learner-style preview of a lesson for content authors. Uses the
 * authoring detail (full data, incl. correct answers) so reviewers can sanity-
 * check the lesson before publishing.
 */
export function LessonPreviewModal({ lesson, open, onClose }: { lesson: AuthorLesson; open: boolean; onClose: () => void }) {
  const components = lesson.components ?? []

  return (
    <Modal open={open} onClose={onClose} title={`Preview · ${lesson.title}`} description="How this lesson appears to a learner (answers shown for review)." className="max-w-2xl">
      {components.length === 0 ? (
        <p className="py-6 text-center text-sm text-muted">No steps to preview yet.</p>
      ) : (
        <div className="flex max-h-[70vh] flex-col gap-5 overflow-y-auto pr-1">
          {components.map((c) => (
            <section key={c.id} className="flex flex-col gap-2">
              <span className="inline-flex w-fit items-center gap-1.5 rounded-full bg-primary-soft px-3 py-1 text-xs font-bold uppercase tracking-wide text-primary">
                <span aria-hidden="true">{TYPE_ICON[c.type] ?? '•'}</span> {c.type}
              </span>
              <StepPreview component={c} />
            </section>
          ))}
        </div>
      )}
    </Modal>
  )
}

export function StepPreview({ component }: { component: AuthorComponent }) {
  const d = (component.detail ?? {}) as Record<string, unknown>

  if (component.type === 'video') {
    const src = (d.src as string | null) ?? null
    const embedUrl = d.source_type === 'youtube' ? youtubeEmbedUrl((d.external_url as string | null) ?? null) : null

    if (embedUrl) {
      return (
        <iframe
          src={embedUrl}
          title="Video preview"
          allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
          allowFullScreen
          className="aspect-video w-full rounded-xl bg-charcoal-900"
        />
      )
    }

    return src ? (
      <video src={src} controls className="aspect-video w-full rounded-xl bg-charcoal-900" />
    ) : (
      <div className="flex aspect-video items-center justify-center rounded-xl border border-border bg-charcoal-900 text-sm text-white/70">
        No video file attached
      </div>
    )
  }

  if (component.type === 'speaking') {
    return (
      <div className="rounded-xl border border-border bg-surface p-4 text-center">
        <p className="text-sm text-muted">Say out loud:</p>
        <p className="mt-1 font-display text-xl font-bold text-foreground">{(d.target_text as string) || (d.prompt_text as string) || '—'}</p>
        {d.prompt_text ? <p className="mt-1 text-sm text-muted">{d.prompt_text as string}</p> : null}
      </div>
    )
  }

  if (component.type === 'quiz') {
    const questions = (d.questions as QuizQ[] | undefined) ?? []
    return (
      <div className="flex flex-col gap-3">
        {questions.map((q, i) => (
          <div key={q.id} className="rounded-xl border border-border bg-surface p-3">
            <p className="font-semibold text-foreground">{i + 1}. {q.prompt}</p>
            {q.options.length > 0 ? (
              <ul className="mt-2 flex flex-col gap-1.5">
                {q.options.map((o) => (
                  <li
                    key={o.id}
                    className={cn(
                      'flex items-center justify-between rounded-lg border px-3 py-1.5 text-sm',
                      o.is_correct ? 'border-leaf-400 bg-leaf-50 text-leaf-700' : 'border-border text-foreground',
                    )}
                  >
                    {o.label}
                    {o.is_correct && <span aria-hidden="true">✓</span>}
                  </li>
                ))}
              </ul>
            ) : (
              <p className="mt-2 text-sm text-muted">Expected answer: <span className="font-medium text-foreground">{q.target_text || '—'}</span></p>
            )}
          </div>
        ))}
        {questions.length === 0 && <p className="text-sm text-muted">No questions yet.</p>}
      </div>
    )
  }

  return <p className="text-sm text-muted">A {component.type} activity.</p>
}
