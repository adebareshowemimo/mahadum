import { useState, type FormEvent } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Alert, Badge, Button, Card, CardBody, Input, Modal, Skeleton } from '@/components/ui'
import { ApiError, contentApi, type LibrarySetLevel } from '@/lib/api'
import { useCanManageContent } from '@/lib/content/permissions'

export function LibrarySetBuilderPage() {
  const id = Number(useParams().setId)
  const client = useQueryClient()
  const navigate = useNavigate()
  const canManage = useCanManageContent()
  const set = useQuery({ queryKey: ['content', 'library-set', id], queryFn: () => contentApi.librarySet(id), enabled: id > 0 })
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [levelOpen, setLevelOpen] = useState(false)
  const [editingLevel, setEditingLevel] = useState<LibrarySetLevel | null>(null)
  const [levelTitle, setLevelTitle] = useState('')
  const [levelFree, setLevelFree] = useState(true)
  const [lessonLevel, setLessonLevel] = useState<LibrarySetLevel | null>(null)
  const [lessonTitle, setLessonTitle] = useState('')
  const [detailsOpen, setDetailsOpen] = useState(false)
  const [title, setTitle] = useState('')
  const [description, setDescription] = useState('')

  async function run(action: () => Promise<unknown>) {
    setBusy(true); setError(null)
    try { await action(); await client.invalidateQueries({ queryKey: ['content', 'library-set', id] }); await client.invalidateQueries({ queryKey: ['content', 'library-sets'] }) }
    catch (err) { setError(err instanceof ApiError ? err.message : 'Could not save this set.') }
    finally { setBusy(false) }
  }

  async function createLevel(event: FormEvent) {
    event.preventDefault()
    await run(async () => {
      if (editingLevel) await contentApi.updateLibraryLevel(editingLevel.id, { title: levelTitle.trim(), is_free: levelFree })
      else await contentApi.addLibraryLevel(id, { title: levelTitle.trim(), is_free: levelFree })
      setLevelOpen(false); setEditingLevel(null); setLevelTitle('')
    })
  }

  async function createLesson(event: FormEvent) {
    event.preventDefault()
    if (!lessonLevel) return
    await run(async () => {
      const lesson = await contentApi.addLibraryLesson(lessonLevel.id, { title: lessonTitle.trim() })
      setLessonLevel(null); setLessonTitle(''); navigate(`/courses/proverbs-trivia/${id}/lessons/${lesson.id}`)
    })
  }

  async function moveLevel(index: number, direction: -1 | 1) {
    const levels = set.data?.levels ?? []
    const target = index + direction
    if (target < 0 || target >= levels.length) return
    const order = levels.map((level) => level.id)
    ;[order[index], order[target]] = [order[target], order[index]]
    await run(() => contentApi.reorderLibraryLevels(id, order))
  }

  async function moveLesson(level: LibrarySetLevel, index: number, direction: -1 | 1) {
    const target = index + direction
    if (target < 0 || target >= level.lessons.length) return
    const order = level.lessons.map((lesson) => lesson.id)
    ;[order[index], order[target]] = [order[target], order[index]]
    await run(() => contentApi.reorderLibraryLessons(level.id, order))
  }

  if (set.isLoading) return <Skeleton className="h-64" />
  if (set.isError || !set.data) return <Alert variant="danger">Could not load this set.</Alert>
  const item = set.data
  const levels = item.levels ?? []

  return <div className="flex flex-col gap-6">
    <header><Link to="/courses/proverbs-trivia" className="text-sm font-semibold text-primary">← Proverbs & Trivia</Link><div className="mt-2 flex flex-wrap items-end justify-between gap-3"><div><p className="text-xs font-bold uppercase tracking-widest text-primary">{item.kind} set · {item.language?.toUpperCase()}</p><h1 className="mt-1 font-display text-2xl font-bold">{item.title}</h1>{item.description && <p className="mt-1 text-sm text-muted">{item.description}</p>}</div><div className="flex flex-wrap gap-2"><Badge variant={item.status === 'published' ? 'success' : 'neutral'}>{item.status}</Badge>{canManage && <><Button variant="secondary" onClick={() => { setTitle(item.title); setDescription(item.description ?? ''); setDetailsOpen(true) }}>Edit details</Button><Button variant="secondary" onClick={() => { setEditingLevel(null); setLevelTitle(''); setLevelFree(true); setLevelOpen(true) }}>Add level</Button><Button loading={busy} onClick={() => void run(() => contentApi.setLibraryCollectionPublished(id, item.status !== 'published'))}>{item.status === 'published' ? 'Unpublish' : 'Publish set'}</Button></>}</div></div></header>
    {error && <Alert variant="danger">{error}</Alert>}
    <h2 className="font-display text-lg font-bold">Set structure</h2>
    {levels.length === 0 && <Card><CardBody className="py-10 text-center text-muted">No levels yet. Add a level to hold lessons.</CardBody></Card>}
    {levels.map((level, index) => <Card key={level.id}><CardBody className="space-y-4"><div className="flex flex-wrap items-center justify-between gap-3"><div><h3 className="font-display text-lg font-bold">{level.position}. {level.title}</h3><Badge variant={level.is_free ? 'success' : 'neutral'}>{level.is_free ? 'Free' : 'Paid'}</Badge></div>{canManage && <div className="flex flex-wrap gap-2"><Button size="sm" variant="secondary" disabled={index === 0 || busy} onClick={() => void moveLevel(index, -1)}>Up</Button><Button size="sm" variant="secondary" disabled={index === levels.length - 1 || busy} onClick={() => void moveLevel(index, 1)}>Down</Button><Button size="sm" variant="secondary" onClick={() => { setEditingLevel(level); setLevelTitle(level.title); setLevelFree(level.is_free); setLevelOpen(true) }}>Edit level</Button><Button size="sm" onClick={() => { setLessonTitle(''); setLessonLevel(level) }}>Add lesson</Button>{level.lessons.length === 0 && <Button size="sm" variant="danger" onClick={() => { if (window.confirm(`Delete ${level.title}?`)) void run(() => contentApi.deleteLibraryLevel(level.id)) }}>Delete level</Button>}</div>}</div><div className="divide-y divide-border rounded-xl border border-border">{level.lessons.length === 0 && <p className="p-4 text-sm text-muted">No lessons yet.</p>}{level.lessons.map((lesson, lessonIndex) => <div key={lesson.id} className="flex flex-wrap items-center justify-between gap-3 p-4"><div><p className="font-semibold">{lesson.title}</p><p className="text-xs text-muted">{lesson.published_at ? 'Published' : 'Draft'}</p></div><div className="flex flex-wrap gap-2">{canManage && <><Button size="sm" variant="secondary" disabled={lessonIndex === 0 || busy} onClick={() => void moveLesson(level, lessonIndex, -1)}>Up</Button><Button size="sm" variant="secondary" disabled={lessonIndex === level.lessons.length - 1 || busy} onClick={() => void moveLesson(level, lessonIndex, 1)}>Down</Button></>}<Button size="sm" variant="secondary" onClick={() => navigate(`/courses/proverbs-trivia/${id}/lessons/${lesson.id}`)}>{canManage ? 'Edit lesson' : 'View'}</Button>{canManage && <Button size="sm" variant="danger" onClick={() => { if (window.confirm(`Delete ${lesson.title}?`)) void run(() => contentApi.deleteLesson(lesson.id)) }}>Delete</Button>}</div></div>)}</div></CardBody></Card>)}
    <Modal open={levelOpen} onClose={() => setLevelOpen(false)} title={editingLevel ? 'Edit level' : 'Add level'}><form className="space-y-4" onSubmit={createLevel}><Input label="Level title" value={levelTitle} onChange={(event) => setLevelTitle(event.target.value)} required /><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={levelFree} onChange={(event) => setLevelFree(event.target.checked)} /> Free access</label><Button type="submit" loading={busy} disabled={!levelTitle.trim()}>{editingLevel ? 'Save level' : 'Add level'}</Button></form></Modal>
    <Modal open={!!lessonLevel} onClose={() => setLessonLevel(null)} title={`Add lesson to ${lessonLevel?.title ?? 'level'}`}><form className="space-y-4" onSubmit={createLesson}><Input label="Lesson title" value={lessonTitle} onChange={(event) => setLessonTitle(event.target.value)} required /><Button type="submit" loading={busy} disabled={!lessonTitle.trim()}>Create lesson</Button></form></Modal>
    <Modal open={detailsOpen} onClose={() => setDetailsOpen(false)} title="Edit set details"><form className="space-y-4" onSubmit={(event) => { event.preventDefault(); void run(async () => { await contentApi.updateLibraryCollection(id, { title: title.trim(), description: description.trim() }); setDetailsOpen(false) }) }}><Input label="Set title" value={title} onChange={(event) => setTitle(event.target.value)} required /><Input label="Description" value={description} onChange={(event) => setDescription(event.target.value)} /><Button type="submit" loading={busy} disabled={!title.trim()}>Save details</Button></form></Modal>
  </div>
}
