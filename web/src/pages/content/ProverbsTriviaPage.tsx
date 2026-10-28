import { useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Alert, Badge, Button, Input, Modal, Skeleton } from '@/components/ui'
import { ApiError, contentApi, type LibraryLesson } from '@/lib/api'
import { useAuthorCourses, useCourseLevels } from '@/lib/content/queries'
import { useCanManageContent } from '@/lib/content/permissions'

type Kind = 'proverb' | 'trivia'

function CreatorSection({ kind, canManage, onCreate, onDelete }: { kind: Kind; canManage: boolean; onCreate: () => void; onDelete: (lesson: LibraryLesson) => void }) {
  const navigate = useNavigate()
  const [page, setPage] = useState(1)
  const lessons = useQuery({ queryKey: ['content', 'library-lessons', kind, page], queryFn: () => contentApi.libraryLessons(kind, page) })
  const label = kind === 'proverb' ? 'Proverb sets' : 'Trivia sets'
  return <section className="overflow-hidden rounded-3xl border border-border bg-surface">
    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border p-5 sm:p-6">
      <div><p className="text-xs font-bold uppercase tracking-widest text-primary">{kind === 'proverb' ? 'Words of wisdom' : 'Play & learn'}</p><h2 className="mt-1 font-display text-xl font-bold">{label}</h2><p className="text-sm text-muted">{kind === 'proverb' ? 'Sayings, meanings and lesson practice.' : 'Question sets with configurable timers, attempts and XP.'}</p></div>
      {canManage && <Button size="sm" onClick={onCreate}>+ Add {kind} set</Button>}
    </div>
    {lessons.isError && <div className="p-5"><Alert variant="danger">Could not load {label.toLowerCase()}.</Alert></div>}
    {lessons.isLoading && <div className="p-5"><Skeleton className="h-40" /></div>}
    {lessons.data && <>
      <div className="divide-y divide-border">{lessons.data.data.length === 0 && <p className="p-6 text-sm text-muted">No {label.toLowerCase()} yet. Add one in an existing course level.</p>}
        {lessons.data.data.map((lesson) => <article key={lesson.id} className="flex flex-wrap items-center justify-between gap-4 p-5">
          <div className="min-w-0 flex-1"><div className="flex flex-wrap items-center gap-2"><h3 className="font-display font-bold">{lesson.title}</h3><Badge variant={lesson.is_published ? 'success' : 'neutral'}>{lesson.is_published ? 'Published' : 'Draft'}</Badge></div>
            <p className="mt-1 text-sm text-muted">{lesson.course_title} · {lesson.level_title} · {lesson.language.toUpperCase()}</p>
            <div className="mt-2 flex flex-wrap gap-2 text-xs font-semibold text-muted"><span>{kind === 'proverb' ? `${lesson.proverb_count} proverbs` : `${lesson.question_count} questions`}</span>{lesson.time_limit_seconds ? <span>· {lesson.time_limit_seconds}s per question</span> : null}{lesson.has_audio && <span>· Audio</span>}</div>
          </div>
          <div className="flex flex-wrap gap-2"><Button size="sm" variant="secondary" onClick={() => window.open(`/courses/${lesson.course_id}/lessons/${lesson.id}/preview`, '_blank', 'noopener')}>Preview</Button><Button size="sm" variant="secondary" onClick={() => navigate(`/courses/${lesson.course_id}/lessons/${lesson.id}`)}>Edit</Button>{canManage && <Button size="sm" variant="ghost" className="text-danger" onClick={() => onDelete(lesson)}>Delete</Button>}</div>
        </article>)}
      </div>
      {lessons.data.meta.last_page > 1 && <div className="flex items-center justify-between border-t border-border p-4"><Button size="sm" variant="secondary" disabled={page <= 1} onClick={() => setPage(page - 1)}>Previous</Button><span className="text-sm text-muted">Page {page} of {lessons.data.meta.last_page}</span><Button size="sm" variant="secondary" disabled={page >= lessons.data.meta.last_page} onClick={() => setPage(page + 1)}>Next</Button></div>}
    </>}
  </section>
}

export function ProverbsTriviaPage() {
  const navigate = useNavigate()
  const qc = useQueryClient()
  const canManage = useCanManageContent()
  const [kind, setKind] = useState<Kind>('proverb')
  const [open, setOpen] = useState(false)
  const [deleting, setDeleting] = useState<LibraryLesson | null>(null)
  const [courseId, setCourseId] = useState(0)
  const [levelId, setLevelId] = useState(0)
  const [title, setTitle] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const courses = useAuthorCourses()
  const levels = useCourseLevels(courseId)

  function openCreate(nextKind: Kind) { setKind(nextKind); setError(null); setOpen(true) }
  async function create(event: FormEvent) {
    event.preventDefault()
    if (!levelId || !title.trim()) return
    setBusy(true); setError(null)
    try { const lesson = await contentApi.createLesson(levelId, { title: title.trim(), kind }); await qc.invalidateQueries({ queryKey: ['content', 'library-lessons'] }); setOpen(false); navigate(`/courses/${courseId}/lessons/${lesson.id}`) }
    catch (err) { setError(err instanceof ApiError ? err.message : 'Could not create this lesson.') }
    finally { setBusy(false) }
  }
  async function remove() {
    if (!deleting) return
    setBusy(true); setError(null)
    try { await contentApi.deleteLesson(deleting.id); await qc.invalidateQueries({ queryKey: ['content', 'library-lessons'] }); setDeleting(null) }
    catch (err) { setError(err instanceof ApiError ? err.message : 'Could not delete this lesson.') }
    finally { setBusy(false) }
  }

  return <div className="flex flex-col gap-7">
    <header className="flex flex-wrap items-end justify-between gap-3"><div><button className="text-sm font-semibold text-primary hover:underline" onClick={() => navigate('/courses')}>← Courses</button><h1 className="mt-2 font-display text-3xl font-bold">Proverbs & Trivia</h1><p className="mt-1 text-muted">Create each set with the same lesson editor used by courses.</p></div></header>
    <div className="rounded-2xl border border-primary/25 bg-primary-soft px-5 py-4 text-sm">Set quiz time, retries, hearts and XP in each lesson. Proverbs and trivia follow the course level's access rules.</div>
    {error && !open && !deleting && <Alert variant="danger">{error}</Alert>}
    <CreatorSection kind="proverb" canManage={canManage} onCreate={() => openCreate('proverb')} onDelete={setDeleting} />
    <CreatorSection kind="trivia" canManage={canManage} onCreate={() => openCreate('trivia')} onDelete={setDeleting} />
    <Modal open={open} onClose={() => setOpen(false)} title={`New ${kind} lesson`} description="Choose a course level, then continue in the lesson editor.">
      <form onSubmit={create} className="flex flex-col gap-4">{error && <Alert variant="danger">{error}</Alert>}
        <label className="flex flex-col gap-1 text-sm font-semibold">Course<select className="h-11 rounded-xl border border-border bg-surface px-3" value={courseId} onChange={(event) => { setCourseId(Number(event.target.value)); setLevelId(0) }} required><option value={0}>Choose a course</option>{courses.data?.map((course) => <option key={course.id} value={course.id}>{course.title}</option>)}</select></label>
        <label className="flex flex-col gap-1 text-sm font-semibold">Level<select className="h-11 rounded-xl border border-border bg-surface px-3" value={levelId} onChange={(event) => setLevelId(Number(event.target.value))} required disabled={!courseId}><option value={0}>Choose a level</option>{levels.data?.map((level) => <option key={level.id} value={level.id}>{level.title}</option>)}</select></label>
        <Input label="Lesson title" value={title} onChange={(event) => setTitle(event.target.value)} required /><Button type="submit" disabled={!courseId || !levelId || !title.trim()} loading={busy}>Create and edit</Button>
      </form>
    </Modal>
    <Modal open={!!deleting} onClose={() => { setDeleting(null); setError(null) }} title="Delete lesson" description="This removes the set and its questions or proverbs."><p className="mb-4 font-semibold">{deleting?.title}</p>{error && <Alert variant="danger">{error}</Alert>}<div className="flex justify-end gap-2"><Button variant="secondary" onClick={() => setDeleting(null)}>Cancel</Button><Button variant="danger" loading={busy} onClick={remove}>Delete lesson</Button></div></Modal>
  </div>
}
