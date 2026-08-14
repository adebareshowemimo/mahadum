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
  Icon,
  Input,
  LinkButton,
  Modal,
  Skeleton,
} from '@/components/ui'
import { ApiError } from '@/lib/api'
import { SetPinModal } from '@/components/family/SetPinModal'
import { formatMoney } from '@/lib/format'
import { useConfig } from '@/lib/config/useConfig'
import { useAddChild, useFamily } from '@/lib/family/queries'

export function FamilyPage() {
  const { data: family, isLoading, isError } = useFamily()
  const [addOpen, setAddOpen] = useState(false)
  const [pinLearner, setPinLearner] = useState<{ id: number; display_name: string; pin_protected: boolean } | null>(null)

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
            <LinkButton to="/wallet" variant="secondary" size="sm">
              Manage wallet
            </LinkButton>
          </CardBody>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Parental PINs</CardTitle>
          </CardHeader>
          <CardBody className="flex flex-col gap-3">
            <div className="flex items-center gap-2">
              <Icon name="shield" className="text-muted" />
              <Badge variant={family.learners.some((l) => l.pin_protected) ? 'success' : 'warning'}>
                {family.learners.filter((l) => l.pin_protected).length} of{' '}
                {family.learners.filter((l) => l.is_child).length} protected
              </Badge>
            </div>
            <p className="text-sm text-muted">
              Each child gets their own unique PIN — set it below to stop siblings switching into their profile.
            </p>
          </CardBody>
        </Card>
      </div>

      <section>
        <h2 className="mb-3 font-display text-lg font-bold text-foreground">Family members</h2>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <Card>
            <CardBody className="flex items-center gap-3">
              <Avatar name={family.parent.name} />
              <div className="min-w-0 flex-1">
                <p className="truncate font-semibold text-foreground">{family.parent.name}</p>
                <p className="truncate text-xs text-muted">{family.parent.email}</p>
              </div>
              <Badge variant="primary">Parent</Badge>
            </CardBody>
          </Card>

          {family.learners.map((l) => (
            <Card key={l.id}>
              <CardBody className="flex items-center gap-3">
                <Link
                  to={`/family/children/${l.id}`}
                  className="flex min-w-0 flex-1 items-center gap-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-ring"
                  aria-label={`View ${l.display_name}'s profile`}
                >
                  <Avatar name={l.display_name} />
                  <div className="min-w-0 flex-1">
                    <p className="truncate font-semibold text-foreground hover:text-primary">{l.display_name}</p>
                    <div className="mt-1 flex flex-wrap items-center gap-1.5">
                      {l.is_child && <Badge variant="info">Child</Badge>}
                      <span className="inline-flex items-center gap-1 text-xs font-medium text-muted">
                        🪙 {l.coin_balance.toLocaleString()}
                      </span>
                    </div>
                  </div>
                  <Icon name="chevron" className="size-4 -rotate-90 text-subtle" />
                </Link>
                {l.is_child && (
                  <Button
                    variant={l.pin_protected ? 'outline' : 'secondary'}
                    size="sm"
                    leftIcon={l.pin_protected ? <Icon name="shield" className="size-4" /> : undefined}
                    onClick={() => setPinLearner(l)}
                  >
                    {l.pin_protected ? 'Change PIN' : 'Set PIN'}
                  </Button>
                )}
              </CardBody>
            </Card>
          ))}
        </div>

        {family.learners.length === 0 && (
          <Card className="mt-3">
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
        )}
      </section>

      <AddChildModal open={addOpen} onClose={() => setAddOpen(false)} />
      {pinLearner && (
        <SetPinModal
          open
          onClose={() => setPinLearner(null)}
          learnerId={pinLearner.id}
          learnerName={pinLearner.display_name}
          hasPin={pinLearner.pin_protected}
        />
      )}
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
