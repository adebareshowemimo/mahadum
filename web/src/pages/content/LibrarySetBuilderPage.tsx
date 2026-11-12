import { useState, type FormEvent } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { Alert, Badge, Button, Card, CardBody, Input, Modal, Skeleton, Switch } from '@/components/ui'
import { ApiError, contentApi, type LibrarySetLevel, type LibrarySetLesson } from '@/lib/api'
import { useCanManageContent } from '@/lib/content/permissions'
import { useLibrarySet, useRefreshLibrary } from '@/lib/content/libraryQueries'
import { LibrarySetContents } from '@/components/content/LibrarySetContents'
import { LibraryDeleteModal, LibraryError } from '@/components/content/LibraryFeedback'
import { SortableLibrary, SortableLibraryItem } from '@/components/content/SortableLibrary'

type Editor = { kind: 'details' } | { kind: 'level'; level?: LibrarySetLevel } | { kind: 'lesson'; level: LibrarySetLevel; lesson?: LibrarySetLesson }
type Deletion = { kind: 'level' | 'lesson'; id: number; title: string }

export function LibrarySetBuilderPage() {
  const id = Number(useParams().setId)
  const refresh = useRefreshLibrary()
  const navigate = useNavigate()
  const canManage = useCanManageContent()
  const set = useLibrarySet(id)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<unknown>(null)
  const [report, setReport] = useState('')
  const [editor, setEditor] = useState<Editor | null>(null)
  const [deleting, setDeleting] = useState<Deletion | null>(null)
  const [values, setValues] = useState({ title: '', description: '', level_band: '', minutes: '5', free: true })
  const fields = error instanceof ApiError ? error.fieldErrors : {}

  async function run(action: () => Promise<unknown>): Promise<boolean> {
    setBusy(true); setError(null); setReport('')
    try { await action(); await refresh(); return true }
    catch (err) { setError(err); return false }
    finally { setBusy(false) }
  }

  function edit(next: Editor) {
    setError(null)
    if (next.kind === 'details') setValues({ title: set.data?.title ?? '', description: set.data?.description ?? '', level_band: set.data?.level_band ?? '', minutes: '5', free: true })
    else if (next.kind === 'level') setValues({ title: next.level?.title ?? '', description: '', level_band: '', minutes: '5', free: next.level?.is_free ?? true })
    else setValues({ title: next.lesson?.title ?? '', description: '', level_band: '', minutes: String(next.lesson?.est_minutes ?? 5), free: true })
    setEditor(next)
  }

  async function save(event: FormEvent) {
    event.preventDefault()
    if (!editor) return
    let newLessonId: number | undefined
    const success = await run(async () => {
      if (editor.kind === 'details') await contentApi.updateLibraryCollection(id, { title: values.title.trim(), description: values.description.trim(), level_band: values.level_band.trim() })
      else if (editor.kind === 'level') {
        const input = { title: values.title.trim(), is_free: values.free }
        if (editor.level) await contentApi.updateLibraryLevel(editor.level.id, input)
        else await contentApi.addLibraryLevel(id, input)
      } else {
        const input = { title: values.title.trim(), est_minutes: Number(values.minutes) }
        if (editor.lesson) await contentApi.updateLesson(editor.lesson.id, input)
        else newLessonId = (await contentApi.addLibraryLesson(editor.level.id, input)).id
      }
    })
    if (success) { setEditor(null); if (newLessonId) navigate(`/courses/proverbs-trivia/${id}/lessons/${newLessonId}`) }
  }

  function move(ids: number[], index: number, direction: -1 | 1, saveOrder: (order: number[]) => Promise<unknown>) {
    const target = index + direction
    if (target < 0 || target >= ids.length) return
    const order = [...ids]
    ;[order[index], order[target]] = [order[target], order[index]]
    void run(() => saveOrder(order))
  }

  if (set.isLoading) return <Skeleton className="h-64" />
  if (set.isError || !set.data) return <Alert variant="danger">Could not load this set.</Alert>
  const item = set.data
  const levels = item.levels ?? []
  const preview = (levelId?: number) => window.open(`/courses/proverbs-trivia/${id}${levelId ? `/levels/${levelId}` : ''}/preview`, '_blank', 'noopener')

  return <div className="flex flex-col gap-6">
    <header><Link to="/courses/proverbs-trivia" className="text-sm font-semibold text-primary">← Proverbs & Trivia</Link><div className="mt-2 flex flex-wrap items-end justify-between gap-3"><div><p className="text-xs font-bold uppercase tracking-widest text-primary">{item.kind} set · {item.language?.toUpperCase()} {item.level_band && `· ${item.level_band}`}</p><h1 className="mt-1 font-display text-2xl font-bold">{item.title}</h1>{item.description && <p className="mt-1 text-sm text-muted">{item.description}</p>}{item.owner && <p className="mt-1 text-sm text-muted">Owner: {item.owner}</p>}</div><div className="flex flex-wrap gap-2"><Badge variant={item.status === 'published' ? 'success' : 'neutral'}>{item.status}</Badge>
      <Button variant="secondary" onClick={() => preview()}>Preview set</Button>
      {canManage && <><Button variant="secondary" disabled={busy} onClick={() => edit({ kind: 'details' })}>Edit details</Button><Button variant="secondary" disabled={busy} onClick={() => edit({ kind: 'level' })}>Add level</Button>
        {item.status !== 'archived' && <Button loading={busy} onClick={() => void run(async () => { const result = await contentApi.setLibraryCollectionPublished(id, item.status !== 'published'); setReport(result.status === 'published' ? `${result.title} is published. All ${result.lessons_count} lessons are live.` : `${result.title} is now unpublished.`) })}>{item.status === 'published' ? 'Unpublish' : 'Publish set'}</Button>}
        <Button variant="secondary" disabled={busy} onClick={() => void run(async () => { await contentApi.setLibraryCollectionArchived(id, item.status !== 'archived'); setReport(item.status === 'archived' ? 'Set restored as a draft. Publish it when ready.' : 'Set archived.') })}>{item.status === 'archived' ? 'Unarchive' : 'Archive'}</Button>
      </>}
    </div></div></header>
    {!editor && !deleting && <LibraryError error={error} />}{report && <Alert variant="success">{report}</Alert>}
    <LibrarySetContents setId={id} />
    <h2 id="set-structure" className="font-display text-lg font-bold">Set structure</h2>
    {!levels.length && <Card><CardBody className="py-10 text-center text-muted">No levels yet. Add a level to hold lessons.</CardBody></Card>}
    <SortableLibrary ids={levels.map(level => level.id)} disabled={!canManage || busy} onReorder={order => void run(() => contentApi.reorderLibraryLevels(id, order))}>
      <div className="space-y-5">{levels.map((level, index) => <SortableLibraryItem key={level.id} id={level.id} label={`level ${level.title}`} disabled={!canManage || busy}><Card><CardBody className="space-y-4">
        <div className="flex flex-wrap items-center justify-between gap-3"><div><h3 className="font-display text-lg font-bold">{level.position}. {level.title}</h3><Badge variant={level.is_free ? 'success' : 'neutral'}>{level.is_free ? 'Free' : 'Paid'}</Badge></div><div className="flex flex-wrap gap-2">
          <Button size="sm" variant="secondary" onClick={() => preview(level.id)}>Preview level</Button>
          {canManage && <><Button size="sm" variant="secondary" aria-label={`Move level ${level.title} up`} disabled={index === 0 || busy} onClick={() => move(levels.map(value => value.id), index, -1, order => contentApi.reorderLibraryLevels(id, order))}>Up</Button><Button size="sm" variant="secondary" aria-label={`Move level ${level.title} down`} disabled={index === levels.length - 1 || busy} onClick={() => move(levels.map(value => value.id), index, 1, order => contentApi.reorderLibraryLevels(id, order))}>Down</Button><Button size="sm" variant="secondary" disabled={busy} onClick={() => edit({ kind: 'level', level })}>Edit level</Button><Button size="sm" disabled={busy} onClick={() => edit({ kind: 'lesson', level })}>Add lesson</Button>{!level.lessons.length && <Button size="sm" variant="danger" disabled={busy} onClick={() => { setError(null); setDeleting({ kind: 'level', id: level.id, title: level.title }) }}>Delete level</Button>}</>}
        </div></div>
        <SortableLibrary ids={level.lessons.map(lesson => lesson.id)} disabled={!canManage || busy} onReorder={order => void run(() => contentApi.reorderLibraryLessons(level.id, order))}>
          <div className="space-y-2">{!level.lessons.length && <p className="p-4 text-sm text-muted">No lessons yet.</p>}{level.lessons.map((lesson, lessonIndex) => <SortableLibraryItem key={lesson.id} id={lesson.id} label={`lesson ${lesson.title}`} disabled={!canManage || busy}>
            <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border p-3"><Link to={`/courses/proverbs-trivia/${id}/lessons/${lesson.id}`} className="font-semibold text-primary">{lesson.position}. {lesson.title}</Link><div className="flex flex-wrap items-center gap-2">{lesson.est_minutes != null && <span className="text-sm text-muted">{lesson.est_minutes} min</span>}<Badge variant={lesson.published_at ? 'success' : 'neutral'}>{lesson.published_at ? 'Published' : 'Draft'}</Badge>
              {canManage && <><Button size="sm" variant="ghost" aria-label={`Move lesson ${lesson.title} up`} disabled={lessonIndex === 0 || busy} onClick={() => move(level.lessons.map(value => value.id), lessonIndex, -1, order => contentApi.reorderLibraryLessons(level.id, order))}>Up</Button><Button size="sm" variant="ghost" aria-label={`Move lesson ${lesson.title} down`} disabled={lessonIndex === level.lessons.length - 1 || busy} onClick={() => move(level.lessons.map(value => value.id), lessonIndex, 1, order => contentApi.reorderLibraryLessons(level.id, order))}>Down</Button><Button size="sm" variant="secondary" disabled={busy} onClick={() => edit({ kind: 'lesson', level, lesson })}>Edit details</Button></>}
              <Button size="sm" variant="secondary" onClick={() => navigate(`/courses/proverbs-trivia/${id}/lessons/${lesson.id}`)}>{canManage ? 'Edit lesson' : 'View'}</Button>{canManage && <Button size="sm" variant="danger" disabled={busy} onClick={() => { setError(null); setDeleting({ kind: 'lesson', id: lesson.id, title: lesson.title }) }}>Delete</Button>}
            </div></div>
          </SortableLibraryItem>)}</div>
        </SortableLibrary>
      </CardBody></Card></SortableLibraryItem>)}</div>
    </SortableLibrary>
    <Modal open={!!editor} onClose={() => { if (!busy) setEditor(null) }} title={editor?.kind === 'details' ? 'Edit set details' : editor?.kind === 'level' ? (editor.level ? 'Edit level' : 'Add level') : editor?.kind === 'lesson' && editor.lesson ? 'Edit lesson details' : 'Add lesson'}>
      <form className="space-y-4" onSubmit={save}><LibraryError error={error} /><Input label="Title" value={values.title} onChange={event => setValues({ ...values, title: event.target.value })} error={fields.title} required autoFocus />
        {editor?.kind === 'details' && <><Input label="Description" value={values.description} onChange={event => setValues({ ...values, description: event.target.value })} error={fields.description} /><Input label="Level band" value={values.level_band} onChange={event => setValues({ ...values, level_band: event.target.value })} error={fields.level_band} /></>}
        {editor?.kind === 'level' && <div className="rounded-xl border border-border p-3"><Switch label="Free access" checked={values.free} onChange={free => setValues({ ...values, free })} /><p className="mt-1 text-sm text-muted">All published lessons in this level are available without a paid plan. Free learners still use hearts.</p>{fields.is_free && <p className="text-sm text-danger">{fields.is_free}</p>}</div>}
        {editor?.kind === 'lesson' && <Input label="Estimated minutes" type="number" min={1} step={1} required value={values.minutes} onChange={event => setValues({ ...values, minutes: event.target.value })} error={fields.est_minutes} />}
        <div className="flex gap-2"><Button type="button" variant="secondary" fullWidth disabled={busy} onClick={() => setEditor(null)}>Cancel</Button><Button type="submit" fullWidth loading={busy} disabled={!values.title.trim()}>Save</Button></div>
      </form>
    </Modal>
    <LibraryDeleteModal title={deleting?.title ?? 'item'} open={!!deleting} busy={busy} error={error} onClose={() => setDeleting(null)} onConfirm={() => { if (deleting) void run(() => deleting.kind === 'level' ? contentApi.deleteLibraryLevel(deleting.id) : contentApi.deleteLesson(deleting.id)).then(success => { if (success) setDeleting(null) }) }} />
  </div>
}
