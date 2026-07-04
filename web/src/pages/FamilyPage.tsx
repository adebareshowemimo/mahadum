import { useState, type FormEvent } from 'react'
import { Link } from 'react-router-dom'
import {
  Alert,
  Avatar,
  Badge,
  Button,
  Card,
  CardBody,
  CardHeader,
  CardTitle,
  CodeInput,
  Icon,
  Input,
  Modal,
  Skeleton,
} from '@/components/ui'
import { ApiError } from '@/lib/api'
import { formatMoney } from '@/lib/format'
import { useConfig } from '@/lib/config/useConfig'
import { useAddChild, useFamily, useSetPin } from '@/lib/family/queries'

export function FamilyPage() {
  const { data: family, isLoading, isError } = useFamily()
  const [addOpen, setAddOpen] = useState(false)
  const [pinOpen, setPinOpen] = useState(false)

  if (isLoading) return <PageSkeleton />
  if (isError || !family) {
    return <Alert variant="danger">We couldn’t load your family. Please refresh and try again.</Alert>
  }

  const atLimit = family.learners.length >= family.child_limit

  return (
    <div className="flex flex-col gap-8">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="font-display text-2xl font-bold text-foreground">{family.name}</h1>
          <p className="mt-1 text-muted">
            {family.learners.length} of {family.child_limit} profiles
          </p>
        </div>
        <Button variant="parent" leftIcon={<Icon name="users" className="size-[18px]" />} disabled={atLimit} onClick={() => setAddOpen(true)}>
          Add child
        </Button>
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Wallet</CardTitle>
          </CardHeader>
          <CardBody className="flex flex-col gap-3">
            <div className="flex items-baseline gap-2">
              <span className="text-3xl">🪙</span>
              <span className="font-display text-2xl font-bold text-foreground">
                {family.wallet.coin_balance.toLocaleString()}
              </span>
              <span className="text-muted">coins</span>
            </div>
            <p className="text-sm text-muted">
              Cash balance: {formatMoney(family.wallet.currency_minor, family.wallet.currency)}
            </p>
            <Link to="/wallet">
              <Button variant="secondary" size="sm">
                Manage wallet
              </Button>
            </Link>
          </CardBody>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Parental PIN</CardTitle>
          </CardHeader>
          <CardBody className="flex flex-col gap-3">
            <div className="flex items-center gap-2">
              <Icon name="shield" className="text-muted" />
              <Badge variant={family.pin_set ? 'success' : 'warning'}>
                {family.pin_set ? 'PIN set' : 'No PIN yet'}
              </Badge>
            </div>
            <p className="text-sm text-muted">
              A PIN protects child profiles when switching from a parent device.
            </p>
            <Button variant="outline" size="sm" onClick={() => setPinOpen(true)}>
              {family.pin_set ? 'Change PIN' : 'Set a PIN'}
            </Button>
          </CardBody>
        </Card>
      </div>

      <section>
        <h2 className="mb-3 font-display text-lg font-bold text-foreground">Children</h2>
        {family.learners.length === 0 ? (
          <Card>
            <CardBody className="flex flex-col items-center gap-3 py-10 text-center">
              <span className="text-4xl" aria-hidden="true">
                🧒
              </span>
              <p className="max-w-xs text-sm text-muted">
                No child profiles yet. Add your first one to start their learning journey.
              </p>
              <Button variant="parent" disabled={atLimit} onClick={() => setAddOpen(true)}>
                Add child
              </Button>
            </CardBody>
          </Card>
        ) : (
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {family.learners.map((l) => (
              <Card key={l.id}>
                <CardBody className="flex items-center gap-3">
                  <Avatar name={l.display_name} />
                  <div className="min-w-0">
                    <p className="truncate font-semibold text-foreground">{l.display_name}</p>
                    {l.is_child && <Badge variant="info">Child</Badge>}
                  </div>
                </CardBody>
              </Card>
            ))}
          </div>
        )}
      </section>

      <AddChildModal open={addOpen} onClose={() => setAddOpen(false)} />
      <SetPinModal open={pinOpen} onClose={() => setPinOpen(false)} hasPin={family.pin_set} />
    </div>
  )
}

function AddChildModal({ open, onClose }: { open: boolean; onClose: () => void }) {
  const addChild = useAddChild()
  const { data: config } = useConfig()
  const languages = config?.languages ?? []

  const [values, setValues] = useState({ display_name: '', date_of_birth: '', target_language_id: '' })
  const [consent, setConsent] = useState(false)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})
  const [formError, setFormError] = useState<string | null>(null)

  function reset() {
    setValues({ display_name: '', date_of_birth: '', target_language_id: '' })
    setConsent(false)
    setFieldErrors({})
    setFormError(null)
  }

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setFieldErrors({})
    setFormError(null)
    try {
      await addChild.mutateAsync({
        display_name: values.display_name,
        date_of_birth: values.date_of_birth || undefined,
        target_language_id: values.target_language_id ? Number(values.target_language_id) : undefined,
        consent,
      })
      reset()
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
    <Modal open={open} onClose={onClose} title="Add a child" description="Create a learner profile for your child.">
      <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
        {formError && <Alert variant="danger">{formError}</Alert>}
        <Input
          label="Display name"
          value={values.display_name}
          onChange={(e) => setValues((v) => ({ ...v, display_name: e.target.value }))}
          error={fieldErrors.display_name}
          autoFocus
          required
        />
        <Input
          label="Date of birth"
          type="date"
          max={new Date().toISOString().slice(0, 10)}
          value={values.date_of_birth}
          onChange={(e) => setValues((v) => ({ ...v, date_of_birth: e.target.value }))}
          error={fieldErrors.date_of_birth}
          hint="Used to apply the right privacy protections."
        />
        <label className="flex flex-col gap-1.5">
          <span className="text-sm font-semibold text-foreground">Language to learn</span>
          <select
            value={values.target_language_id}
            onChange={(e) => setValues((v) => ({ ...v, target_language_id: e.target.value }))}
            className="h-11 rounded-xl border border-border-strong bg-surface px-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
          >
            <option value="">Choose later</option>
            {languages.map((lang) => (
              <option key={lang.id} value={lang.id}>
                {lang.name}
              </option>
            ))}
          </select>
        </label>
        <label className="flex items-start gap-2.5 text-sm text-foreground">
          <input
            type="checkbox"
            checked={consent}
            onChange={(e) => setConsent(e.target.checked)}
            className="mt-0.5 size-4 rounded border-border-strong text-primary focus:ring-ring"
          />
          <span>
            I am the parent or legal guardian and I consent to creating and managing this child’s account.
          </span>
        </label>
        {fieldErrors.consent && <p className="-mt-2 text-xs font-medium text-danger">{fieldErrors.consent}</p>}
        <div className="flex gap-2">
          <Button type="button" variant="secondary" fullWidth onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" variant="parent" fullWidth loading={addChild.isPending}>
            Add child
          </Button>
        </div>
      </form>
    </Modal>
  )
}

function SetPinModal({ open, onClose, hasPin }: { open: boolean; onClose: () => void; hasPin: boolean }) {
  const setPin = useSetPin()
  const [pin, setPinValue] = useState('')
  const [error, setError] = useState<string | null>(null)

  async function submit() {
    if (pin.length < 4) return
    setError(null)
    try {
      await setPin.mutateAsync(pin)
      setPinValue('')
      onClose()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not save the PIN.')
    }
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={hasPin ? 'Change parental PIN' : 'Set a parental PIN'}
      description="A 4-digit PIN protects child profile switching."
    >
      <form
        className="flex flex-col items-center gap-4"
        onSubmit={(e) => {
          e.preventDefault()
          void submit()
        }}
      >
        <CodeInput value={pin} onChange={(v) => { setPinValue(v); setError(null) }} length={4} mask error={!!error} aria-label="Parental PIN" />
        {error && <p className="text-xs font-medium text-danger">{error}</p>}
        <div className="flex w-full gap-2">
          <Button type="button" variant="secondary" fullWidth onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" fullWidth loading={setPin.isPending} disabled={pin.length < 4}>
            Save PIN
          </Button>
        </div>
      </form>
    </Modal>
  )
}

function PageSkeleton() {
  return (
    <div className="flex flex-col gap-8">
      <Skeleton className="h-8 w-48" />
      <div className="grid gap-4 sm:grid-cols-2">
        <Skeleton className="h-40" />
        <Skeleton className="h-40" />
      </div>
      <Skeleton className="h-32" />
    </div>
  )
}
