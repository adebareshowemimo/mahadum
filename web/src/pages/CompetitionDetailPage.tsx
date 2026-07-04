import { useState, type FormEvent } from 'react'
import { Link, useParams } from 'react-router-dom'
import { Alert, Badge, Button, Card, CardBody, Input, Modal, Skeleton } from '@/components/ui'
import { ApiError, type CompetitionCategory, type CompetitionDetail, type CompetitionEntry, type SubmitEntryInput } from '@/lib/api'
import { useAuth } from '@/lib/auth/AuthProvider'
import { useCompetition, useSubmitEntry, useVote } from '@/lib/competition/queries'

const CATEGORY_LABEL: Record<CompetitionCategory, string> = {
  school_play: 'School plays',
  diaspora_folklore: 'Diaspora folklore',
}

export function CompetitionDetailPage() {
  const { competitionId } = useParams()
  const id = Number(competitionId)
  const { data, isLoading, isError } = useCompetition(id)
  const [submitOpen, setSubmitOpen] = useState(false)

  if (isLoading) return <Skeleton className="h-96" />
  if (isError || !data) return <Alert variant="danger">Couldn’t load this competition.</Alert>

  return (
    <div className="flex flex-col gap-8">
      <div>
        <Link to="/competitions" className="text-sm text-muted hover:text-foreground">
          ← All competitions
        </Link>
        <div className="mt-2 flex flex-wrap items-end justify-between gap-3">
          <div>
            <h1 className="font-display text-2xl font-bold text-foreground">{data.title}</h1>
            <p className="mt-1 text-muted">Season {data.season}</p>
          </div>
          {data.can_enter && (
            <Button variant="premium" onClick={() => setSubmitOpen(true)}>
              Submit an entry
            </Button>
          )}
        </div>
        {data.description && <p className="mt-3 max-w-2xl text-sm text-muted">{data.description}</p>}
        {!data.accepting_votes && (
          <Alert variant="info" className="mt-4">
            Voting is {data.status === 'closed' ? 'closed — results are final.' : 'not open yet.'}
          </Alert>
        )}
      </div>

      {(['school_play', 'diaspora_folklore'] as CompetitionCategory[]).map((cat) => (
        <CategorySection key={cat} competition={data} category={cat} />
      ))}

      {data.can_enter && (
        <SubmitEntryModal competition={data} open={submitOpen} onClose={() => setSubmitOpen(false)} />
      )}
    </div>
  )
}

function CategorySection({ competition, category }: { competition: CompetitionDetail; category: CompetitionCategory }) {
  const entries = competition.entries.filter((e) => e.category === category)
  const alreadyVoted = competition.voted_categories.includes(category)

  return (
    <section>
      <div className="mb-3 flex items-center gap-2">
        <h2 className="font-display text-lg font-bold text-foreground">{CATEGORY_LABEL[category]}</h2>
        <Badge variant="neutral">{entries.length}</Badge>
        {alreadyVoted && <Badge variant="success">You voted</Badge>}
      </div>
      {entries.length === 0 ? (
        <Card>
          <CardBody className="py-6 text-center text-sm text-muted">No entries in this category yet.</CardBody>
        </Card>
      ) : (
        <div className="flex flex-col gap-2">
          {entries.map((e) => (
            <EntryRow
              key={e.id}
              competitionId={competition.id}
              entry={e}
              canVote={competition.accepting_votes && !alreadyVoted}
            />
          ))}
        </div>
      )}
    </section>
  )
}

function EntryRow({ competitionId, entry, canVote }: { competitionId: number; entry: CompetitionEntry; canVote: boolean }) {
  const vote = useVote(competitionId)
  const [error, setError] = useState<string | null>(null)

  async function onVote() {
    setError(null)
    try {
      await vote.mutateAsync(entry.id)
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not record your vote.')
    }
  }

  return (
    <Card>
      <CardBody className="flex items-center justify-between gap-4 py-3">
        <div className="min-w-0">
          <div className="flex items-center gap-2">
            <p className="truncate font-semibold text-foreground">{entry.title}</p>
            {entry.award_rank === 1 && <Badge variant="warning">🏆 Winner</Badge>}
            {entry.award_rank != null && entry.award_rank > 1 && <Badge variant="info">#{entry.award_rank}</Badge>}
          </div>
          <p className="truncate text-xs text-muted">
            {entry.entrant ?? 'Anonymous'}
            {entry.language ? ` · ${entry.language}` : ''}
          </p>
          {error && <p className="mt-1 text-xs text-clay-600">{error}</p>}
        </div>
        <div className="flex shrink-0 items-center gap-3">
          <span className="font-display text-lg font-bold text-foreground">{entry.votes_count}</span>
          <Button size="sm" variant="secondary" disabled={!canVote} loading={vote.isPending} onClick={onVote}>
            Vote
          </Button>
        </div>
      </CardBody>
    </Card>
  )
}

function SubmitEntryModal({ competition, open, onClose }: { competition: CompetitionDetail; open: boolean; onClose: () => void }) {
  const { user } = useAuth()
  const submit = useSubmitEntry(competition.id)
  const orgs = user?.organizations ?? []
  const learners = (user?.families ?? []).flatMap((f) => f.learners ?? [])

  const [category, setCategory] = useState<CompetitionCategory>(orgs.length > 0 ? 'school_play' : 'diaspora_folklore')
  const [title, setTitle] = useState('')
  const [synopsis, setSynopsis] = useState('')
  const [orgId, setOrgId] = useState<number | ''>(orgs[0]?.id ?? '')
  const [learnerId, setLearnerId] = useState<number | ''>(learners[0]?.id ?? '')
  const [error, setError] = useState<string | null>(null)
  const [done, setDone] = useState(false)

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setError(null)
    const input: SubmitEntryInput = { category, title, synopsis: synopsis || undefined }
    if (category === 'school_play') input.organization_id = Number(orgId)
    else input.learner_profile_id = Number(learnerId)

    try {
      await submit.mutateAsync(input)
      setDone(true)
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not submit your entry.')
    }
  }

  function close() {
    setTitle('')
    setSynopsis('')
    setError(null)
    setDone(false)
    onClose()
  }

  const canSchool = orgs.length > 0
  const canFolklore = learners.length > 0

  return (
    <Modal open={open} onClose={close} title="Submit an entry" description="Enter the national Language & Culture competition.">
      {done ? (
        <div className="flex flex-col gap-4">
          <Alert variant="success" title="Entry received">
            Your submission is in. Organisers will review it before it appears for voting.
          </Alert>
          <Button fullWidth onClick={close}>
            Done
          </Button>
        </div>
      ) : (
        <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
          {error && <Alert variant="danger">{error}</Alert>}

          <div className="flex flex-col gap-1.5 text-sm">
            <span className="font-semibold text-foreground">Category</span>
            <div className="flex gap-2">
              <CategoryChip label="School play" active={category === 'school_play'} disabled={!canSchool} onClick={() => setCategory('school_play')} />
              <CategoryChip label="Diaspora folklore" active={category === 'diaspora_folklore'} disabled={!canFolklore} onClick={() => setCategory('diaspora_folklore')} />
            </div>
          </div>

          {category === 'school_play' ? (
            <label className="flex flex-col gap-1.5 text-sm">
              <span className="font-semibold text-foreground">School</span>
              <select
                value={orgId}
                onChange={(e) => setOrgId(e.target.value === '' ? '' : Number(e.target.value))}
                className="h-11 rounded-xl border border-border-strong bg-surface px-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
              >
                {orgs.map((o) => (
                  <option key={o.id} value={o.id}>
                    {o.name ?? `Org #${o.id}`}
                  </option>
                ))}
              </select>
            </label>
          ) : (
            <label className="flex flex-col gap-1.5 text-sm">
              <span className="font-semibold text-foreground">Child</span>
              <select
                value={learnerId}
                onChange={(e) => setLearnerId(e.target.value === '' ? '' : Number(e.target.value))}
                className="h-11 rounded-xl border border-border-strong bg-surface px-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
              >
                {learners.map((l) => (
                  <option key={l.id} value={l.id}>
                    {l.display_name}
                  </option>
                ))}
              </select>
              <span className="text-xs text-muted">Must have at least {competition.min_activity_days} days of activity to qualify.</span>
            </label>
          )}

          <Input label="Title" value={title} onChange={(e) => setTitle(e.target.value)} required placeholder="e.g. The Talking Drum" />
          <label className="flex flex-col gap-1.5 text-sm">
            <span className="font-semibold text-foreground">Synopsis (optional)</span>
            <textarea
              value={synopsis}
              onChange={(e) => setSynopsis(e.target.value)}
              rows={3}
              className="rounded-xl border border-border-strong bg-surface px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
              placeholder="A short description of the piece."
            />
          </label>

          <div className="flex gap-2">
            <Button type="button" variant="secondary" fullWidth onClick={close}>
              Cancel
            </Button>
            <Button
              type="submit"
              variant="premium"
              fullWidth
              loading={submit.isPending}
              disabled={!title || (category === 'school_play' ? orgId === '' : learnerId === '')}
            >
              Submit
            </Button>
          </div>
        </form>
      )}
    </Modal>
  )
}

function CategoryChip({ label, active, disabled, onClick }: { label: string; active: boolean; disabled?: boolean; onClick: () => void }) {
  return (
    <button
      type="button"
      disabled={disabled}
      onClick={onClick}
      className={`rounded-full border px-3 py-1.5 text-sm font-semibold transition ${
        active ? 'border-transparent bg-foreground text-surface' : 'border-border-strong text-foreground hover:bg-surface-2'
      } disabled:cursor-not-allowed disabled:opacity-40`}
    >
      {label}
    </button>
  )
}
