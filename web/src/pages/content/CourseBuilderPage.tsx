import { useState, type FormEvent } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
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
import { ApiError } from '@/lib/api'
import {
  useAuthorCourses,
  useCourseLevels,
  useCreateLesson,
  useCreateLevel,
  useLevelLessons,
} from '@/lib/content/queries'
import { useCanManageContent } from '@/lib/content/permissions'

export function CourseBuilderPage() {
  const { courseId } = useParams()
  const id = Number(courseId)
  const courses = useAuthorCourses()
  const levels = useCourseLevels(id)
  const canManage = useCanManageContent()
  const [levelOpen, setLevelOpen] = useState(false)

  const course = courses.data?.find((c) => c.id === id)

  if (levels.isLoading) return <Skeleton className="h-48" />
  if (levels.isError || !levels.data) return <Alert variant="danger">Couldn’t load this course.</Alert>

  return (
    <div className="flex flex-col gap-6">
      <div>
        <Link to="/courses" className="text-sm font-medium text-primary hover:underline">
          ← Courses
        </Link>
        <div className="mt-2 flex flex-wrap items-end justify-between gap-3">
          <h1 className="font-display text-2xl font-bold text-foreground">{course?.title ?? 'Course'}</h1>
          <div className="flex items-center gap-2">
            <Button
              variant="secondary"
              leftIcon={<span aria-hidden="true">👁</span>}
              onClick={() => window.open(`/courses/${id}/preview`, '_blank', 'noopener')}
            >
              Preview course
            </Button>
            {canManage && (
              <Button variant="secondary" leftIcon={<Icon name="layers" className="size-[18px]" />} onClick={() => setLevelOpen(true)}>
                Add level
              </Button>
            )}
          </div>
        </div>
      </div>

      {levels.data.length === 0 ? (
        <Card>
          <CardBody className="py-10 text-center text-sm text-muted">
            No levels yet. Add a level (e.g. “Unit 1 — Greetings”) to hold lessons.
          </CardBody>
        </Card>
      ) : (
        <div className="flex flex-col gap-5">
          {levels.data.map((level) => (
            <LevelSection key={level.id} courseId={id} levelId={level.id} title={level.title} position={level.position} />
          ))}
        </div>
      )}

      <NewLevelModal courseId={id} open={levelOpen} onClose={() => setLevelOpen(false)} />
    </div>
  )
}

function LevelSection({ courseId, levelId, title, position }: { courseId: number; levelId: number; title: string; position: number }) {
  const navigate = useNavigate()
  const lessons = useLevelLessons(levelId)
  const canManage = useCanManageContent()
  const [lessonOpen, setLessonOpen] = useState(false)

  return (
    <section className="rounded-2xl border border-border bg-surface p-4">
      <div className="mb-3 flex items-center justify-between gap-2">
        <h2 className="font-display text-lg font-bold text-foreground">
          <span className="text-subtle">{position}.</span> {title}
        </h2>
        <div className="flex items-center gap-1">
          <Button
            size="sm"
            variant="ghost"
            onClick={() => window.open(`/courses/${courseId}/levels/${levelId}/preview`, '_blank', 'noopener')}
          >
            👁 Preview
          </Button>
          {canManage && (
            <Button size="sm" variant="ghost" onClick={() => setLessonOpen(true)}>
              + Lesson
            </Button>
          )}
        </div>
      </div>

      {lessons.isLoading ? (
        <Skeleton className="h-12" />
      ) : (lessons.data?.length ?? 0) === 0 ? (
        <p className="px-1 text-sm text-muted">No lessons yet.</p>
      ) : (
        <ul className="flex flex-col gap-2">
          {lessons.data?.map((lesson) => (
            <li key={lesson.id}>
              <button
                onClick={() => navigate(`/courses/${courseId}/lessons/${lesson.id}`)}
                className="flex w-full items-center justify-between gap-3 rounded-xl border border-border px-3 py-2.5 text-left hover:bg-surface-muted"
              >
                <span className="font-medium text-foreground">
                  {lesson.position}. {lesson.title}
                </span>
                <span className="flex items-center gap-2">
                  <Badge variant={lesson.is_published ? 'success' : 'neutral'}>
                    {lesson.is_published ? 'Published' : 'Draft'}
                  </Badge>
                  <Icon name="chevron" className="size-4 -rotate-90 text-muted" />
                </span>
              </button>
            </li>
          ))}
        </ul>
      )}

      <NewLessonModal levelId={levelId} open={lessonOpen} onClose={() => setLessonOpen(false)} />
    </section>
  )
}

function NewLevelModal({ courseId, open, onClose }: { courseId: number; open: boolean; onClose: () => void }) {
  const createLevel = useCreateLevel(courseId)
  const [title, setTitle] = useState('')
  const [error, setError] = useState<string | null>(null)

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setError(null)
    try {
      await createLevel.mutateAsync({ title })
      setTitle('')
      onClose()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not add the level.')
    }
  }

  return (
    <Modal open={open} onClose={onClose} title="Add level" description="A unit that groups lessons.">
      <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
        {error && <Alert variant="danger">{error}</Alert>}
        <Input label="Level title" value={title} onChange={(e) => setTitle(e.target.value)} placeholder="Unit 1 — Greetings" autoFocus required />
        <div className="flex gap-2">
          <Button type="button" variant="secondary" fullWidth onClick={onClose}>Cancel</Button>
          <Button type="submit" fullWidth loading={createLevel.isPending}>Add level</Button>
        </div>
      </form>
    </Modal>
  )
}

function NewLessonModal({ levelId, open, onClose }: { levelId: number; open: boolean; onClose: () => void }) {
  const createLesson = useCreateLesson(levelId)
  const [title, setTitle] = useState('')
  const [minutes, setMinutes] = useState('5')
  const [error, setError] = useState<string | null>(null)

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setError(null)
    try {
      await createLesson.mutateAsync({ title, est_minutes: minutes ? Number(minutes) : undefined })
      setTitle('')
      onClose()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not add the lesson.')
    }
  }

  return (
    <Modal open={open} onClose={onClose} title="Add lesson" description="A lesson holds the video, quiz and activity steps.">
      <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
        {error && <Alert variant="danger">{error}</Alert>}
        <Input label="Lesson title" value={title} onChange={(e) => setTitle(e.target.value)} placeholder="Saying hello" autoFocus required />
        <Input label="Estimated minutes" type="number" min={1} value={minutes} onChange={(e) => setMinutes(e.target.value)} />
        <div className="flex gap-2">
          <Button type="button" variant="secondary" fullWidth onClick={onClose}>Cancel</Button>
          <Button type="submit" fullWidth loading={createLesson.isPending}>Add lesson</Button>
        </div>
      </form>
    </Modal>
  )
}
