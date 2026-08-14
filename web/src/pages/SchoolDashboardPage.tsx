import { useState, type FormEvent } from 'react'
import { Link } from 'react-router-dom'
import {
  Alert,
  Badge,
  Button,
  Card,
  CardBody,
  Icon,
  Input,
  Modal,
  LinkButton,
  Skeleton,
} from '@/components/ui'
import { ApiError, type SchoolTeacher } from '@/lib/api'
import { formatMoney } from '@/lib/format'
import { SchoolGate } from '@/components/school/SchoolGate'
import { useClasses, useCreateClass, useSchoolDashboard, useTeachers, useUpdateClass } from '@/lib/school/queries'

export function SchoolDashboardPage() {
  return <SchoolGate>{(orgId) => <Dashboard orgId={orgId} />}</SchoolGate>
}

function Dashboard({ orgId }: { orgId: number }) {
  const { data, isLoading, isError } = useSchoolDashboard(orgId)
  const classes = useClasses()
  const teachers = useTeachers(orgId)
  const [newOpen, setNewOpen] = useState(false)

  if (isLoading) return <Skeleton className="h-40" />
  if (isError || !data) return <Alert variant="danger">Couldn’t load the school dashboard.</Alert>

  const seatsPct = data.seats.purchased > 0 ? Math.round((data.seats.filled / data.seats.purchased) * 100) : 0

  return (
    <div className="flex flex-col gap-8">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="font-display text-2xl font-bold text-foreground">{data.organization.name}</h1>
          <p className="mt-1 capitalize text-muted">{data.organization.status}</p>
        </div>
        <Button variant="parent" leftIcon={<Icon name="cap" className="size-[18px]" />} onClick={() => setNewOpen(true)}>
          New class
        </Button>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Kpi icon="cap" label="Classes" value={data.classes} />
        <Kpi icon="users" label="Students" value={data.students} />
        <Kpi icon="layers" label="Seats filled" value={`${data.seats.filled}/${data.seats.purchased}`} sub={`${seatsPct}% used`} />
        <Kpi
          icon="card"
          label="Unpaid invoices"
          value={data.invoices.unpaid}
          sub={formatMoney(data.invoices.unpaid_minor, 'NGN')}
        />
      </div>

      <Card>
        <CardBody className="flex flex-wrap items-center gap-x-8 gap-y-2">
          <div>
            <p className="text-xs text-muted">Subscription</p>
            <p className="font-semibold capitalize text-foreground">{data.subscription.status ?? 'None'}</p>
          </div>
          <div>
            <p className="text-xs text-muted">Last payment</p>
            <p className="font-semibold text-foreground">
              {data.subscription.last_payment_at ? new Date(data.subscription.last_payment_at).toLocaleDateString() : '—'}
            </p>
          </div>
        </CardBody>
      </Card>

      <section>
        <div className="mb-3 flex items-center justify-between">
          <h2 className="font-display text-lg font-bold text-foreground">Classes</h2>
          <Link to="/roster" className="text-sm font-semibold text-primary hover:underline">
            Import roster →
          </Link>
        </div>
        {classes.isLoading ? (
          <Skeleton className="h-24" />
        ) : (classes.data?.length ?? 0) === 0 ? (
          <Card>
            <CardBody className="py-8 text-center text-sm text-muted">
              No classes yet. Create one to start enrolling students.
            </CardBody>
          </Card>
        ) : (
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {classes.data?.map((c) => (
              <Card key={c.id}>
                <CardBody className="flex flex-col gap-2">
                  <div className="flex items-center justify-between">
                    <p className="font-semibold text-foreground">{c.name}</p>
                    {c.level && <Badge variant="info">{c.level}</Badge>}
                  </div>
                  <p className="text-sm text-muted">{c.teacher ?? 'No teacher'} · {c.students} students</p>
                  <AssignTeacher classId={c.id} teachers={teachers.data} />
                  <LinkButton to={`/classes/${c.id}`} variant="outline" size="sm" className="mt-1 self-start">Open class</LinkButton>
                </CardBody>
              </Card>
            ))}
          </div>
        )}
      </section>

      <NewClassModal open={newOpen} onClose={() => setNewOpen(false)} orgId={orgId} />
    </div>
  )
}

function Kpi({ icon, label, value, sub }: { icon: 'cap' | 'users' | 'layers' | 'card'; label: string; value: string | number; sub?: string }) {
  return (
    <Card>
      <CardBody className="flex items-center gap-3">
        <span className="flex size-11 items-center justify-center rounded-xl bg-primary-soft text-primary">
          <Icon name={icon} />
        </span>
        <div>
          <p className="font-display text-2xl font-bold text-foreground">{value}</p>
          <p className="text-xs text-muted">{label}</p>
          {sub && <p className="text-xs text-subtle">{sub}</p>}
        </div>
      </CardBody>
    </Card>
  )
}

/** Inline "assign a teacher" control shown on each class card so a school
 * admin can hook up a teacher after the fact — without this, classes created
 * without a teacher never appear in that teacher's `useMyClasses()`, and
 * they see "no classes assigned" with no way to fix it themselves. */
function AssignTeacher({ classId, teachers }: { classId: number; teachers: SchoolTeacher[] | undefined }) {
  const updateClass = useUpdateClass()
  const [teacherId, setTeacherId] = useState('')

  if (!teachers || teachers.length === 0) return null

  return (
    <div className="flex items-center gap-2">
      <select
        value={teacherId}
        onChange={(e) => setTeacherId(e.target.value)}
        className="h-9 flex-1 rounded-lg border border-border-strong bg-surface px-2 text-xs text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
      >
        <option value="">Assign teacher…</option>
        {teachers.map((t) => (
          <option key={t.id} value={t.id}>
            {t.name}
          </option>
        ))}
      </select>
      <Button
        variant="secondary"
        size="sm"
        disabled={!teacherId}
        loading={updateClass.isPending}
        onClick={() => updateClass.mutate({ classId, input: { teacher_user_id: Number(teacherId) } })}
      >
        Assign
      </Button>
    </div>
  )
}

function NewClassModal({ open, onClose, orgId }: { open: boolean; onClose: () => void; orgId: number }) {
  const createClass = useCreateClass()
  const teachers = useTeachers(orgId)
  const [values, setValues] = useState({ name: '', level: '', teacher_user_id: '' })
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})
  const [formError, setFormError] = useState<string | null>(null)

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setFieldErrors({})
    setFormError(null)
    try {
      await createClass.mutateAsync({
        name: values.name,
        level: values.level || undefined,
        teacher_user_id: values.teacher_user_id ? Number(values.teacher_user_id) : undefined,
      })
      setValues({ name: '', level: '', teacher_user_id: '' })
      onClose()
    } catch (err) {
      if (err instanceof ApiError) {
        setFieldErrors(err.fieldErrors)
        if (!Object.keys(err.fieldErrors).length) setFormError(err.message)
      } else {
        setFormError('Something went wrong. Please try again.')
      }
    }
  }

  return (
    <Modal open={open} onClose={onClose} title="New class" description="Create a class for this school.">
      <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
        {formError && <Alert variant="danger">{formError}</Alert>}
        <Input
          label="Class name"
          value={values.name}
          onChange={(e) => setValues((v) => ({ ...v, name: e.target.value }))}
          error={fieldErrors.name}
          autoFocus
          required
        />
        <Input
          label="Level (optional)"
          value={values.level}
          onChange={(e) => setValues((v) => ({ ...v, level: e.target.value }))}
          error={fieldErrors.level}
        />
        <label className="flex flex-col gap-1.5">
          <span className="text-sm font-semibold text-foreground">Teacher (optional)</span>
          <select
            value={values.teacher_user_id}
            onChange={(e) => setValues((v) => ({ ...v, teacher_user_id: e.target.value }))}
            className="h-11 rounded-xl border border-border-strong bg-surface px-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
          >
            <option value="">Assign later</option>
            {teachers.data?.map((t) => (
              <option key={t.id} value={t.id}>
                {t.name}
              </option>
            ))}
          </select>
          {fieldErrors.teacher_user_id && <p className="text-xs font-medium text-danger">{fieldErrors.teacher_user_id}</p>}
        </label>
        <div className="flex gap-2">
          <Button type="button" variant="secondary" fullWidth onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" variant="parent" fullWidth loading={createClass.isPending}>
            Create class
          </Button>
        </div>
      </form>
    </Modal>
  )
}
