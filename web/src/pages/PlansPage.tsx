import { useEffect, useState } from 'react'
import { AdminPageHeader } from '@/components/admin'
import { Alert, Badge, Button, Card, CardBody, Input, Skeleton, Switch } from '@/components/ui'
import { ApiError, type AdminPlan, type CreatePlanInput, type UpdatePlanInput } from '@/lib/api'
import { useAdminPlans, useCreatePlan, useUpdatePlan } from '@/lib/admin/queries'

const FLAG_LABELS: Record<string, string> = {
  ads: 'Show ads',
  offline_download: 'Offline download',
  unlimited_hearts: 'Unlimited hearts',
  family_dashboard: 'Family dashboard',
  teacher_analytics: 'Teacher analytics',
}

const INTERVAL_LABELS: Record<string, string> = {
  month: 'Monthly',
  quarter: 'Quarterly',
  year: 'Yearly',
  term: 'Per term',
  week: 'Weekly',
}

const FLAGS = ['ads', 'offline_download', 'unlimited_hearts', 'family_dashboard', 'teacher_analytics']

function Select({ label, value, options, onChange, labels }: { label: string; value: string; options: string[]; onChange: (v: string) => void; labels?: Record<string, string> }) {
  return (
    <label className="flex flex-col gap-1.5 text-sm">
      <span className="font-semibold text-foreground">{label}</span>
      <select
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className="h-11 rounded-xl border border-border-strong bg-surface px-3 text-sm capitalize text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
      >
        {options.map((o) => (
          <option key={o} value={o}>
            {labels?.[o] ?? o}
          </option>
        ))}
      </select>
    </label>
  )
}

export function PlansPage() {
  const { data, isLoading, isError } = useAdminPlans()
  const [creating, setCreating] = useState(false)

  if (isLoading) return <Skeleton className="h-96" />
  if (isError || !data) return <Alert variant="danger">Couldn’t load plans.</Alert>

  // Option lists come back on every plan; fall back to sane defaults.
  const intervals = data[0]?.intervals ?? ['month', 'quarter', 'year', 'term', 'week']
  const audiences = data[0]?.audiences ?? ['individual', 'family', 'teacher', 'school', 'any']

  return (
    <div className="flex max-w-3xl flex-col gap-6">
      <AdminPageHeader
        title="Plans & pricing"
        description="Create tiers (e.g. a quarterly parent plan or a yearly teacher plan) and edit their price, cadence, audience and features. Changes are audited."
        actions={
          <Button variant="parent" onClick={() => setCreating((v) => !v)}>
            {creating ? 'Close' : 'New plan'}
          </Button>
        }
      />

      {creating && <NewPlanForm intervals={intervals} audiences={audiences} onDone={() => setCreating(false)} />}

      <div className="flex flex-col gap-4">
        {data.map((plan) => (
          <PlanCard key={plan.id} plan={plan} />
        ))}
      </div>
    </div>
  )
}

function NewPlanForm({ intervals, audiences, onDone }: { intervals: string[]; audiences: string[]; onDone: () => void }) {
  const create = useCreatePlan()
  const [form, setForm] = useState<CreatePlanInput>({ code: '', name: '', price_minor: 0, interval: 'month', audience: 'individual', max_profiles: 1, features: {} })
  const [naira, setNaira] = useState('0')
  const [flags, setFlags] = useState<Record<string, boolean>>({})
  const [error, setError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})

  function set<K extends keyof CreatePlanInput>(key: K, value: CreatePlanInput[K]) {
    setForm((f) => ({ ...f, [key]: value }))
  }

  async function onSubmit() {
    setError(null)
    setFieldErrors({})
    try {
      await create.mutateAsync({
        ...form,
        code: form.code.trim(),
        price_minor: Math.round(Number(naira) * 100),
        max_profiles: form.max_profiles || null,
        features: flags,
      })
      onDone()
    } catch (err) {
      if (err instanceof ApiError) {
        setFieldErrors(err.fieldErrors)
        if (!Object.keys(err.fieldErrors).length) setError(err.message)
      } else {
        setError('Could not create the plan.')
      }
    }
  }

  return (
    <Card>
      <CardBody className="flex flex-col gap-4">
        <h2 className="font-display text-lg font-bold text-foreground">New plan</h2>
        {error && <Alert variant="danger">{error}</Alert>}
        <div className="grid gap-4 sm:grid-cols-2">
          <Input label="Code (immutable)" placeholder="premium_quarterly" value={form.code} onChange={(e) => set('code', e.target.value)} error={fieldErrors.code} hint="lowercase letters, numbers, underscore" />
          <Input label="Name" placeholder="Premium (Quarterly)" value={form.name} onChange={(e) => set('name', e.target.value)} error={fieldErrors.name} />
          <Input label="Price (₦)" type="number" min={0} step="0.01" value={naira} onChange={(e) => setNaira(e.target.value)} error={fieldErrors.price_minor} />
          <Input label="Max profiles (blank = unlimited)" type="number" min={1} value={form.max_profiles ?? ''} onChange={(e) => set('max_profiles', e.target.value === '' ? null : Number(e.target.value))} />
          <Select label="Billing cadence" value={form.interval} options={intervals} labels={INTERVAL_LABELS} onChange={(v) => set('interval', v)} />
          <Select label="Audience" value={form.audience ?? 'any'} options={audiences} onChange={(v) => set('audience', v)} />
        </div>
        <div className="flex flex-col gap-2.5">
          <p className="text-sm font-semibold text-foreground">Features</p>
          {FLAGS.map((flag) => (
            <div key={flag} className="flex items-center justify-between">
              <span className="text-sm text-foreground">{FLAG_LABELS[flag]}</span>
              <Switch checked={Boolean(flags[flag])} onChange={(v) => setFlags((f) => ({ ...f, [flag]: v }))} />
            </div>
          ))}
        </div>
        <div className="flex justify-end gap-2">
          <Button variant="ghost" onClick={onDone}>Cancel</Button>
          <Button variant="parent" loading={create.isPending} disabled={!form.code.trim() || !form.name.trim()} onClick={onSubmit}>
            Create plan
          </Button>
        </div>
      </CardBody>
    </Card>
  )
}

function PlanCard({ plan }: { plan: AdminPlan }) {
  const update = useUpdatePlan()
  const [naira, setNaira] = useState(String((plan.price_minor ?? 0) / 100))
  const [maxProfiles, setMaxProfiles] = useState(plan.max_profiles == null ? '' : String(plan.max_profiles))
  const [interval, setInterval] = useState(plan.interval)
  const [audience, setAudience] = useState(plan.audience ?? 'any')
  const [flags, setFlags] = useState<Record<string, boolean>>(() =>
    Object.fromEntries(plan.editable_flags.map((f) => [f, Boolean(plan.features?.[f])])),
  )
  const [saved, setSaved] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})

  useEffect(() => {
    setNaira(String((plan.price_minor ?? 0) / 100))
    setMaxProfiles(plan.max_profiles == null ? '' : String(plan.max_profiles))
    setInterval(plan.interval)
    setAudience(plan.audience ?? 'any')
    setFlags(Object.fromEntries(plan.editable_flags.map((f) => [f, Boolean(plan.features?.[f])])))
  }, [plan])

  const pricedPerSeat = Boolean(plan.features?.priced_per_seat)

  async function onSave() {
    setError(null)
    setFieldErrors({})
    setSaved(false)
    const input: UpdatePlanInput = {
      price_minor: Math.round(Number(naira) * 100),
      max_profiles: maxProfiles === '' ? null : Number(maxProfiles),
      interval,
      audience,
      features: flags,
    }
    try {
      await update.mutateAsync({ planId: plan.id, input })
      setSaved(true)
    } catch (err) {
      if (err instanceof ApiError) {
        setFieldErrors(err.fieldErrors)
        if (!Object.keys(err.fieldErrors).length) setError(err.message)
      } else {
        setError('Could not save the plan.')
      }
    }
  }

  const dirty = () => setSaved(false)

  return (
    <Card>
      <CardBody className="flex flex-col gap-4">
        <div className="flex items-center justify-between">
          <div>
            <h2 className="font-display text-lg font-bold text-foreground">{plan.name}</h2>
            <p className="font-mono text-xs text-muted">{plan.code} · {INTERVAL_LABELS[plan.interval] ?? plan.interval}</p>
          </div>
          <div className="flex items-center gap-2">
            {plan.audience && <Badge variant="neutral">{plan.audience}</Badge>}
            {pricedPerSeat && <Badge variant="info">priced per seat</Badge>}
          </div>
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <Input label={`Price (₦)`} type="number" min={0} step="0.01" value={naira} onChange={(e) => { setNaira(e.target.value); dirty() }} error={fieldErrors.price_minor} hint={pricedPerSeat ? 'School tiers bill per seat.' : undefined} />
          <Input label="Max profiles (blank = unlimited)" type="number" min={1} value={maxProfiles} onChange={(e) => { setMaxProfiles(e.target.value); dirty() }} error={fieldErrors.max_profiles} />
          <Select label="Billing cadence" value={interval} options={plan.intervals} labels={INTERVAL_LABELS} onChange={(v) => { setInterval(v); dirty() }} />
          <Select label="Audience" value={audience} options={plan.audiences} onChange={(v) => { setAudience(v); dirty() }} />
        </div>

        {plan.editable_flags.length > 0 && (
          <div className="flex flex-col gap-2.5">
            <p className="text-sm font-semibold text-foreground">Features</p>
            {plan.editable_flags.map((flag) => (
              <div key={flag} className="flex items-center justify-between">
                <span className="text-sm text-foreground">{FLAG_LABELS[flag] ?? flag}</span>
                <Switch checked={Boolean(flags[flag])} onChange={(v) => { setFlags((f) => ({ ...f, [flag]: v })); dirty() }} />
              </div>
            ))}
          </div>
        )}

        {error && <Alert variant="danger">{error}</Alert>}

        <div className="flex items-center justify-end gap-3">
          {saved && <span className="text-sm font-semibold text-leaf-600">Saved ✓</span>}
          <Button variant="parent" loading={update.isPending} onClick={onSave}>
            Save
          </Button>
        </div>
      </CardBody>
    </Card>
  )
}
