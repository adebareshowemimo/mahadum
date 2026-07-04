import { useRef, useState, type ChangeEvent, type FormEvent, type ReactNode } from 'react'
import { Link, useParams } from 'react-router-dom'
import {
  Alert,
  Badge,
  Button,
  Card,
  CardBody,
  Icon,
  Input,
  Modal,
  Skeleton,
} from '@/components/ui'
import { ApiError, contentApi, type AddComponentInput, type AuthorComponent, type AuthorQuestionInput, type QuestionType } from '@/lib/api'
import { cn } from '@/lib/cn'
import {
  useAddComponent,
  useDeleteComponent,
  useLessonAnalytics,
  useLessonDetail,
  useMediaAssets,
  usePublishLesson,
  useUpdateComponent,
} from '@/lib/content/queries'
import { useCanManageContent } from '@/lib/content/permissions'
import { LessonPreviewModal } from './LessonPreviewModal'

const TYPE_ICON: Record<string, string> = { video: '🎬', quiz: '❓', speaking: '🎙️', exercise: '🎯', game: '🎮', assignment: '📝' }

function componentSummary(c: AuthorComponent): string {
  const d = (c.detail ?? {}) as Record<string, unknown>
  if (c.type === 'video') return (d.title as string) ?? 'Video'
  if (c.type === 'quiz') {
    const questions = (d.questions as unknown[] | undefined)?.length ?? 0
    return `${questions} question${questions === 1 ? '' : 's'}`
  }
  if (c.type === 'speaking') return (d.prompt as string) ?? (d.prompt_text as string) ?? 'Speaking prompt'
  if (c.type === 'assignment') return (d.prompt as string) ?? 'Assignment'
  if (c.type === 'exercise') {
    const cards = (d.cards as unknown[] | undefined)?.length ?? 0
    return `${cards} card${cards === 1 ? '' : 's'}`
  }
  if (c.type === 'game') return `${(d.game_type as string) ?? 'memory'} game`
  return c.type
}

export function LessonBuilderPage() {
  const { courseId, lessonId } = useParams()
  const id = Number(lessonId)
  const lesson = useLessonDetail(id)
  const publish = usePublishLesson(id)
  const canManage = useCanManageContent()
  const [modal, setModal] = useState<{ type: 'video' | 'quiz' | 'exercise' | 'game'; component?: AuthorComponent } | null>(null)
  const [deleting, setDeleting] = useState<AuthorComponent | null>(null)
  const [previewOpen, setPreviewOpen] = useState(false)
  const [insightsOpen, setInsightsOpen] = useState(false)
  const [publishErrors, setPublishErrors] = useState<string[] | null>(null)

  async function onPublish() {
    setPublishErrors(null)
    try {
      await publish.mutateAsync()
    } catch (err) {
      if (err instanceof ApiError && Array.isArray(err.details)) setPublishErrors(err.details as string[])
      else setPublishErrors([err instanceof ApiError ? err.message : 'Publish failed.'])
    }
  }

  if (lesson.isLoading) return <Skeleton className="h-64" />
  if (lesson.isError || !lesson.data) return <Alert variant="danger">Couldn’t load this lesson.</Alert>

  const components = lesson.data.components ?? []

  return (
    <div className="flex flex-col gap-6">
      <div>
        <Link to={`/courses/${courseId}`} className="text-sm font-medium text-primary hover:underline">
          ← Course
        </Link>
        <div className="mt-2 flex flex-wrap items-center justify-between gap-3">
          <h1 className="font-display text-2xl font-bold text-foreground">{lesson.data.title}</h1>
          <div className="flex items-center gap-2">
            <Badge variant={lesson.data.is_published ? 'success' : 'neutral'}>
              {lesson.data.is_published ? 'Published' : 'Draft'}
            </Badge>
            <Button variant="secondary" onClick={() => setPreviewOpen(true)}>
              Preview
            </Button>
            {canManage && (
              <Button variant="secondary" onClick={() => setInsightsOpen(true)}>
                Insights
              </Button>
            )}
            {canManage && (
              <Button variant="primary" loading={publish.isPending} onClick={onPublish}>
                {lesson.data.is_published ? 'Re-publish' : 'Publish'}
              </Button>
            )}
          </div>
        </div>
      </div>

      {publishErrors && (
        <Alert variant="warning" title="Can’t publish yet">
          <ul className="mt-1 list-disc pl-5 text-sm">
            {publishErrors.map((f, i) => (
              <li key={i}>{f}</li>
            ))}
          </ul>
        </Alert>
      )}
      {publish.isSuccess && !publishErrors && <Alert variant="success">Lesson published. 🎉</Alert>}

      {canManage && (
        <div className="flex flex-wrap gap-2">
          {/* speaking + assignment (learner recording + review) are deferred to v2. */}
          <Button variant="secondary" size="sm" onClick={() => setModal({ type: 'video' })}>🎬 Add video</Button>
          <Button variant="secondary" size="sm" onClick={() => setModal({ type: 'quiz' })}>❓ Add quiz</Button>
          <Button variant="secondary" size="sm" onClick={() => setModal({ type: 'exercise' })}>🎴 Add flashcards</Button>
          <Button variant="secondary" size="sm" onClick={() => setModal({ type: 'game' })}>🎮 Add game</Button>
        </div>
      )}

      <section>
        <h2 className="mb-3 font-display text-lg font-bold text-foreground">Steps</h2>
        {components.length === 0 ? (
          <Card>
            <CardBody className="py-8 text-center text-sm text-muted">
              No steps yet. A publishable lesson needs at least one video and one quiz.
            </CardBody>
          </Card>
        ) : (
          <ol className="flex flex-col gap-2">
            {components.map((c) => {
              const editable = ['video', 'quiz', 'exercise', 'game'].includes(c.type)
              return (
                <li key={c.id}>
                  <Card>
                    <CardBody className="flex items-center gap-3 py-3">
                      <span className="text-2xl" aria-hidden="true">{TYPE_ICON[c.type] ?? '•'}</span>
                      <span className="min-w-0 flex-1">
                        <span className="block font-semibold capitalize text-foreground">
                          {c.position}. {c.type}
                          {c.is_required && <span className="ml-2 text-xs font-normal text-subtle">required</span>}
                        </span>
                        <span className="block truncate text-sm text-muted">{componentSummary(c)}</span>
                      </span>
                      {c.xp_value > 0 && <span className="hidden text-sm text-muted sm:inline">{c.xp_value} XP</span>}
                      {canManage && (
                        <span className="flex items-center gap-1">
                          {editable && (
                            <Button
                              variant="ghost"
                              size="sm"
                              onClick={() => setModal({ type: c.type as 'video' | 'quiz' | 'exercise' | 'game', component: c })}
                            >
                              Edit
                            </Button>
                          )}
                          <Button
                            variant="ghost"
                            size="sm"
                            className="text-danger"
                            onClick={() => setDeleting(c)}
                          >
                            Delete
                          </Button>
                        </span>
                      )}
                    </CardBody>
                  </Card>
                </li>
              )
            })}
          </ol>
        )}
      </section>

      <LessonPreviewModal lesson={lesson.data} open={previewOpen} onClose={() => setPreviewOpen(false)} />
      <LessonAnalyticsModal lessonId={id} open={insightsOpen} onClose={() => setInsightsOpen(false)} />
      {modal?.type === 'video' && (
        <AddVideoModal key={modal.component?.id ?? 'new'} lessonId={id} editing={modal.component} onClose={() => setModal(null)} />
      )}
      {modal?.type === 'quiz' && (
        <QuizBuilderModal key={modal.component?.id ?? 'new'} lessonId={id} editing={modal.component} onClose={() => setModal(null)} />
      )}
      {modal?.type === 'exercise' && (
        <AddExerciseModal key={modal.component?.id ?? 'new'} lessonId={id} editing={modal.component} onClose={() => setModal(null)} />
      )}
      {modal?.type === 'game' && (
        <AddGameModal key={modal.component?.id ?? 'new'} lessonId={id} editing={modal.component} onClose={() => setModal(null)} />
      )}
      <DeleteStepModal lessonId={id} component={deleting} onClose={() => setDeleting(null)} />
    </div>
  )
}

const pct = (a: number, b: number) => (b > 0 ? Math.round((a / b) * 100) : 0)

function Bar({ value }: { value: number }) {
  return (
    <div className="h-2 w-full overflow-hidden rounded-full bg-surface-muted">
      <div className="h-full rounded-full bg-primary" style={{ width: `${Math.round(Math.max(0, Math.min(1, value)) * 100)}%` }} />
    </div>
  )
}

function Stat({ label, value }: { label: string; value: ReactNode }) {
  return (
    <div className="rounded-2xl border border-border bg-surface p-4">
      <p className="text-xs font-semibold uppercase tracking-wide text-muted">{label}</p>
      <p className="mt-1 font-display text-2xl font-bold text-foreground">{value}</p>
    </div>
  )
}

/** Drop-off funnel + per-question accuracy for a lesson (content owners). */
function LessonAnalyticsModal({ lessonId, open, onClose }: { lessonId: number; open: boolean; onClose: () => void }) {
  const { data, isLoading, isError } = useLessonAnalytics(lessonId, open)

  return (
    <Modal open={open} onClose={onClose} title="Lesson insights" description="Where learners drop off and which questions trip them up." className="max-w-2xl">
      {isLoading ? (
        <Skeleton className="h-48" />
      ) : isError || !data ? (
        <Alert variant="danger">Couldn’t load insights.</Alert>
      ) : data.learners_started === 0 ? (
        <p className="py-8 text-center text-sm text-muted">No learner activity yet — insights appear once learners start this lesson.</p>
      ) : (
        <div className="flex flex-col gap-6">
          <div className="grid grid-cols-2 gap-3">
            <Stat label="Learners started" value={data.learners_started} />
            <Stat label="Completed" value={`${data.learners_completed} · ${pct(data.learners_completed, data.learners_started)}%`} />
          </div>

          <section className="flex flex-col gap-2.5">
            <h3 className="text-sm font-bold text-foreground">Step funnel</h3>
            {data.funnel.map((f) => (
              <div key={f.component_id} className="flex flex-col gap-1">
                <div className="flex items-center justify-between text-sm">
                  <span className="truncate text-foreground">{f.position}. {f.title || f.type}</span>
                  <span className="shrink-0 pl-2 text-muted">{f.completed}/{f.reached} done</span>
                </div>
                <Bar value={f.reached ? f.completed / f.reached : 0} />
              </div>
            ))}
          </section>

          {data.questions.length > 0 && (
            <section className="flex flex-col gap-2.5">
              <h3 className="text-sm font-bold text-foreground">Question accuracy</h3>
              {data.questions.map((q) => (
                <div key={q.question_id} className="flex flex-col gap-1">
                  <div className="flex items-center justify-between gap-3 text-sm">
                    <span className="min-w-0 flex-1 truncate text-foreground">{q.prompt}</span>
                    <span className="shrink-0 text-muted">{q.accuracy == null ? '—' : `${Math.round(q.accuracy * 100)}%`} · {q.answered} ans</span>
                  </div>
                  <Bar value={q.accuracy ?? 0} />
                </div>
              ))}
            </section>
          )}
        </div>
      )}
    </Modal>
  )
}

function DeleteStepModal({
  lessonId,
  component,
  onClose,
}: {
  lessonId: number
  component: AuthorComponent | null
  onClose: () => void
}) {
  const del = useDeleteComponent(lessonId)
  const [error, setError] = useState<string | null>(null)

  async function onConfirm() {
    if (!component) return
    setError(null)
    try {
      await del.mutateAsync(component.id)
      onClose()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not delete this step.')
    }
  }

  return (
    <Modal
      open={component != null}
      onClose={onClose}
      title="Delete step?"
      description={component ? `This removes the ${component.type} step and its content. This can’t be undone.` : ''}
    >
      <div className="flex flex-col gap-4">
        {error && <Alert variant="danger">{error}</Alert>}
        <div className="flex gap-2">
          <Button type="button" variant="secondary" fullWidth onClick={onClose}>Cancel</Button>
          <Button type="button" variant="danger" fullWidth loading={del.isPending} onClick={onConfirm}>
            Delete step
          </Button>
        </div>
      </div>
    </Modal>
  )
}

/** Add or edit a component depending on whether `editing` is supplied. */
function useComponentForm(lessonId: number, editing: AuthorComponent | undefined, onClose: () => void) {
  const add = useAddComponent(lessonId)
  const update = useUpdateComponent(lessonId)
  const [error, setError] = useState<string | null>(null)
  async function submit(payload: AddComponentInput) {
    setError(null)
    try {
      if (editing) await update.mutateAsync({ componentId: editing.id, input: payload })
      else await add.mutateAsync(payload)
      onClose()
      return true
    } catch (err) {
      setError(err instanceof ApiError ? err.message : `Could not ${editing ? 'save' : 'add'} the step.`)
      return false
    }
  }
  return { submit, error, pending: add.isPending || update.isPending, isEdit: !!editing }
}

function AddVideoModal({ lessonId, editing, onClose }: { lessonId: number; editing?: AuthorComponent; onClose: () => void }) {
  const { submit, error, pending, isEdit } = useComponentForm(lessonId, editing, onClose)
  const library = useMediaAssets({ type: 'video', per_page: 100 })
  const d = (editing?.detail ?? {}) as Record<string, unknown>
  const [source, setSource] = useState<'upload' | 'library'>('upload')
  const [title, setTitle] = useState((d.title as string) ?? '')
  const [presenter, setPresenter] = useState((d.presenter_name as string) ?? '')
  const [quality, setQuality] = useState<'240p' | '360p' | '720p'>(((d.default_quality as string) ?? '360p') as '240p' | '360p' | '720p')
  // Default new videos to "must watch"; respect the saved value when editing.
  const [requireWatch, setRequireWatch] = useState<boolean>(
    editing ? !!editing.settings?.require_watch : true,
  )
  const [file, setFile] = useState<File | null>(null)
  const [pickedAssetId, setPickedAssetId] = useState<number | null>(null)
  const [uploading, setUploading] = useState(false)
  const [uploadError, setUploadError] = useState<string | null>(null)

  const videoAssets = (library.data ?? []).filter((a) => a.type === 'video')

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setUploadError(null)
    let sourceAssetId: number | undefined
    if (source === 'library') {
      sourceAssetId = pickedAssetId ?? undefined
    } else if (file) {
      setUploading(true)
      try {
        const asset = await contentApi.uploadMedia(file)
        sourceAssetId = asset.id
      } catch (err) {
        setUploadError(err instanceof ApiError ? err.message : 'Upload failed.')
        setUploading(false)
        return
      }
      setUploading(false)
    }
    const ok = await submit({
      type: 'video',
      xp_value: 5,
      settings: { require_watch: requireWatch },
      video: { title, presenter_name: presenter || undefined, default_quality: quality, status: 'ready', source_asset_id: sourceAssetId },
    })
    if (ok) { setTitle(''); setPresenter(''); setFile(null); setPickedAssetId(null); setSource('upload') }
  }

  return (
    <Modal
      open
      onClose={onClose}
      title={isEdit ? 'Edit video' : 'Add video'}
      description={isEdit ? 'Update the details, or attach a new file to replace the current one.' : 'Upload a file or reuse one from your media library.'}
    >
      <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
        {error && <Alert variant="danger">{error}</Alert>}
        {uploadError && <Alert variant="danger">{uploadError}</Alert>}
        {isEdit && (
          <p className="rounded-xl bg-surface-muted px-3 py-2 text-xs text-muted">
            The current video stays unless you choose a new file or library item.
          </p>
        )}

        <div className="flex rounded-xl bg-surface-muted p-1 text-sm font-semibold">
          {(['upload', 'library'] as const).map((opt) => (
            <button
              key={opt}
              type="button"
              onClick={() => setSource(opt)}
              className={cn('flex-1 rounded-lg py-1.5 capitalize transition-colors', source === opt ? 'bg-surface text-foreground shadow-sm' : 'text-muted')}
            >
              {opt === 'upload' ? 'Upload new' : 'From library'}
            </button>
          ))}
        </div>

        {source === 'upload' ? (
          <label className="flex cursor-pointer flex-col items-center gap-2 rounded-2xl border-2 border-dashed border-border-strong p-6 text-center hover:bg-surface-muted">
            <span className="text-3xl" aria-hidden="true">🎬</span>
            <span className="text-sm font-medium text-foreground">{file ? file.name : 'Choose a video file'}</span>
            <span className="text-xs text-subtle">MP4 / WebM, up to 100 MB</span>
            <input
              type="file"
              accept="video/*"
              className="hidden"
              onChange={(e) => {
                const f = e.target.files?.[0] ?? null
                setFile(f)
                if (f && !title) setTitle(f.name.replace(/\.[^.]+$/, ''))
              }}
            />
          </label>
        ) : library.isLoading ? (
          <Skeleton className="h-24" />
        ) : videoAssets.length === 0 ? (
          <p className="rounded-xl border border-border p-4 text-center text-sm text-muted">
            No video assets yet — upload one here or in the Media library.
          </p>
        ) : (
          <div className="grid max-h-56 grid-cols-3 gap-2 overflow-y-auto">
            {videoAssets.map((a) => (
              <button
                key={a.id}
                type="button"
                onClick={() => setPickedAssetId(a.id)}
                className={cn('overflow-hidden rounded-xl border-2 transition-colors', pickedAssetId === a.id ? 'border-primary' : 'border-border')}
              >
                <video src={a.url} className="aspect-video w-full bg-charcoal-900" muted preload="metadata" />
                <span className="block px-1 py-0.5 text-[10px] text-muted">#{a.id}</span>
              </button>
            ))}
          </div>
        )}

        <Input label="Video title" value={title} onChange={(e) => setTitle(e.target.value)} required />
        <Input label="Presenter (optional)" value={presenter} onChange={(e) => setPresenter(e.target.value)} />
        <label className="flex flex-col gap-1.5">
          <span className="text-sm font-semibold text-foreground">Default quality</span>
          <select value={quality} onChange={(e) => setQuality(e.target.value as typeof quality)} className="h-11 rounded-xl border border-border-strong bg-surface px-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring">
            <option value="240p">240p</option>
            <option value="360p">360p</option>
            <option value="720p">720p</option>
          </select>
        </label>
        <label className="flex flex-col gap-1.5">
          <span className="text-sm font-semibold text-foreground">Completion rule</span>
          <select
            value={requireWatch ? 'required' : 'optional'}
            onChange={(e) => setRequireWatch(e.target.value === 'required')}
            className="h-11 rounded-xl border border-border-strong bg-surface px-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
          >
            <option value="required">Must watch to the end (Continue stays locked)</option>
            <option value="optional">Optional — learners can skip</option>
          </select>
        </label>
        <div className="flex gap-2">
          <Button type="button" variant="secondary" fullWidth onClick={onClose}>Cancel</Button>
          <Button type="submit" fullWidth loading={uploading || pending} disabled={source === 'library' && pickedAssetId == null}>
            {uploading ? 'Uploading…' : isEdit ? 'Save changes' : 'Add video'}
          </Button>
        </div>
      </form>
    </Modal>
  )
}

// ---- Exercise (flashcards) builder ----

interface CardDraft { key: string; front_text: string; back_text: string; mnemonic: string; audio_asset_id: number | null; audio: string | null }

const newCard = (): CardDraft => ({ key: uid(), front_text: '', back_text: '', mnemonic: '', audio_asset_id: null, audio: null })

function cardsFromComponent(editing?: AuthorComponent): CardDraft[] {
  const cards = (editing?.detail?.cards as Array<Record<string, unknown>> | undefined) ?? []
  if (cards.length === 0) return [newCard()]
  return cards.map((c) => ({
    key: uid(),
    front_text: (c.front_text as string) ?? '',
    back_text: (c.back_text as string) ?? '',
    mnemonic: (c.mnemonic as string) ?? '',
    audio_asset_id: (c.audio_asset_id as number) ?? null,
    audio: (c.audio as string) ?? null,
  }))
}

function AddExerciseModal({ lessonId, editing, onClose }: { lessonId: number; editing?: AuthorComponent; onClose: () => void }) {
  const { submit, error, pending, isEdit } = useComponentForm(lessonId, editing, onClose)
  const audio = useMediaAssets({ type: 'audio', per_page: 100 })
  const audioAssets = (audio.data ?? []) as AudioAsset[]
  const [cards, setCards] = useState<CardDraft[]>(() => cardsFromComponent(editing))
  const [localError, setLocalError] = useState<string | null>(null)

  function patch(key: string, fn: (c: CardDraft) => CardDraft) {
    setCards((cs) => cs.map((c) => (c.key === key ? fn(c) : c)))
  }

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setLocalError(null)
    const ready = cards.filter((c) => c.front_text.trim() && c.back_text.trim())
    if (ready.length === 0) {
      setLocalError('Add at least one card with both sides filled.')
      return
    }
    const ok = await submit({
      type: 'exercise',
      xp_value: 4,
      exercise: {
        mode: 'flashcards',
        cards: ready.map((c) => ({
          front_text: c.front_text,
          back_text: c.back_text,
          mnemonic: c.mnemonic || undefined,
          audio_asset_id: c.audio_asset_id ?? undefined,
        })),
      },
    })
    if (ok && !isEdit) setCards([newCard()])
  }

  return (
    <Modal open onClose={onClose} title={isEdit ? 'Edit flashcards' : 'Add flashcards'} description="Word ↔ meaning cards the learner flips through." className="max-w-2xl">
      <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
        {error && <Alert variant="danger">{error}</Alert>}
        {localError && <Alert variant="danger">{localError}</Alert>}

        <div className="-mr-1 flex max-h-[55vh] flex-col gap-3 overflow-y-auto pr-1">
          {cards.map((c, i) => (
            <div key={c.key} className="rounded-2xl border border-border bg-surface p-3">
              <div className="mb-2 flex items-center justify-between">
                <span className="text-xs font-bold text-subtle">Card {i + 1}</span>
                {cards.length > 1 && (
                  <button type="button" className="text-xs text-danger" onClick={() => setCards((cs) => cs.filter((x) => x.key !== c.key))}>Remove</button>
                )}
              </div>
              <div className="grid gap-2 sm:grid-cols-2">
                <Input label="Front (word)" value={c.front_text} onChange={(e) => patch(c.key, (x) => ({ ...x, front_text: e.target.value }))} />
                <Input label="Back (meaning)" value={c.back_text} onChange={(e) => patch(c.key, (x) => ({ ...x, back_text: e.target.value }))} />
              </div>
              <Input label="Mnemonic (optional)" value={c.mnemonic} onChange={(e) => patch(c.key, (x) => ({ ...x, mnemonic: e.target.value }))} />
              <label className="mt-2 flex flex-col gap-1.5">
                <span className="text-sm font-semibold text-foreground">Audio (optional)</span>
                <select
                  value={c.audio_asset_id ?? ''}
                  onChange={(e) => {
                    const id = e.target.value ? Number(e.target.value) : null
                    const asset = audioAssets.find((a) => a.id === id)
                    patch(c.key, (x) => ({ ...x, audio_asset_id: id, audio: asset?.url ?? null }))
                  }}
                  className="h-10 rounded-xl border border-border-strong bg-surface px-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                >
                  <option value="">No audio</option>
                  {audioAssets.map((a) => (
                    <option key={a.id} value={a.id}>#{a.id} {a.original_name ?? a.url}</option>
                  ))}
                </select>
              </label>
            </div>
          ))}
        </div>

        <button type="button" className="self-start text-sm font-medium text-primary" onClick={() => setCards((cs) => [...cs, newCard()])}>
          + Add card
        </button>

        <div className="flex gap-2 border-t border-border pt-4">
          <Button type="button" variant="secondary" fullWidth onClick={onClose}>Cancel</Button>
          <Button type="submit" fullWidth loading={pending}>{isEdit ? 'Save changes' : 'Add flashcards'}</Button>
        </div>
      </form>
    </Modal>
  )
}

// ---- Game builder ----

interface PairDraft { key: string; a: string; b: string }

const newPair = (): PairDraft => ({ key: uid(), a: '', b: '' })

const GAME_TYPE_LABEL: Record<string, string> = {
  memory: 'Memory match',
  match: 'Match pairs',
  tone_pop: 'Tone pop',
  word_builder: 'Word builder',
}

function pairsFromComponent(editing?: AuthorComponent): PairDraft[] {
  const cfg = (editing?.detail?.config as { pairs?: { a: string; b: string }[] } | undefined) ?? {}
  const pairs = cfg.pairs ?? []
  if (pairs.length === 0) return [newPair(), newPair()]
  return pairs.map((p) => ({ key: uid(), a: p.a, b: p.b }))
}

function AddGameModal({ lessonId, editing, onClose }: { lessonId: number; editing?: AuthorComponent; onClose: () => void }) {
  const { submit, error, pending, isEdit } = useComponentForm(lessonId, editing, onClose)
  const [gameType, setGameType] = useState<'memory' | 'match' | 'tone_pop' | 'word_builder'>(
    ((editing?.detail?.game_type as string) ?? 'memory') as 'memory' | 'match' | 'tone_pop' | 'word_builder',
  )
  const [pairs, setPairs] = useState<PairDraft[]>(() => pairsFromComponent(editing))
  const [localError, setLocalError] = useState<string | null>(null)

  function patch(key: string, fn: (p: PairDraft) => PairDraft) {
    setPairs((ps) => ps.map((p) => (p.key === key ? fn(p) : p)))
  }

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setLocalError(null)
    const ready = pairs.filter((p) => p.a.trim() && p.b.trim())
    if (ready.length < 2) {
      setLocalError('Add at least two complete pairs.')
      return
    }
    const ok = await submit({
      type: 'game',
      xp_value: 4,
      game: { game_type: gameType, config: { pairs: ready.map((p) => ({ a: p.a, b: p.b })) } },
    })
    if (ok && !isEdit) setPairs([newPair(), newPair()])
  }

  return (
    <Modal open onClose={onClose} title={isEdit ? 'Edit game' : 'Add game'} description="A matching game built from your pairs." className="max-w-2xl">
      <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
        {error && <Alert variant="danger">{error}</Alert>}
        {localError && <Alert variant="danger">{localError}</Alert>}

        <label className="flex flex-col gap-1.5">
          <span className="text-sm font-semibold text-foreground">Game type</span>
          <select
            value={gameType}
            onChange={(e) => setGameType(e.target.value as typeof gameType)}
            className="h-11 rounded-xl border border-border-strong bg-surface px-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
          >
            {Object.entries(GAME_TYPE_LABEL).map(([v, label]) => (
              <option key={v} value={v}>{label}</option>
            ))}
          </select>
        </label>

        <div className="-mr-1 flex max-h-[45vh] flex-col gap-2 overflow-y-auto pr-1">
          <span className="text-sm font-semibold text-foreground">Pairs</span>
          {pairs.map((p) => (
            <div key={p.key} className="flex items-center gap-2">
              <input
                value={p.a}
                onChange={(e) => patch(p.key, (x) => ({ ...x, a: e.target.value }))}
                placeholder="Side A"
                className="h-10 flex-1 rounded-xl border border-border-strong bg-surface px-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
              />
              <span aria-hidden="true" className="text-muted">↔</span>
              <input
                value={p.b}
                onChange={(e) => patch(p.key, (x) => ({ ...x, b: e.target.value }))}
                placeholder="Side B"
                className="h-10 flex-1 rounded-xl border border-border-strong bg-surface px-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
              />
              {pairs.length > 2 && (
                <button type="button" className="text-xs text-danger" onClick={() => setPairs((ps) => ps.filter((x) => x.key !== p.key))}>✕</button>
              )}
            </div>
          ))}
        </div>

        <button type="button" className="self-start text-sm font-medium text-primary" onClick={() => setPairs((ps) => [...ps, newPair()])}>
          + Add pair
        </button>

        <div className="flex gap-2 border-t border-border pt-4">
          <Button type="button" variant="secondary" fullWidth onClick={onClose}>Cancel</Button>
          <Button type="submit" fullWidth loading={pending}>{isEdit ? 'Save changes' : 'Add game'}</Button>
        </div>
      </form>
    </Modal>
  )
}

// ---- Quiz builder ----

interface QOption { key: string; label: string; correct: boolean; match_target?: string }
interface QDraft {
  key: string
  type: QuestionType
  prompt: string
  target_text: string
  options: QOption[]
  promptAudioAssetId: number | null
  promptAudio: string | null
}

/** A lightweight audio-asset shape for the prompt picker. */
interface AudioAsset { id: number; url: string; original_name?: string | null }

const uid = () =>
  typeof crypto !== 'undefined' && 'randomUUID' in crypto ? crypto.randomUUID() : `k${Math.random().toString(36).slice(2)}`

// ---- Per-type predicates ----
const SINGLE_CORRECT: QuestionType[] = ['mcq_single', 'true_false', 'fill_blank', 'listen_and_respond', 'complete_the_chat']
const isMulti = (t: QuestionType) => t === 'mcq_multi'
const usesCorrectFlag = (t: QuestionType) => SINGLE_CORRECT.includes(t) || isMulti(t)
const isWordBank = (t: QuestionType) => t === 'word_bank'
const isMatch = (t: QuestionType) => t === 'match_pairs'
const isTextType = (t: QuestionType) => t === 'type_what_you_hear'
const needsAudio = (t: QuestionType) => t === 'listen_and_respond'
const usesOptions = (t: QuestionType) => usesCorrectFlag(t) || isWordBank(t) || isMatch(t)

const blankOption = (): QOption => ({ key: uid(), label: '', correct: false })
const pairOption = (): QOption => ({ key: uid(), label: '', correct: false, match_target: '' })

function newQuestion(): QDraft {
  return {
    key: uid(),
    type: 'mcq_single',
    prompt: '',
    target_text: '',
    promptAudioAssetId: null,
    promptAudio: null,
    options: [
      { key: uid(), label: '', correct: true },
      { key: uid(), label: '', correct: false },
    ],
  }
}

function draftsFromComponent(editing?: AuthorComponent): QDraft[] {
  const qs = (editing?.detail?.questions as Array<Record<string, unknown>> | undefined) ?? []
  if (qs.length === 0) return [newQuestion()]
  return qs.map((q) => ({
    key: uid(),
    type: (q.type as QuestionType) ?? 'mcq_single',
    prompt: (q.prompt as string) ?? '',
    target_text: (q.target_text as string) ?? '',
    promptAudioAssetId: (q.prompt_audio_asset_id as number) ?? null,
    promptAudio: (q.prompt_audio as string) ?? null,
    options: ((q.options as Array<{ label: string; is_correct: boolean; match_target?: string | null }>) ?? []).map((o) => ({
      key: uid(),
      label: o.label,
      correct: !!o.is_correct,
      match_target: o.match_target ?? '',
    })),
  }))
}

const QUESTION_TYPE_LABEL: Record<QuestionType, string> = {
  mcq_single: 'Multiple choice',
  mcq_multi: 'Multiple answer',
  true_false: 'True / False',
  fill_blank: 'Fill in the blank',
  listen_and_respond: 'Listen & respond',
  complete_the_chat: 'Complete the chat',
  word_bank: 'Word bank',
  match_pairs: 'Match pairs',
  type_what_you_hear: 'Type the answer',
}

/** A parsed import row → an editable draft (reverse of the save mapping). */
function draftFromImport(q: AuthorQuestionInput): QDraft {
  return {
    key: uid(),
    type: q.type,
    prompt: q.prompt,
    target_text: q.target_text ?? '',
    promptAudioAssetId: q.prompt_audio_asset_id ?? null,
    promptAudio: null,
    options: (q.options ?? []).map((o) => ({ key: uid(), label: o.label, correct: !!o.is_correct, match_target: o.match_target ?? '' })),
  }
}

const IMPORT_TEMPLATE = [
  'type,prompt,options,correct,explanation,points,prompt_audio_asset_id',
  'mcq_single,Capital of Nigeria?,Abuja|Lagos|Kano,Abuja,Abuja is the capital,1,',
  'mcq_multi,Which are greetings?,Ẹ n lẹ|Daalụ|Mba,1|2,,2,',
  'true_false,"""Nna"" means father",,True,,1,',
  'fill_blank,Good ___ (morning),morning|evening|night,morning,,1,',
  'type_what_you_hear,Type what you hear,,Ụtụtụ ọma,,1,',
  'listen_and_respond,Choose the correct reply,Ụtụtụ ọma|Ka chi fo,Ụtụtụ ọma,,1,',
  'match_pairs,Match word to meaning,Mama=Mother|Nna=Father,,,1,',
  'word_bank,Arrange the greeting,Ụtụtụ|ọma,,,1,',
].join('\n')

function downloadImportTemplate() {
  const blob = new Blob([IMPORT_TEMPLATE], { type: 'text/csv;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'quiz-import-template.csv'
  a.click()
  URL.revokeObjectURL(url)
}

/** A question is "ready" when it has a prompt and a resolvable answer. */
function isValidDraft(q: QDraft): boolean {
  if (!q.prompt.trim()) return false
  if (needsAudio(q.type) && !q.promptAudioAssetId) return false
  if (isTextType(q.type)) return q.target_text.trim().length > 0
  if (isMatch(q.type)) return q.options.length >= 2 && q.options.every((o) => o.label.trim() && (o.match_target ?? '').trim())
  if (isWordBank(q.type)) return q.options.length >= 2 && q.options.every((o) => o.label.trim())
  return q.options.length > 0 && q.options.every((o) => o.label.trim()) && q.options.some((o) => o.correct)
}

function QuizBuilderModal({ lessonId, editing, onClose }: { lessonId: number; editing?: AuthorComponent; onClose: () => void }) {
  const { submit, error, pending, isEdit } = useComponentForm(lessonId, editing, onClose)
  const audio = useMediaAssets({ type: 'audio', per_page: 100 })
  const audioAssets = (audio.data ?? []) as AudioAsset[]
  const [questions, setQuestions] = useState<QDraft[]>(() => draftsFromComponent(editing))
  // Accordion: only one question is expanded for editing at a time.
  const [openKey, setOpenKey] = useState<string | null>(() => questions[0]?.key ?? null)
  const [localError, setLocalError] = useState<string | null>(null)
  const [importing, setImporting] = useState(false)
  const [importErrors, setImportErrors] = useState<{ row: number; error: string }[] | null>(null)
  const fileRef = useRef<HTMLInputElement>(null)

  async function onImportFile(e: ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0]
    e.target.value = '' // let the same file be re-picked
    if (!file) return
    setImporting(true)
    setImportErrors(null)
    setLocalError(null)
    try {
      const res = await contentApi.parseQuizImport(file)
      if (res.questions.length > 0) {
        const drafts = res.questions.map(draftFromImport)
        setQuestions((qs) => {
          // Drop a single untouched starter question so imports don't leave a blank at the top.
          const seed = qs.length === 1 && !qs[0].prompt.trim() && qs[0].options.every((o) => !o.label.trim()) ? [] : qs
          return [...seed, ...drafts]
        })
        setOpenKey(null)
      }
      if (res.errors.length > 0) setImportErrors(res.errors)
      else if (res.questions.length === 0) setLocalError('No questions were found in that file.')
    } catch (err) {
      setLocalError(err instanceof ApiError ? err.message : 'Could not import that file.')
    } finally {
      setImporting(false)
    }
  }

  function patch(qKey: string, fn: (q: QDraft) => QDraft) {
    setQuestions((qs) => qs.map((q) => (q.key === qKey ? fn(q) : q)))
  }

  function setType(qKey: string, type: QuestionType) {
    patch(qKey, (q) => {
      if (type === 'true_false') {
        return { ...q, type, options: [{ key: uid(), label: 'True', correct: true }, { key: uid(), label: 'False', correct: false }] }
      }
      if (isTextType(type)) return { ...q, type }
      if (isMatch(type)) {
        return { ...q, type, options: q.options.length >= 2 ? q.options.map((o) => ({ ...o, correct: false, match_target: o.match_target ?? '' })) : [pairOption(), pairOption()] }
      }
      if (isWordBank(type)) {
        return { ...q, type, options: q.options.length >= 2 ? q.options.map((o) => ({ ...o, correct: false })) : [blankOption(), blankOption()] }
      }
      // mcq_single / mcq_multi / fill_blank / listen_and_respond / complete_the_chat
      return { ...q, type, options: q.options.length ? q.options : newQuestion().options }
    })
  }

  function move(qKey: string, dir: -1 | 1) {
    setQuestions((qs) => {
      const i = qs.findIndex((q) => q.key === qKey)
      const j = i + dir
      if (i < 0 || j < 0 || j >= qs.length) return qs
      const next = [...qs]
      ;[next[i], next[j]] = [next[j], next[i]]
      return next
    })
  }

  function duplicate(qKey: string) {
    setQuestions((qs) => {
      const i = qs.findIndex((q) => q.key === qKey)
      if (i < 0) return qs
      const src = qs[i]
      const copy: QDraft = {
        ...src,
        key: uid(),
        options: src.options.map((o) => ({ ...o, key: uid() })),
      }
      const next = [...qs]
      next.splice(i + 1, 0, copy)
      setOpenKey(copy.key)
      return next
    })
  }

  function remove(qKey: string) {
    setQuestions((qs) => qs.filter((q) => q.key !== qKey))
    setOpenKey((k) => (k === qKey ? null : k))
  }

  function addQuestion() {
    const q = newQuestion()
    setQuestions((qs) => [...qs, q])
    setOpenKey(q.key)
  }

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setLocalError(null)
    const firstInvalid = questions.find((q) => !isValidDraft(q))
    if (questions.length === 0 || firstInvalid) {
      setLocalError(
        questions.length === 0
          ? 'Add at least one question.'
          : 'Some questions are incomplete — each needs a prompt, and choice questions need filled options with one correct answer.',
      )
      if (firstInvalid) setOpenKey(firstInvalid.key)
      return
    }
    const out: AuthorQuestionInput[] = questions.map((q) => {
      const item: AuthorQuestionInput = { type: q.type, prompt: q.prompt }
      if (q.promptAudioAssetId) item.prompt_audio_asset_id = q.promptAudioAssetId
      if (isTextType(q.type)) {
        item.target_text = q.target_text
      } else if (isMatch(q.type)) {
        item.options = q.options.map((o) => ({ label: o.label, match_target: o.match_target }))
      } else if (isWordBank(q.type)) {
        // Authored order defines the correct sequence (server stores position).
        item.options = q.options.map((o) => ({ label: o.label }))
      } else {
        item.options = q.options.map((o) => ({ label: o.label, is_correct: o.correct }))
      }
      return item
    })
    const ok = await submit({ type: 'quiz', xp_value: 10, quiz: { pass_threshold: 0.6, hearts_enabled: true, questions: out } })
    if (ok && !isEdit) {
      const fresh = newQuestion()
      setQuestions([fresh])
      setOpenKey(fresh.key)
    }
  }

  const readyCount = questions.filter(isValidDraft).length

  return (
    <Modal open onClose={onClose} title={isEdit ? 'Edit quiz' : 'Build quiz'} description="Add questions; learners are graded server-side." className="max-w-2xl">
      <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
        {error && <Alert variant="danger">{error}</Alert>}
        {localError && <Alert variant="danger">{localError}</Alert>}

        <div className="flex flex-wrap items-center justify-between gap-2">
          <p className="text-sm text-muted">
            <span className="font-bold text-foreground">{questions.length}</span> question{questions.length === 1 ? '' : 's'}
            <span className="text-subtle"> · {readyCount} ready</span>
          </p>
          <div className="flex flex-wrap items-center gap-2">
            <input ref={fileRef} type="file" accept=".csv,.xlsx,text/csv" className="hidden" onChange={onImportFile} />
            <button type="button" className="text-sm font-medium text-primary hover:underline" onClick={downloadImportTemplate}>
              Template
            </button>
            <Button type="button" size="sm" variant="secondary" loading={importing} onClick={() => fileRef.current?.click()}>
              Import CSV/Excel
            </Button>
            <Button type="button" size="sm" variant="secondary" onClick={addQuestion}>
              + Add question
            </Button>
          </div>
        </div>

        {importErrors && importErrors.length > 0 && (
          <Alert variant="warning" title={`${importErrors.length} row${importErrors.length === 1 ? '' : 's'} skipped`}>
            <ul className="mt-1 list-disc pl-5 text-sm">
              {importErrors.slice(0, 8).map((e, i) => (
                <li key={i}>Row {e.row}: {e.error}</li>
              ))}
              {importErrors.length > 8 && <li>…and {importErrors.length - 8} more</li>}
            </ul>
          </Alert>
        )}

        <div className="-mr-1 flex max-h-[55vh] flex-col gap-2 overflow-y-auto pr-1">
          {questions.map((q, qi) => (
            <QuestionRow
              key={q.key}
              draft={q}
              index={qi}
              total={questions.length}
              valid={isValidDraft(q)}
              expanded={openKey === q.key}
              onToggle={() => setOpenKey((k) => (k === q.key ? null : q.key))}
              onPatch={(fn) => patch(q.key, fn)}
              onSetType={(t) => setType(q.key, t)}
              audioAssets={audioAssets}
              onMove={(dir) => move(q.key, dir)}
              onDuplicate={() => duplicate(q.key)}
              onRemove={() => remove(q.key)}
              canRemove={questions.length > 1}
            />
          ))}
        </div>

        <div className="flex gap-2 border-t border-border pt-4">
          <Button type="button" variant="secondary" fullWidth onClick={onClose}>Cancel</Button>
          <Button type="submit" fullWidth loading={pending}>{isEdit ? 'Save changes' : 'Save quiz'}</Button>
        </div>
      </form>
    </Modal>
  )
}

function QuestionRow({
  draft: q,
  index,
  total,
  valid,
  expanded,
  onToggle,
  onPatch,
  onSetType,
  audioAssets,
  onMove,
  onDuplicate,
  onRemove,
  canRemove,
}: {
  draft: QDraft
  index: number
  total: number
  valid: boolean
  expanded: boolean
  onToggle: () => void
  onPatch: (fn: (q: QDraft) => QDraft) => void
  onSetType: (t: QuestionType) => void
  audioAssets: AudioAsset[]
  onMove: (dir: -1 | 1) => void
  onDuplicate: () => void
  onRemove: () => void
  canRemove: boolean
}) {
  const summary = usesOptions(q.type)
    ? `${QUESTION_TYPE_LABEL[q.type]} · ${q.options.length} ${isMatch(q.type) ? 'pair' : isWordBank(q.type) ? 'word' : 'option'}${q.options.length === 1 ? '' : 's'}`
    : QUESTION_TYPE_LABEL[q.type] ?? q.type

  return (
    <div className={cn('rounded-2xl border bg-surface', expanded ? 'border-primary' : 'border-border')}>
      {/* Collapsed header — one line per question for easy scanning at scale. */}
      <div className="flex items-center gap-2 p-2.5">
        <button type="button" onClick={onToggle} className="flex min-w-0 flex-1 items-center gap-3 text-left" aria-expanded={expanded}>
          <span
            className={cn(
              'flex size-7 shrink-0 items-center justify-center rounded-lg text-xs font-bold ring-1',
              valid ? 'bg-leaf-50 text-leaf-700 ring-leaf-300' : 'bg-gold-50 text-gold-800 ring-gold-300',
            )}
            title={valid ? 'Ready' : 'Incomplete'}
          >
            {index + 1}
          </span>
          <span className="min-w-0 flex-1">
            <span className="block truncate text-sm font-semibold text-foreground">{q.prompt.trim() || 'Untitled question'}</span>
            <span className="block truncate text-xs text-muted">{summary}</span>
          </span>
          <Icon name="chevron" className={cn('size-4 shrink-0 text-muted transition-transform', expanded && 'rotate-180')} />
        </button>

        <div className="flex shrink-0 items-center">
          <IconBtn label="Move up" disabled={index === 0} onClick={() => onMove(-1)}>↑</IconBtn>
          <IconBtn label="Move down" disabled={index === total - 1} onClick={() => onMove(1)}>↓</IconBtn>
          <IconBtn label="Duplicate" onClick={onDuplicate}>⧉</IconBtn>
          <IconBtn label="Remove" disabled={!canRemove} danger onClick={onRemove}>✕</IconBtn>
        </div>
      </div>

      {expanded && (
        <div className="flex flex-col gap-3 border-t border-border p-4 pt-3">
          <label className="flex flex-col gap-1.5">
            <span className="text-sm font-semibold text-foreground">Type</span>
            <select
              value={q.type}
              onChange={(e) => onSetType(e.target.value as QuestionType)}
              className="h-10 rounded-xl border border-border-strong bg-surface px-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
            >
              {(Object.keys(QUESTION_TYPE_LABEL) as QuestionType[]).map((t) => (
                <option key={t} value={t}>{QUESTION_TYPE_LABEL[t]}</option>
              ))}
            </select>
          </label>

          <Input label="Prompt" value={q.prompt} onChange={(e) => onPatch((x) => ({ ...x, prompt: e.target.value }))} required />

          {needsAudio(q.type) && <AudioPicker assets={audioAssets} draft={q} onPatch={onPatch} />}

          {isTextType(q.type) ? (
            <Input
              label="Correct answer"
              value={q.target_text}
              onChange={(e) => onPatch((x) => ({ ...x, target_text: e.target.value }))}
              placeholder="Exact expected text"
              required
            />
          ) : isMatch(q.type) ? (
            <MatchEditor draft={q} onPatch={onPatch} />
          ) : isWordBank(q.type) ? (
            <WordEditor draft={q} onPatch={onPatch} />
          ) : (
            <OptionEditor draft={q} onPatch={onPatch} />
          )}
        </div>
      )}
    </div>
  )
}

interface EditorProps {
  draft: QDraft
  onPatch: (fn: (q: QDraft) => QDraft) => void
}

function toggleCorrectIn(options: QOption[], key: string, multi: boolean): QOption[] {
  return multi
    ? options.map((o) => (o.key === key ? { ...o, correct: !o.correct } : o))
    : options.map((o) => ({ ...o, correct: o.key === key }))
}

/** mcq_single / mcq_multi / true_false / fill_blank / listen_and_respond / complete_the_chat. */
function OptionEditor({ draft, onPatch }: EditorProps) {
  const multi = isMulti(draft.type)
  const lockLabels = draft.type === 'true_false'
  return (
    <div className="flex flex-col gap-2">
      <span className="text-sm font-semibold text-foreground">
        Options <span className="font-normal text-muted">({multi ? 'tap ✓ for every correct answer' : 'tap ✓ to mark the correct one'})</span>
      </span>
      {draft.options.map((o) => (
        <div key={o.key} className="flex items-center gap-2">
          <button
            type="button"
            onClick={() => onPatch((x) => ({ ...x, options: toggleCorrectIn(x.options, o.key, multi) }))}
            aria-label="Mark correct"
            className={cn(
              'flex size-8 shrink-0 items-center justify-center rounded-lg border',
              o.correct ? 'border-leaf-400 bg-leaf-50 text-leaf-700' : 'border-border-strong text-subtle',
            )}
          >
            ✓
          </button>
          <input
            value={o.label}
            onChange={(e) => onPatch((x) => ({ ...x, options: x.options.map((y) => (y.key === o.key ? { ...y, label: e.target.value } : y)) }))}
            placeholder="Option text"
            disabled={lockLabels}
            className="h-10 flex-1 rounded-xl border border-border-strong bg-surface px-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring disabled:opacity-70"
          />
          {!lockLabels && draft.options.length > 2 && (
            <button type="button" className="text-xs text-danger" onClick={() => onPatch((x) => ({ ...x, options: x.options.filter((y) => y.key !== o.key) }))}>
              ✕
            </button>
          )}
        </div>
      ))}
      {!lockLabels && (
        <button
          type="button"
          className="self-start text-sm font-medium text-primary"
          onClick={() => onPatch((x) => ({ ...x, options: [...x.options, blankOption()] }))}
        >
          + Add option
        </button>
      )}
    </div>
  )
}

/** Word bank — the authored order is the correct sentence. */
function WordEditor({ draft, onPatch }: EditorProps) {
  return (
    <div className="flex flex-col gap-2">
      <span className="text-sm font-semibold text-foreground">
        Words <span className="font-normal text-muted">(in the correct order — learners shuffle & rebuild)</span>
      </span>
      {draft.options.map((o, i) => (
        <div key={o.key} className="flex items-center gap-2">
          <span className="flex size-8 shrink-0 items-center justify-center rounded-lg border border-border-strong text-xs font-bold text-subtle">{i + 1}</span>
          <input
            value={o.label}
            onChange={(e) => onPatch((x) => ({ ...x, options: x.options.map((y) => (y.key === o.key ? { ...y, label: e.target.value } : y)) }))}
            placeholder="Word or phrase"
            className="h-10 flex-1 rounded-xl border border-border-strong bg-surface px-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
          />
          {draft.options.length > 2 && (
            <button type="button" className="text-xs text-danger" onClick={() => onPatch((x) => ({ ...x, options: x.options.filter((y) => y.key !== o.key) }))}>
              ✕
            </button>
          )}
        </div>
      ))}
      <button
        type="button"
        className="self-start text-sm font-medium text-primary"
        onClick={() => onPatch((x) => ({ ...x, options: [...x.options, blankOption()] }))}
      >
        + Add word
      </button>
    </div>
  )
}

/** Match pairs — each left prompt with its match. */
function MatchEditor({ draft, onPatch }: EditorProps) {
  return (
    <div className="flex flex-col gap-2">
      <span className="text-sm font-semibold text-foreground">
        Pairs <span className="font-normal text-muted">(left prompt ↔ its match)</span>
      </span>
      {draft.options.map((o) => (
        <div key={o.key} className="flex items-center gap-2">
          <input
            value={o.label}
            onChange={(e) => onPatch((x) => ({ ...x, options: x.options.map((y) => (y.key === o.key ? { ...y, label: e.target.value } : y)) }))}
            placeholder="Left"
            className="h-10 flex-1 rounded-xl border border-border-strong bg-surface px-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
          />
          <span aria-hidden="true" className="text-muted">↔</span>
          <input
            value={o.match_target ?? ''}
            onChange={(e) => onPatch((x) => ({ ...x, options: x.options.map((y) => (y.key === o.key ? { ...y, match_target: e.target.value } : y)) }))}
            placeholder="Match"
            className="h-10 flex-1 rounded-xl border border-border-strong bg-surface px-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
          />
          {draft.options.length > 2 && (
            <button type="button" className="text-xs text-danger" onClick={() => onPatch((x) => ({ ...x, options: x.options.filter((y) => y.key !== o.key) }))}>
              ✕
            </button>
          )}
        </div>
      ))}
      <button
        type="button"
        className="self-start text-sm font-medium text-primary"
        onClick={() => onPatch((x) => ({ ...x, options: [...x.options, pairOption()] }))}
      >
        + Add pair
      </button>
    </div>
  )
}

/** Prompt-audio picker for listen_and_respond. */
function AudioPicker({ assets, draft, onPatch }: { assets: AudioAsset[]; draft: QDraft; onPatch: (fn: (q: QDraft) => QDraft) => void }) {
  // An imported question carries only the asset id — resolve its URL for preview.
  const previewSrc = draft.promptAudio ?? assets.find((a) => a.id === draft.promptAudioAssetId)?.url ?? null
  return (
    <label className="flex flex-col gap-1.5">
      <span className="text-sm font-semibold text-foreground">Prompt audio</span>
      <select
        value={draft.promptAudioAssetId ?? ''}
        onChange={(e) => {
          const id = e.target.value ? Number(e.target.value) : null
          const asset = assets.find((a) => a.id === id)
          onPatch((x) => ({ ...x, promptAudioAssetId: id, promptAudio: asset?.url ?? null }))
        }}
        className="h-10 rounded-xl border border-border-strong bg-surface px-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
      >
        <option value="">Choose an audio asset…</option>
        {assets.map((a) => (
          <option key={a.id} value={a.id}>#{a.id} {a.original_name ?? a.url}</option>
        ))}
      </select>
      {previewSrc && <audio src={previewSrc} controls className="mt-1 w-full" />}
      {assets.length === 0 && <span className="text-xs text-muted">Upload audio in the Media library first.</span>}
    </label>
  )
}

function IconBtn({
  label,
  onClick,
  disabled,
  danger,
  children,
}: {
  label: string
  onClick: () => void
  disabled?: boolean
  danger?: boolean
  children: ReactNode
}) {
  return (
    <button
      type="button"
      aria-label={label}
      title={label}
      disabled={disabled}
      onClick={onClick}
      className={cn(
        'flex size-8 items-center justify-center rounded-lg text-sm transition-colors hover:bg-surface-muted disabled:opacity-30 disabled:hover:bg-transparent',
        danger ? 'text-danger' : 'text-muted',
      )}
    >
      {children}
    </button>
  )
}
