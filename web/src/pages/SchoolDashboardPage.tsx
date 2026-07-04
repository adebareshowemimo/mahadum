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
  Skeleton,
} from '@/components/ui'
import { ApiError } from '@/lib/api'
import { formatMoney } from '@/lib/format'
import { SchoolGate } from '@/components/school/SchoolGate'
import { useClasses, useCreateClass, useSchoolDashboard } from '@/lib/school/queries'

export function SchoolDashboardPage() {
  return <SchoolGate>{(orgId) => <Dashboard orgId={orgId} />}</SchoolGate>
}

function Dashboard({ orgId }: { orgId: number }) {
  const { data, isLoading, isError } = useSchoolDashboard(orgId)
  const classes = useClasses()
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
                <CardBody className="flex flex-col gap-1">
                  <div className="flex items-center justify-between">
                    <p className="font-semibold text-foreground">{c.name}</p>
                    {c.level && <Badge variant="info">{c.level}</Badge>}
                  </div>
                  <p className="text-sm text-muted">{c.teacher ?? 'No teacher'} · {c.students} students</p>
                </CardBody>
              </Card>
            ))}
          </div>
        )}
      </section>

      <NewClassModal open={newOpen} onClose={() => setNewOpen(false)} />
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

function NewClassModal({ open, onClose }: { open: boolean; onClose: () => void }) {
  const createClass = useCreateClass()
  const [values, setValues] = useState({ name: '', level: '' })
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})
  const [formError, setFormError] = useState<string | null>(null)

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setFieldErrors({})
    setFormError(null)
    try {
      await createClass.mutateAsync({ name: values.name, level: values.level || undefined })
      setValues({ name: '', level: '' })
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
