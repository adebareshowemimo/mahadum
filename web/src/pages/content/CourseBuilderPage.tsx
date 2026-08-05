import { useState, type FormEvent } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import {
  Alert,
  Badge,
  Button,
  Card,
  CardBody,
  Icon,
  IconButton,
  Input,
  Modal,
  Skeleton,
} from '@/components/ui'
import { ApiError, type AuthorLesson, type AuthorLevel } from '@/lib/api'
import {
  useAuthorCourses,
  useCourseLevels,
  useCreateLesson,
  useCreateLevel,
  useDeleteLesson,
  useDeleteLevel,
  useLevelLessons,
  useReorderLessons,
  useReorderLevels,
  useUpdateLesson,
  useUpdateLevel,
} from '@/lib/content/queries'
import { useCanManageContent } from '@/lib/content/permissions'

export function CourseBuilderPage() {
  const { courseId } = useParams()
  const id = Number(courseId)
  const courses = useAuthorCourses()
  const levels = useCourseLevels(id)
  const canManage = useCanManageContent()
  const reorderLevels = useReorderLevels(id)
  const [levelOpen, setLevelOpen] = useState(false)
  const [editingLevel, setEditingLevel] = useState<AuthorLevel | null>(null)
  const [deletingLevel, setDeletingLevel] = useState<AuthorLevel | null>(null)

  const course = courses.data?.find((c) => c.id === id)

  function moveLevel(index: number, direction: -1 | 1) {
    const ordered = levels.data
    if (!ordered) return
    const target = index + direction
    if (target < 0 || target >= ordered.length) return
    const next = [...ordered]
    ;[next[index], next[target]] = [next[target], next[index]]
    reorderLevels.mutate(next.map((l) => l.id))
  }

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
          {levels.data.map((level, index) => (
            <LevelSection
              key={level.id}
              courseId={id}
              level={level}
              onEdit={() => setEditingLevel(level)}
              onDelete={() => setDeletingLevel(level)}
              canMoveUp={canManage && index > 0}
              canMoveDown={canManage && index < levels.data.length - 1}
              onMoveUp={() => moveLevel(index, -1)}
              onMoveDown={() => moveLevel(index, 1)}
            />
          ))}
        </div>
      )}

      <NewLevelModal courseId={id} open={levelOpen} onClose={() => setLevelOpen(false)} />
      <EditLevelModal key={editingLevel?.id ?? 'edit-level'} courseId={id} level={editingLevel} onClose={() => setEditingLevel(null)} />
      <DeleteLevelModal courseId={id} level={deletingLevel} onClose={() => setDeletingLevel(null)} />
    </div>
  )
}

function LevelSection({
  courseId,
  level,
  onEdit,
  onDelete,
  canMoveUp,
  canMoveDown,
  onMoveUp,
  onMoveDown,
}: {
  courseId: number
  level: AuthorLevel
  onEdit: () => void
  onDelete: () => void
  canMoveUp: boolean
  canMoveDown: boolean
  onMoveUp: () => void
  onMoveDown: () => void
}) {
  const navigate = useNavigate()
  const lessons = useLevelLessons(level.id)
  const canManage = useCanManageContent()
  const reorderLessons = useReorderLessons(level.id)
  const [lessonOpen, setLessonOpen] = useState(false)
  const [editingLesson, setEditingLesson] = useState<AuthorLesson | null>(null)
  const [deletingLesson, setDeletingLesson] = useState<AuthorLesson | null>(null)

  function moveLesson(index: number, direction: -1 | 1) {
    const ordered = lessons.data
    if (!ordered) return
    const target = index + direction
    if (target < 0 || target >= ordered.length) return
    const next = [...ordered]
    ;[next[index], next[target]] = [next[target], next[index]]
    reorderLessons.mutate(next.map((l) => l.id))
  }

  return (
    <section className="rounded-2xl border border-border bg-surface p-4">
      <div className="mb-3 flex items-center justify-between gap-2">
        <h2 className="font-display text-lg font-bold text-foreground">
          <span className="text-subtle">{level.position}.</span> {level.title}
        </h2>
        <div className="flex items-center gap-1">
          {canManage && (
            <div className="mr-1 flex items-center gap-0.5">
              <IconButton
                aria-label="Move unit up"
                size="sm"
                variant="ghost"
                disabled={!canMoveUp}
                onClick={onMoveUp}
              >
                <Icon name="chevron" className="size-4 rotate-180" />
              </IconButton>
              <IconButton
                aria-label="Move unit down"
                size="sm"
                variant="ghost"
                disabled={!canMoveDown}
                onClick={onMoveDown}
              >
                <Icon name="chevron" className="size-4" />
              </IconButton>
            </div>
          )}
          <Button
            size="sm"
            variant="ghost"
            onClick={() => window.open(`/courses/${courseId}/levels/${level.id}/preview`, '_blank', 'noopener')}
          >
            👁 Preview
          </Button>
          {canManage && (
            <>
              <Button size="sm" variant="ghost" onClick={() => setLessonOpen(true)}>
                + Lesson
              </Button>
              <Button size="sm" variant="ghost" onClick={onEdit}>
                Edit
              </Button>
              <Button size="sm" variant="danger" onClick={onDelete}>
                Delete
              </Button>
            </>
          )}
        </div>
      </div>

      {lessons.isLoading ? (
        <Skeleton className="h-12" />
      ) : (lessons.data?.length ?? 0) === 0 ? (
        <p className="px-1 text-sm text-muted">No lessons yet.</p>
      ) : (
        <ul className="flex flex-col gap-2">
          {lessons.data?.map((lesson, index) => (
            <li key={lesson.id}>
              <div className="flex w-full items-center justify-between gap-3 rounded-xl border border-border px-3 py-2.5 hover:bg-surface-muted">
                {canManage && (
                  <div className="flex shrink-0 items-center gap-0.5">
                    <IconButton
                      aria-label="Move lesson up"
                      size="sm"
                      variant="ghost"
                      disabled={index === 0}
                      onClick={() => moveLesson(index, -1)}
                    >
                      <Icon name="chevron" className="size-4 rotate-180" />
                    </IconButton>
                    <IconButton
                      aria-label="Move lesson down"
                      size="sm"
                      variant="ghost"
                      disabled={index === (lessons.data?.length ?? 0) - 1}
                      onClick={() => moveLesson(index, 1)}
                    >
                      <Icon name="chevron" className="size-4" />
                    </IconButton>
                  </div>
                )}
                <button
                  onClick={() => navigate(`/courses/${courseId}/lessons/${lesson.id}`)}
                  className="flex flex-1 items-center justify-between gap-3 text-left"
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
                {canManage && (
                  <div className="flex shrink-0 items-center gap-1">
                    <Button size="sm" variant="ghost" onClick={() => setEditingLesson(lesson)}>
                      Edit
                    </Button>
                    <Button size="sm" variant="danger" onClick={() => setDeletingLesson(lesson)}>
                      Delete
                    </Button>
                  </div>
                )}
              </div>
            </li>
          ))}
        </ul>
      )}

      <NewLessonModal levelId={level.id} open={lessonOpen} onClose={() => setLessonOpen(false)} />
      <EditLessonModal
        key={editingLesson?.id ?? 'edit-lesson'}
        levelId={level.id}
        lesson={editingLesson}
        onClose={() => setEditingLesson(null)}
      />
      <DeleteLessonModal levelId={level.id} lesson={deletingLesson} onClose={() => setDeletingLesson(null)} />
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

function EditLevelModal({ courseId, level, onClose }: { courseId: number; level: AuthorLevel | null; onClose: () => void }) {
  const updateLevel = useUpdateLevel(courseId)
  const [title, setTitle] = useState(level?.title ?? '')
  const [error, setError] = useState<string | null>(null)

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    if (!level) return
    setError(null)
    try {
      await updateLevel.mutateAsync({ levelId: level.id, input: { title } })
      onClose()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not update the level.')
    }
  }

  return (
    <Modal
      open={level != null}
      onClose={onClose}
      title="Edit level"
      description="Update this unit’s title."
    >
      <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
        {error && <Alert variant="danger">{error}</Alert>}
        <Input
          label="Level title"
          value={title}
          onChange={(e) => setTitle(e.target.value)}
          autoFocus
          required
        />
        <div className="flex gap-2">
          <Button type="button" variant="secondary" fullWidth onClick={onClose}>Cancel</Button>
          <Button type="submit" fullWidth loading={updateLevel.isPending}>Save changes</Button>
        </div>
      </form>
    </Modal>
  )
}

function DeleteLevelModal({ courseId, level, onClose }: { courseId: number; level: AuthorLevel | null; onClose: () => void }) {
  const deleteLevel = useDeleteLevel(courseId)
  const [error, setError] = useState<string | null>(null)

  async function onConfirm() {
    if (!level) return
    setError(null)
    try {
      await deleteLevel.mutateAsync(level.id)
      onClose()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not delete the level.')
    }
  }

  return (
    <Modal
      open={level != null}
      onClose={onClose}
      title="Delete level?"
      description={level ? `“${level.title}” and its lessons will be removed.` : undefined}
    >
      <div className="flex flex-col gap-4">
        {error && <Alert variant="danger">{error}</Alert>}
        <div className="flex gap-2">
          <Button type="button" variant="secondary" fullWidth onClick={onClose}>Cancel</Button>
          <Button type="button" variant="danger" fullWidth loading={deleteLevel.isPending} onClick={onConfirm}>
            Delete
          </Button>
        </div>
      </div>
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

function EditLessonModal({ levelId, lesson, onClose }: { levelId: number; lesson: AuthorLesson | null; onClose: () => void }) {
  const updateLesson = useUpdateLesson(levelId)
  const [title, setTitle] = useState(lesson?.title ?? '')
  const [minutes, setMinutes] = useState(String(lesson?.est_minutes ?? ''))
  const [error, setError] = useState<string | null>(null)

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    if (!lesson) return
    setError(null)
    try {
      await updateLesson.mutateAsync({
        lessonId: lesson.id,
        input: { title, est_minutes: minutes ? Number(minutes) : undefined },
      })
      onClose()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not update the lesson.')
    }
  }

  return (
    <Modal
      open={lesson != null}
      onClose={onClose}
      title="Edit lesson"
      description="Update this lesson’s title and length."
    >
      <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
        {error && <Alert variant="danger">{error}</Alert>}
        <Input
          label="Lesson title"
          value={title}
          onChange={(e) => setTitle(e.target.value)}
          autoFocus
          required
        />
        <Input
          label="Estimated minutes"
          type="number"
          min={1}
          value={minutes}
          onChange={(e) => setMinutes(e.target.value)}
        />
        <div className="flex gap-2">
          <Button type="button" variant="secondary" fullWidth onClick={onClose}>Cancel</Button>
          <Button type="submit" fullWidth loading={updateLesson.isPending}>Save changes</Button>
        </div>
      </form>
    </Modal>
  )
}

function DeleteLessonModal({ levelId, lesson, onClose }: { levelId: number; lesson: AuthorLesson | null; onClose: () => void }) {
  const deleteLesson = useDeleteLesson(levelId)
  const [error, setError] = useState<string | null>(null)

  async function onConfirm() {
    if (!lesson) return
    setError(null)
    try {
      await deleteLesson.mutateAsync(lesson.id)
      onClose()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not delete the lesson.')
    }
  }

  return (
    <Modal
      open={lesson != null}
      onClose={onClose}
      title="Delete lesson?"
      description={lesson ? `“${lesson.title}” will be removed.` : undefined}
    >
      <div className="flex flex-col gap-4">
        {error && <Alert variant="danger">{error}</Alert>}
        <div className="flex gap-2">
          <Button type="button" variant="secondary" fullWidth onClick={onClose}>Cancel</Button>
          <Button type="button" variant="danger" fullWidth loading={deleteLesson.isPending} onClick={onConfirm}>
            Delete
          </Button>
        </div>
      </div>
    </Modal>
  )
}
