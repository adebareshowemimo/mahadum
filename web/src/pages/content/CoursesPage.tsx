import { useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
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
import { ApiError, type CreateCourseInput } from '@/lib/api'
import { useConfig } from '@/lib/config/useConfig'
import { useAuthorCourses, useCreateCourse } from '@/lib/content/queries'
import { useCanManageContent } from '@/lib/content/permissions'

export function CoursesPage() {
  const navigate = useNavigate()
  const { data, isLoading, isError } = useAuthorCourses()
  const canManage = useCanManageContent()
  const [open, setOpen] = useState(false)

  if (isLoading) return <Skeleton className="h-48" />
  if (isError || !data) return <Alert variant="danger">Couldn’t load courses.</Alert>

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

      {data.length === 0 ? (
        <Card>
          <CardBody className="py-10 text-center text-sm text-muted">
            No courses yet. Create your first to start adding content.
          </CardBody>
        </Card>
      ) : (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {data.map((c) => (
            <button key={c.id} onClick={() => navigate(`/courses/${c.id}`)} className="text-left">
              <Card className="h-full transition-colors hover:bg-surface-muted">
                <CardBody className="flex h-full flex-col gap-2">
                  <div className="flex items-start justify-between gap-2">
                    <p className="font-semibold text-foreground">{c.title}</p>
                    <Badge variant={c.is_published ? 'success' : 'neutral'}>
                      {c.is_published ? 'Published' : 'Draft'}
                    </Badge>
                  </div>
                  {c.description && <p className="line-clamp-2 text-sm text-muted">{c.description}</p>}
                  <span className="mt-auto text-xs uppercase text-subtle">
                    {c.language ?? '—'}{c.level_band ? ` · ${c.level_band}` : ''}
                  </span>
                </CardBody>
              </Card>
            </button>
          ))}
        </div>
      )}

      <NewCourseModal open={open} onClose={() => setOpen(false)} onCreated={(id) => navigate(`/courses/${id}`)} />
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
