import { useMemo, useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { AdminToolbar, DataTable, FilterSelect, type Column } from '@/components/admin'
import {
  Alert,
  Badge,
  Button,
  Icon,
  Input,
  Modal,
} from '@/components/ui'
import {
  ApiError,
  type AdminCoursesQuery,
  type CoursePublishResult,
  type CourseSummary,
  type CreateCourseInput,
  type PublishedLesson,
} from '@/lib/api'
import { useConfig } from '@/lib/config/useConfig'
import {
  useAdminCourses,
  useCreateCourse,
  useDeleteCourse,
  useSetCourseArchived,
  useSetCoursePublished,
} from '@/lib/content/queries'
import { useCanManageContent } from '@/lib/content/permissions'

export function CoursesPage() {
  const navigate = useNavigate()
  const canManage = useCanManageContent()
  const [open, setOpen] = useState(false)
  const [search, setSearch] = useState('')
  const [language, setLanguage] = useState('')
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const [actionError, setActionError] = useState<string | null>(null)
  const [actingId, setActingId] = useState<number | null>(null)
  const [deleting, setDeleting] = useState<CourseSummary | null>(null)
  // Outcome of the last cascade publish: what went live, and what was skipped.
  const [publishReport, setPublishReport] = useState<(CoursePublishResult & { courseTitle: string }) | null>(null)
  const [blockedDetails, setBlockedDetails] = useState<PublishedLesson[]>([])

  const params: AdminCoursesQuery = useMemo(
    () => ({ q: search || undefined, language: language || undefined, status: status || undefined, page }),
    [search, language, status, page],
  )
  const { data, isLoading, isError, isFetching } = useAdminCourses(params)
  const { data: config } = useConfig()
  const setArchived = useSetCourseArchived()
  const setPublished = useSetCoursePublished()

  const languageOptions = (config?.languages ?? []).map((l) => ({ label: l.name, value: l.code }))

  function onFilter(setter: (v: string) => void) {
    return (v: string) => {
      setter(v)
      setPage(1)
    }
  }

  async function toggleArchive(course: CourseSummary) {
    setActionError(null)
    setActingId(course.id)
    try {
      await setArchived.mutateAsync({ courseId: course.id, archive: course.status !== 'archived' })
    } catch (err) {
      setActionError(err instanceof ApiError ? err.message : 'Could not update the course.')
    } finally {
      setActingId(null)
    }
  }

  async function togglePublish(course: CourseSummary) {
    setActionError(null)
    setPublishReport(null)
    setActingId(course.id)
    try {
      const result = await setPublished.mutateAsync({ courseId: course.id, publish: !course.is_published })
      if (course.is_published) return // unpublishing cascades nothing to report
      setPublishReport({ ...result, courseTitle: course.title })
    } catch (err) {
      // A failed publish carries a per-lesson reason list; show it instead of
      // the bare message so the author knows what to fix.
      setActionError(err instanceof ApiError ? err.message : 'Could not update the course.')
      setBlockedDetails(err instanceof ApiError ? blockedFrom(err) : [])
    } finally {
      setActingId(null)
    }
  }

  const columns: Column<CourseSummary>[] = [
    {
      key: 'title',
      header: 'Course',
      render: (c) => (
        <div>
          <p className="font-semibold text-foreground">{c.title}</p>
          <p className="text-xs uppercase text-muted">
            {c.language ?? '—'} · {c.level_band ?? 'no band'} · {c.levels_count ?? 0} levels
          </p>
        </div>
      ),
    },
    { key: 'owner', header: 'Owner', hideOnMobile: true, render: (c) => c.owner ?? '—' },
    {
      key: 'status',
      header: 'Status',
      render: (c) => (
        <Badge variant={c.status === 'archived' ? 'neutral' : c.is_published ? 'success' : 'neutral'}>
          {c.status === 'archived' ? 'archived' : c.is_published ? 'published' : 'draft'}
        </Badge>
      ),
    },
    {
      key: 'actions',
      header: '',
      className: 'text-right',
      render: (c) =>
        canManage ? (
          <div className="flex justify-end gap-2">
            <Button size="sm" variant="ghost" onClick={() => navigate(`/courses/${c.id}`)}>
              Edit
            </Button>
            {c.status !== 'archived' && (
              <Button
                size="sm"
                variant={c.is_published ? 'ghost' : 'parent'}
                loading={actingId === c.id && setPublished.isPending}
                onClick={() => togglePublish(c)}
              >
                {c.is_published ? 'Unpublish' : 'Publish'}
              </Button>
            )}
            <Button
              size="sm"
              variant="ghost"
              loading={actingId === c.id && setArchived.isPending}
              onClick={() => toggleArchive(c)}
            >
              {c.status === 'archived' ? 'Unarchive' : 'Archive'}
            </Button>
            <Button size="sm" variant="danger" onClick={() => setDeleting(c)}>
              Delete
            </Button>
          </div>
        ) : null,
    },
  ]

  if (isError) return <Alert variant="danger">Couldn’t load courses.</Alert>

  const meta = data?.meta

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="font-display text-2xl font-bold text-foreground">Courses</h1>
          <p className="mt-1 text-muted">Build courses, levels, lessons, videos and quizzes.</p>
        </div>
        {canManage && (
          <Button leftIcon={<Icon name="layers" className="size-[18px]" />} onClick={() => setOpen(true)}>
            New course
          </Button>
        )}
      </div>

      {actionError && (
        <Alert variant="danger">
          <p>{actionError}</p>
          <LessonReasons lessons={blockedDetails} />
        </Alert>
      )}

      {publishReport && (
        <Alert variant={publishReport.lessons_blocked.length > 0 ? 'warning' : 'success'}>
          <p>
            <strong>{publishReport.courseTitle}</strong> is published
            {publishReport.lessons_published.length > 0
              ? `, along with ${publishReport.lessons_published.length} draft lesson${
                  publishReport.lessons_published.length === 1 ? '' : 's'
                }.`
              : '.'}
          </p>
          {publishReport.lessons_blocked.length > 0 && (
            <>
              <p className="mt-2">
                These lessons stayed in draft because they aren’t ready for learners yet:
              </p>
              <LessonReasons lessons={publishReport.lessons_blocked} />
            </>
          )}
        </Alert>
      )}

      <DataTable
        columns={columns}
        rows={data?.data ?? []}
        getRowId={(c) => c.id}
        isLoading={isLoading}
        empty="No courses match your filters."
        toolbar={
          <AdminToolbar search={search} onSearch={(v) => { setSearch(v); setPage(1) }} searchPlaceholder="Search course title…">
            <FilterSelect label="Language" value={language} onChange={onFilter(setLanguage)} options={languageOptions} allLabel="All languages" />
            <FilterSelect
              label="Status"
              value={status}
              onChange={onFilter(setStatus)}
              options={[
                { label: 'Published', value: 'published' },
                { label: 'Draft', value: 'draft' },
                { label: 'Archived', value: 'archived' },
              ]}
              allLabel="All statuses"
            />
          </AdminToolbar>
        }
      />

      {meta && meta.total > 0 && (
        <div className="flex items-center justify-between text-sm text-muted">
          <span>
            Page {meta.current_page} of {meta.last_page} · {meta.total} courses
          </span>
          <div className="flex gap-2">
            <Button size="sm" variant="ghost" disabled={meta.current_page <= 1 || isFetching} onClick={() => setPage((p) => p - 1)}>
              Previous
            </Button>
            <Button size="sm" variant="ghost" disabled={meta.current_page >= meta.last_page || isFetching} onClick={() => setPage((p) => p + 1)}>
              Next
            </Button>
          </div>
        </div>
      )}

      <NewCourseModal open={open} onClose={() => setOpen(false)} onCreated={(id) => navigate(`/courses/${id}`)} />
      <DeleteCourseModal course={deleting} onClose={() => setDeleting(null)} />
    </div>
  )
}

function NewCourseModal({ open, onClose, onCreated }: { open: boolean; onClose: () => void; onCreated: (id: number) => void }) {
  const createCourse = useCreateCourse()
  const { data: config } = useConfig()
  const languages = config?.languages ?? []
  const [values, setValues] = useState({ language_id: '', title: '', description: '', level_band: '' })
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})
  const [formError, setFormError] = useState<string | null>(null)

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setFieldErrors({})
    setFormError(null)
    try {
      const payload: CreateCourseInput = {
        language_id: Number(values.language_id),
        title: values.title,
        description: values.description || undefined,
        level_band: values.level_band || undefined,
      }
      const course = await createCourse.mutateAsync(payload)
      onCreated(course.id)
    } catch (err) {
      if (err instanceof ApiError) {
        setFieldErrors(err.fieldErrors)
        if (!Object.keys(err.fieldErrors).length) setFormError(err.message)
      } else setFormError('Something went wrong.')
    }
  }

  return (
    <Modal open={open} onClose={onClose} title="New course" description="Pick a language and name your course.">
      <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
        {formError && <Alert variant="danger">{formError}</Alert>}
        <label className="flex flex-col gap-1.5">
          <span className="text-sm font-semibold text-foreground">Language</span>
          <select
            value={values.language_id}
            onChange={(e) => setValues((v) => ({ ...v, language_id: e.target.value }))}
            required
            className="h-11 rounded-xl border border-border-strong bg-surface px-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
          >
            <option value="">Choose a language</option>
            {languages.map((l) => (
              <option key={l.id} value={l.id}>{l.name}</option>
            ))}
          </select>
          {fieldErrors.language_id && <p className="text-xs font-medium text-danger">{fieldErrors.language_id}</p>}
        </label>
        <Input
          label="Title"
          value={values.title}
          onChange={(e) => setValues((v) => ({ ...v, title: e.target.value }))}
          error={fieldErrors.title}
          autoFocus
          required
        />
        <Input
          label="Level band (optional)"
          placeholder="e.g. A1"
          value={values.level_band}
          onChange={(e) => setValues((v) => ({ ...v, level_band: e.target.value }))}
          error={fieldErrors.level_band}
        />
        <Input
          label="Description (optional)"
          value={values.description}
          onChange={(e) => setValues((v) => ({ ...v, description: e.target.value }))}
          error={fieldErrors.description}
        />
        <div className="flex gap-2">
          <Button type="button" variant="secondary" fullWidth onClick={onClose}>Cancel</Button>
          <Button type="submit" fullWidth loading={createCourse.isPending}>Create course</Button>
        </div>
      </form>
    </Modal>
  )
}

function DeleteCourseModal({ course, onClose }: { course: CourseSummary | null; onClose: () => void }) {
  const deleteCourse = useDeleteCourse()
  const [error, setError] = useState<string | null>(null)

  async function onConfirm() {
    if (!course) return
    setError(null)
    try {
      await deleteCourse.mutateAsync(course.id)
      onClose()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not delete the course.')
    }
  }

  return (
    <Modal
      open={course != null}
      onClose={onClose}
      title="Delete course?"
      description={course ? `“${course.title}” will be removed from the catalogue.` : undefined}
    >
      <div className="flex flex-col gap-4">
        {error && <Alert variant="danger">{error}</Alert>}
        <div className="flex gap-2">
          <Button type="button" variant="secondary" fullWidth onClick={onClose}>Cancel</Button>
          <Button type="button" variant="danger" fullWidth loading={deleteCourse.isPending} onClick={onConfirm}>
            Delete
          </Button>
        </div>
      </div>
    </Modal>
  )
}

/**
 * Pulls the per-lesson publish failures out of a 422. The API sends them as
 * `error.details`; anything else (network error, plain message) yields none.
 */
function blockedFrom(err: ApiError): PublishedLesson[] {
  return Array.isArray(err.details) ? (err.details as PublishedLesson[]) : []
}

/** Lesson-by-lesson list of why a publish was skipped. */
function LessonReasons({ lessons }: { lessons: PublishedLesson[] }) {
  if (lessons.length === 0) return null

  return (
    <ul className="mt-2 flex flex-col gap-2 text-sm">
      {lessons.map((lesson) => (
        <li key={lesson.lesson_id}>
          <span className="font-semibold">{lesson.title || `Lesson #${lesson.lesson_id}`}</span>
          <ul className="ml-4 list-disc">
            {(lesson.reasons ?? []).map((reason) => (
              <li key={reason}>{reason}</li>
            ))}
          </ul>
        </li>
      ))}
    </ul>
  )
}
