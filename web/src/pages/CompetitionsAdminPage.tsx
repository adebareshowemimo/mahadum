import { useState } from 'react'
import { Link } from 'react-router-dom'
import { AdminPageHeader } from '@/components/admin'
import { Alert, Badge, Button, Card, CardBody, Input, Skeleton } from '@/components/ui'
import {
  ApiError,
  type AdminCompetition,
  type AdminCompetitionEntry,
  type CompetitionStatus,
  type CreateCompetitionInput,
  type EntryModeration,
} from '@/lib/api'
import {
  useAdminCompetition,
  useAdminCompetitions,
  useCreateCompetition,
  useJudgeCompetition,
  useModerateEntry,
  useSetCompetitionStatus,
  useUpdateCompetition,
} from '@/lib/competition/queries'

const STATUS_VARIANT: Record<CompetitionStatus, 'neutral' | 'success' | 'info'> = {
  draft: 'neutral',
  open: 'success',
  voting: 'info',
  closed: 'neutral',
}

// Lifecycle: draft → open → voting → closed. Each stage offers the next move.
const NEXT: Partial<Record<CompetitionStatus, { to: CompetitionStatus; label: string }>> = {
  draft: { to: 'open', label: 'Open for entries' },
  open: { to: 'voting', label: 'Start voting' },
  voting: { to: 'closed', label: 'Close' },
}

export function CompetitionsAdminPage() {
  const { data, isLoading, isError } = useAdminCompetitions()
  const [creating, setCreating] = useState(false)

  if (isLoading) return <Skeleton className="h-96" />
  if (isError || !data) return <Alert variant="danger">Couldn’t load competitions.</Alert>

  return (
    <div className="flex max-w-3xl flex-col gap-6">
      <AdminPageHeader
        title="Competitions"
        description="Create and run the annual Language & Culture competition. Move it through its lifecycle; entries and votes are collected on the public competition pages. Actions are audited."
        actions={
          <Button variant="parent" onClick={() => setCreating((v) => !v)}>
            {creating ? 'Close' : 'New competition'}
          </Button>
        }
      />

      {creating && <NewCompetitionForm onDone={() => setCreating(false)} />}

      <div className="flex flex-col gap-4">
        {data.map((c) => (
          <CompetitionRow key={c.id} competition={c} />
        ))}
      </div>
    </div>
  )
}

function NewCompetitionForm({ onDone }: { onDone: () => void }) {
  const create = useCreateCompetition()
  const [form, setForm] = useState<CreateCompetitionInput>({ title: '', season: new Date().getFullYear(), min_activity_days: 90 })
  const [error, setError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})

  async function onSubmit() {
    setError(null)
    setFieldErrors({})
    try {
      await create.mutateAsync({ ...form, title: form.title.trim() })
      onDone()
    } catch (err) {
      if (err instanceof ApiError) {
        setFieldErrors(err.fieldErrors)
        if (!Object.keys(err.fieldErrors).length) setError(err.message)
      } else {
        setError('Could not create the competition.')
      }
    }
  }

  return (
    <Card>
      <CardBody className="flex flex-col gap-4">
        <h2 className="font-display text-lg font-bold text-foreground">New competition</h2>
        {error && <Alert variant="danger">{error}</Alert>}
        <div className="grid gap-4 sm:grid-cols-2">
          <Input label="Title" placeholder="Language & Culture 2026" value={form.title} onChange={(e) => setForm((f) => ({ ...f, title: e.target.value }))} error={fieldErrors.title} />
          <Input label="Season (year)" type="number" value={form.season} onChange={(e) => setForm((f) => ({ ...f, season: Number(e.target.value) }))} error={fieldErrors.season} />
          <Input label="Min. activity days to qualify" type="number" min={0} value={form.min_activity_days ?? 90} onChange={(e) => setForm((f) => ({ ...f, min_activity_days: Number(e.target.value) }))} error={fieldErrors.min_activity_days} />
        </div>
        <CompetitionContentFields form={form} setForm={setForm} fieldErrors={fieldErrors} />
        <div className="flex justify-end gap-2">
          <Button variant="ghost" onClick={onDone}>Cancel</Button>
          <Button variant="parent" loading={create.isPending} disabled={!form.title.trim()} onClick={onSubmit}>
            Create
          </Button>
        </div>
      </CardBody>
    </Card>
  )
}

type DateField = 'entries_open_at' | 'submissions_close_at' | 'voting_closes_at' | 'judging_starts_at' | 'judging_closes_at'
const DATE_FIELDS: { key: DateField; label: string }[] = [
  { key: 'entries_open_at', label: 'Entries open' },
  { key: 'submissions_close_at', label: 'Entries close' },
  { key: 'voting_closes_at', label: 'Voting closes' },
  { key: 'judging_starts_at', label: 'Judging starts' },
  { key: 'judging_closes_at', label: 'Judging ends' },
]

function localDateTime(value: string | null | undefined): string {
  if (!value) return ''
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''
  return new Date(date.getTime() - date.getTimezoneOffset() * 60_000).toISOString().slice(0, 16)
}

function CompetitionContentFields({ form, setForm, fieldErrors }: {
  form: CreateCompetitionInput
  setForm: (updater: (form: CreateCompetitionInput) => CreateCompetitionInput) => void
  fieldErrors: Record<string, string>
}) {
  return <div className="flex flex-col gap-4">
    {(['description', 'rules', 'prizes', 'eligibility'] as const).map((key) => <label key={key} className="flex flex-col gap-1 text-sm font-semibold text-foreground">
      <span>{key[0].toUpperCase() + key.slice(1)}</span>
      <textarea rows={key === 'rules' ? 5 : 3} value={form[key] ?? ''} onChange={(e) => setForm((current) => ({ ...current, [key]: e.target.value }))} className="rounded-xl border border-border-strong bg-surface px-3 py-2 text-sm font-normal text-foreground" />
      {fieldErrors[key] && <span className="text-xs text-danger">{fieldErrors[key]}</span>}
    </label>)}
    <div className="grid gap-4 sm:grid-cols-2">{DATE_FIELDS.map(({ key, label }) => <Input key={key} label={label} type="datetime-local" value={localDateTime(form[key])} onChange={(e) => setForm((current) => ({ ...current, [key]: e.target.value ? new Date(e.target.value).toISOString() : null }))} error={fieldErrors[key]} />)}</div>
  </div>
}

function EditCompetitionDetails({ competition, onDone }: { competition: AdminCompetition; onDone: () => void }) {
  const update = useUpdateCompetition()
  const [form, setForm] = useState<CreateCompetitionInput>({
    title: competition.title, season: competition.season, description: competition.description ?? '',
    rules: competition.rules ?? '', prizes: competition.prizes ?? '', eligibility: competition.eligibility ?? '',
    entries_open_at: competition.entries_open_at, submissions_close_at: competition.submissions_close_at,
    voting_closes_at: competition.voting_closes_at, judging_starts_at: competition.judging_starts_at,
    judging_closes_at: competition.judging_closes_at, min_activity_days: competition.min_activity_days,
  })
  const [error, setError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})

  async function save() {
    setError(null)
    setFieldErrors({})
    try {
      await update.mutateAsync({ id: competition.id, input: form })
      onDone()
    } catch (err) {
      if (err instanceof ApiError) {
        setFieldErrors(err.fieldErrors)
        setError(err.message)
      } else setError('Could not save competition details.')
    }
  }

  return <div className="flex flex-col gap-4 border-t border-border pt-4">
    <h3 className="font-display font-bold text-foreground">Competition details</h3>
    {error && <Alert variant="danger">{error}</Alert>}
    <Input label="Title" value={form.title} onChange={(e) => setForm((current) => ({ ...current, title: e.target.value }))} error={fieldErrors.title} />
    <CompetitionContentFields form={form} setForm={setForm} fieldErrors={fieldErrors} />
    <div className="flex justify-end gap-2"><Button variant="ghost" onClick={onDone}>Cancel</Button><Button variant="parent" loading={update.isPending} onClick={save}>Save details</Button></div>
  </div>
}

function CompetitionRow({ competition }: { competition: AdminCompetition }) {
  const setStatus = useSetCompetitionStatus()
  const next = NEXT[competition.status]
  const [managing, setManaging] = useState(false)
  const [editing, setEditing] = useState(false)
  const [copied, setCopied] = useState(false)
  const [copyError, setCopyError] = useState(false)
  const [statusError, setStatusError] = useState<string | null>(null)
  const publicUrl = `${window.location.origin}/competitions/${competition.slug}`

  async function copyLink() {
    try {
      await navigator.clipboard.writeText(publicUrl)
      setCopied(true)
      setCopyError(false)
    } catch {
      setCopyError(true)
    }
  }

  return (
    <Card>
      <CardBody className="flex flex-col gap-4">
        <div className="flex flex-wrap items-center justify-between gap-4">
          <div>
            <div className="flex items-center gap-2">
              <h2 className="font-display text-lg font-bold text-foreground">{competition.title}</h2>
              <Badge variant={STATUS_VARIANT[competition.status]}>{competition.status}</Badge>
            </div>
            <p className="text-xs text-muted">
              Season {competition.season} · {competition.entries_count} entries · qualifies at {competition.min_activity_days} days
            </p>
          </div>
          <div className="flex items-center gap-2">
            <Link to={`/competitions/${competition.slug}`} className="text-sm font-semibold text-muted hover:text-foreground">
              Public page
            </Link>
            <Button size="sm" variant="secondary" onClick={copyLink}>{copied ? 'Copied' : 'Copy link'}</Button>
            <Button size="sm" variant="secondary" onClick={() => setEditing((value) => !value)}>{editing ? 'Close editor' : 'Edit details'}</Button>
            <Button size="sm" variant="secondary" onClick={() => setManaging((v) => !v)}>
              {managing ? 'Hide entries' : 'Manage entries'}
            </Button>
            {next && (
              <Button
                size="sm"
                variant="parent"
                loading={setStatus.isPending}
                onClick={async () => {
                  setStatusError(null)
                  try { await setStatus.mutateAsync({ id: competition.id, status: next.to }) }
                  catch (err) { setStatusError(err instanceof ApiError ? err.message : 'Could not change competition status.') }
                }}
              >
                {next.label}
              </Button>
            )}
          </div>
        </div>

        {managing && <EntriesManager competitionId={competition.id} />}
        {statusError && <Alert variant="danger">{statusError}</Alert>}
        {competition.status === 'draft' && <p className="text-xs text-muted">Complete the description, rules, prizes, eligibility and all dates before opening. The public link works once opened.</p>}
        {copyError && <p role="alert" className="text-sm text-danger">Could not copy automatically. Use the Public page link above.</p>}
        {editing && <EditCompetitionDetails competition={competition} onDone={() => setEditing(false)} />}
      </CardBody>
    </Card>
  )
}

const CATEGORY_LABEL: Record<string, string> = {
  school_play: 'School play',
  diaspora_folklore: 'Diaspora folklore',
}

function EntriesManager({ competitionId }: { competitionId: number }) {
  const { data, isLoading, isError } = useAdminCompetition(competitionId)
  const judge = useJudgeCompetition(competitionId)
  const [ranks, setRanks] = useState<Record<number, string>>({})
  const [judgeError, setJudgeError] = useState<string | null>(null)

  if (isLoading) return <Skeleton className="h-24" />
  if (isError || !data) return <Alert variant="danger">Couldn’t load entries.</Alert>
  if (data.entries.length === 0) return <p className="text-sm text-muted">No entries submitted yet.</p>

  async function recordResults() {
    setJudgeError(null)
    const awards = Object.entries(ranks)
      .filter(([, r]) => r !== '' && Number(r) > 0)
      .map(([entryId, r]) => ({ entry_id: Number(entryId), rank: Number(r) }))
    if (awards.length === 0) {
      setJudgeError('Assign a rank to at least one entry first.')
      return
    }
    try {
      await judge.mutateAsync(awards)
    } catch (err) {
      setJudgeError(err instanceof ApiError ? err.message : 'Could not record results.')
    }
  }

  return (
    <div className="flex flex-col gap-3 border-t border-border pt-4">
      <div className="flex flex-col gap-2">
        {data.entries.map((e) => (
          <EntryManagerRow
            key={e.id}
            competitionId={competitionId}
            entry={e}
            rank={ranks[e.id] ?? (e.award_rank != null ? String(e.award_rank) : '')}
            onRank={(v) => setRanks((prev) => ({ ...prev, [e.id]: v }))}
          />
        ))}
      </div>
      {judgeError && <Alert variant="danger">{judgeError}</Alert>}
      <div className="flex items-center justify-end gap-3">
        {data.status === 'closed' && <span className="text-sm font-semibold text-leaf-600">Results recorded ✓</span>}
        <Button size="sm" variant="premium" loading={judge.isPending} onClick={recordResults}>
          Record results &amp; close
        </Button>
      </div>
    </div>
  )
}

const MOD_ACTIONS: { status: EntryModeration; label: string }[] = [
  { status: 'approved', label: 'Approve' },
  { status: 'rejected', label: 'Reject' },
  { status: 'disqualified', label: 'Disqualify' },
]

function EntryManagerRow({
  competitionId,
  entry,
  rank,
  onRank,
}: {
  competitionId: number
  entry: AdminCompetitionEntry
  rank: string
  onRank: (v: string) => void
}) {
  const moderate = useModerateEntry(competitionId)

  return (
    <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border px-3 py-2">
      <div className="min-w-0">
        <div className="flex items-center gap-2">
          <p className="truncate font-semibold text-foreground">{entry.title}</p>
          <Badge variant="neutral">{entry.status}</Badge>
        </div>
        <p className="truncate text-xs text-muted">
          {CATEGORY_LABEL[entry.category] ?? entry.category} · {entry.entrant ?? 'Anonymous'} · {entry.votes_count} votes
        </p>
      </div>
      <div className="flex items-center gap-2">
        {MOD_ACTIONS.map((a) => (
          <Button
            key={a.status}
            size="sm"
            variant={entry.status === a.status ? 'parent' : 'ghost'}
            loading={moderate.isPending}
            onClick={() => moderate.mutate({ entryId: entry.id, status: a.status })}
          >
            {a.label}
          </Button>
        ))}
        <input
          type="number"
          min={1}
          value={rank}
          onChange={(e) => onRank(e.target.value)}
          placeholder="Rank"
          aria-label={`Award rank for ${entry.title}`}
          className="h-9 w-20 rounded-lg border border-border-strong bg-surface px-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
        />
      </div>
    </div>
  )
}
