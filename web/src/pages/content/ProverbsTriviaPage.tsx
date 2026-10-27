import { useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Alert, Badge, Button, Input, Modal, Skeleton } from '@/components/ui'
import { ApiError, contentApi, type LibraryLesson } from '@/lib/api'
import { useAuthorCourses, useCourseLevels } from '@/lib/content/queries'
import { useCanManageContent } from '@/lib/content/permissions'

type Kind = 'proverb' | 'trivia'

export function ProverbsTriviaPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const canManage = useCanManageContent()
  const [kind, setKind] = useState<Kind>('proverb')
  const [open, setOpen] = useState(false)
  const [page, setPage] = useState(1)
  const [courseId, setCourseId] = useState(0)
  const [levelId, setLevelId] = useState(0)
  const [title, setTitle] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const courses = useAuthorCourses()
  const levels = useCourseLevels(courseId)
  const lessons = useQuery({
    queryKey: ['content', 'library-lessons', kind, page],
    queryFn: () => contentApi.libraryLessons(kind, page),
  })

  async function create(event: FormEvent) {
    event.preventDefault()
    if (!levelId || !title.trim()) return
    setBusy(true)
    setError(null)
    try {
      const lesson = await contentApi.createLesson(levelId, { title: title.trim(), kind })
      await queryClient.invalidateQueries({ queryKey: ['content', 'library-lessons'] })
      setOpen(false)
      navigate(`/courses/${courseId}/lessons/${lesson.id}`)
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not create this lesson.')
    } finally {
      setBusy(false)
    }
  }

  const rows = lessons.data?.data ?? []
  const meta = lessons.data?.meta

  return (
    <div className="flex flex-col gap-6">
      <header className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <button className="text-sm font-semibold text-primary hover:underline" onClick={() => navigate('/courses')}>← Courses</button>
          <h1 className="mt-2 font-display text-2xl font-bold">Proverbs & Trivia</h1>
          <p className="mt-1 text-muted">Build these with the same lesson editor, videos and quizzes used by courses.</p>
        </div>
        {canManage && <Button onClick={() => setOpen(true)}>New {kind === 'proverb' ? 'proverb' : 'trivia'} lesson</Button>}
      </header>

      <div className="flex gap-2" role="tablist" aria-label="Content type">
        {(['proverb', 'trivia'] as const).map((item) => (
          <Button key={item} variant={kind === item ? 'primary' : 'secondary'} onClick={() => { setKind(item); setPage(1) }}>
            {item === 'proverb' ? 'Proverbs' : 'Trivia'}
          </Button>
        ))}
      </div>

      {lessons.isError && <Alert variant="danger">Could not load the lessons.</Alert>}
      {lessons.isLoading ? <Skeleton className="h-48" /> : (
        <div className="divide-y divide-border overflow-hidden rounded-2xl border border-border bg-surface">
          {rows.length === 0 && <p className="p-6 text-sm text-muted">No {kind} lessons yet. Create one in an existing course level.</p>}
          {rows.map((lesson: LibraryLesson) => (
            <div key={lesson.id} className="flex flex-wrap items-center justify-between gap-3 p-4">
              <div>
                <p className="font-semibold">{lesson.title}</p>
                <p className="text-sm text-muted">{lesson.course_title} · {lesson.level_title} · {lesson.language.toUpperCase()}</p>
              </div>
              <div className="flex items-center gap-3">
                <Badge variant={lesson.is_published ? 'success' : 'neutral'}>{lesson.is_published ? 'Published' : 'Draft'}</Badge>
                <Button size="sm" variant="secondary" onClick={() => navigate(`/courses/${lesson.course_id}/lessons/${lesson.id}`)}>Edit lesson</Button>
              </div>
            </div>
          ))}
        </div>
      )}
      {meta && meta.last_page > 1 && <div className="flex justify-between gap-3">
        <Button variant="secondary" disabled={page <= 1} onClick={() => setPage(page - 1)}>Previous</Button>
        <span className="text-sm text-muted">Page {page} of {meta.last_page}</span>
        <Button variant="secondary" disabled={page >= meta.last_page} onClick={() => setPage(page + 1)}>Next</Button>
      </div>}

      <Modal open={open} onClose={() => setOpen(false)} title={`New ${kind} lesson`} description="Choose a course level, then continue in the lesson editor.">
        <form onSubmit={create} className="flex flex-col gap-4">
          {error && <Alert variant="danger">{error}</Alert>}
          <label className="flex flex-col gap-1 text-sm font-semibold">Course
            <select className="h-11 rounded-xl border border-border bg-surface px-3" value={courseId} onChange={(event) => { setCourseId(Number(event.target.value)); setLevelId(0) }} required>
              <option value={0}>Choose a course</option>
              {courses.data?.map((course) => <option key={course.id} value={course.id}>{course.title}</option>)}
            </select>
          </label>
          <label className="flex flex-col gap-1 text-sm font-semibold">Level
            <select className="h-11 rounded-xl border border-border bg-surface px-3" value={levelId} onChange={(event) => setLevelId(Number(event.target.value))} required disabled={!courseId}>
              <option value={0}>Choose a level</option>
              {levels.data?.map((level) => <option key={level.id} value={level.id}>{level.title}</option>)}
            </select>
          </label>
          <Input label="Lesson title" value={title} onChange={(event) => setTitle(event.target.value)} required />
          <Button type="submit" disabled={!courseId || !levelId || !title.trim()} loading={busy}>Create and edit</Button>
        </form>
      </Modal>
    </div>
  )
}
