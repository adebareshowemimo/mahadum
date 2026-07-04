import { useState, type FormEvent } from 'react'
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
import { ApiError, type AssignmentDecision, type AssignmentReviewItem, type ChoreDecision } from '@/lib/api'
import { useCreateChore, useFamily, usePendingReviews, useReviewAssignment, useReviewChore } from '@/lib/family/queries'

export function ReviewsPage() {
  const { data: queue, isLoading, isError } = usePendingReviews()
  const [newOpen, setNewOpen] = useState(false)

  if (isLoading) return <Skeleton className="h-48" />
  if (isError || !queue) {
    return <Alert variant="danger">We couldn’t load your review queue. Please refresh and try again.</Alert>
  }

  const nothingPending =
    queue.chores.length === 0 && queue.speaking.length === 0 && queue.assignments.length === 0

  return (
    <div className="flex flex-col gap-8">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="font-display text-2xl font-bold text-foreground">Reviews</h1>
          <p className="mt-1 text-muted">Approve chores and check what your children have submitted.</p>
        </div>
        <Button variant="parent" leftIcon={<Icon name="clipboard" className="size-[18px]" />} onClick={() => setNewOpen(true)}>
          New chore
        </Button>
      </div>

      {nothingPending ? (
        <Card>
          <CardBody className="flex flex-col items-center gap-3 py-12 text-center">
            <span className="text-4xl" aria-hidden="true">
              🎉
            </span>
            <p className="max-w-xs text-sm text-muted">
              All caught up — nothing waiting for your review right now.
            </p>
            <Button variant="parent" onClick={() => setNewOpen(true)}>
              Assign a chore
            </Button>
          </CardBody>
        </Card>
      ) : (
        <>
          <ChoresSection chores={queue.chores} />
          <AssignmentsSection assignments={queue.assignments} />
          {queue.speaking.length > 0 && <SpeakingSection count={queue.speaking.length} />}
        </>
      )}

      <NewChoreModal open={newOpen} onClose={() => setNewOpen(false)} />
    </div>
  )
}

function ChoresSection({
  chores,
}: {
  chores: { chore_id: number; title: string; assignee: string | null; coin_reward: number; status: string }[]
}) {
  const review = useReviewChore()
  const [actingId, setActingId] = useState<number | null>(null)
  const [error, setError] = useState<string | null>(null)

  if (chores.length === 0) return null

  async function decide(choreId: number, decision: ChoreDecision) {
    setActingId(choreId)
    setError(null)
    try {
      await review.mutateAsync({ choreId, decision })
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not record your decision.')
    } finally {
      setActingId(null)
    }
  }

  return (
    <section className="flex flex-col gap-3">
      <h2 className="font-display text-lg font-bold text-foreground">Chores</h2>
      {error && <Alert variant="danger">{error}</Alert>}
      {chores.map((c) => {
        const busy = actingId === c.chore_id
        return (
          <Card key={c.chore_id}>
            <CardBody className="flex flex-wrap items-center justify-between gap-3">
              <div className="min-w-0">
                <p className="font-semibold text-foreground">{c.title}</p>
                <p className="text-sm text-muted">
                  {c.assignee ?? 'Unassigned'} · 🪙 {c.coin_reward}
                  {c.status === 'pending_review' && (
                    <Badge variant="warning" className="ml-2">
                      Needs another look
                    </Badge>
                  )}
                </p>
              </div>
              <div className="flex flex-wrap gap-2">
                <Button size="sm" variant="ghost" loading={busy} onClick={() => decide(c.chore_id, 'more_evidence')}>
                  Ask for more
                </Button>
                <Button size="sm" variant="outline" loading={busy} onClick={() => decide(c.chore_id, 'reject')}>
                  Not yet
                </Button>
                <Button size="sm" variant="parent" loading={busy} onClick={() => decide(c.chore_id, 'approve')}>
                  Approve · 🪙 {c.coin_reward}
                </Button>
              </div>
            </CardBody>
          </Card>
        )
      })}
    </section>
  )
}

function AssignmentsSection({ assignments }: { assignments: AssignmentReviewItem[] }) {
  const review = useReviewAssignment()
  const [actingId, setActingId] = useState<number | null>(null)
  const [error, setError] = useState<string | null>(null)

  if (assignments.length === 0) return null

  async function decide(submissionId: number, decision: AssignmentDecision) {
    setActingId(submissionId)
    setError(null)
    try {
      await review.mutateAsync({ submissionId, decision })
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not record your decision.')
    } finally {
      setActingId(null)
    }
  }

  return (
    <section className="flex flex-col gap-3">
      <h2 className="font-display text-lg font-bold text-foreground">Assignments</h2>
      <p className="-mt-1 text-sm text-muted">Watch the clip, then release the coins if it looks good.</p>
      {error && <Alert variant="danger">{error}</Alert>}
      {assignments.map((a) => {
        const busy = actingId === a.id
        return (
          <Card key={a.id}>
            <CardBody className="flex flex-col gap-3">
              <div>
                <p className="font-semibold text-foreground">{a.prompt ?? 'Assignment'}</p>
                <p className="text-sm text-muted">
                  {a.learner ?? 'Your child'} · 🪙 {a.coin_reward} on approval
                </p>
              </div>
              {a.media_url ? (
                a.expected_media === 'audio' ? (
                  <audio src={a.media_url} controls className="w-full" />
                ) : (
                  <video src={a.media_url} controls playsInline className="aspect-video w-full rounded-xl bg-charcoal-900" />
                )
              ) : (
                <p className="rounded-xl bg-surface-muted px-3 py-2 text-sm text-muted">No clip was attached.</p>
              )}
              <div className="flex flex-wrap justify-end gap-2">
                <Button size="sm" variant="outline" loading={busy} onClick={() => decide(a.id, 'reject')}>
                  Not yet
                </Button>
                <Button size="sm" variant="parent" loading={busy} onClick={() => decide(a.id, 'approve')}>
                  Approve · 🪙 {a.coin_reward}
                </Button>
              </div>
            </CardBody>
          </Card>
        )
      })}
    </section>
  )
}

function SpeakingSection({ count }: { count: number }) {
  return (
    <section className="flex flex-col gap-3">
      <h2 className="font-display text-lg font-bold text-foreground">Speaking</h2>
      <Card>
        <CardBody className="flex items-center gap-3">
          <span className="text-2xl">🎙️</span>
          <div>
            <p className="font-semibold text-foreground">{count} speaking {count === 1 ? 'clip' : 'clips'}</p>
            <p className="text-sm text-muted">Awaiting review in the learner app.</p>
          </div>
        </CardBody>
      </Card>
    </section>
  )
}

function NewChoreModal({ open, onClose }: { open: boolean; onClose: () => void }) {
  const createChore = useCreateChore()
  const { data: family } = useFamily()
  const learners = family?.learners ?? []

  const [values, setValues] = useState({ title: '', assignee: '', coin_reward: '10', due_at: '' })
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})
  const [formError, setFormError] = useState<string | null>(null)

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setFieldErrors({})
    setFormError(null)
    try {
      await createChore.mutateAsync({
        title: values.title,
        assignee_learner_profile_id: Number(values.assignee),
        coin_reward: Number(values.coin_reward),
        due_at: values.due_at || undefined,
      })
      setValues({ title: '', assignee: '', coin_reward: '10', due_at: '' })
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
    <Modal open={open} onClose={onClose} title="New chore" description="Assign a task and set the coin reward.">
      {learners.length === 0 ? (
        <p className="text-sm text-muted">Add a child first to assign chores.</p>
      ) : (
        <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
          {formError && <Alert variant="danger">{formError}</Alert>}
          <Input
            label="Title"
            value={values.title}
            onChange={(e) => setValues((v) => ({ ...v, title: e.target.value }))}
            error={fieldErrors.title}
            autoFocus
            required
          />
          <label className="flex flex-col gap-1.5">
            <span className="text-sm font-semibold text-foreground">Assign to</span>
            <select
              value={values.assignee}
              onChange={(e) => setValues((v) => ({ ...v, assignee: e.target.value }))}
              required
              className="h-11 rounded-xl border border-border-strong bg-surface px-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
            >
              <option value="">Select a child</option>
              {learners.map((l) => (
                <option key={l.id} value={l.id}>
                  {l.display_name}
                </option>
              ))}
            </select>
            {fieldErrors.assignee_learner_profile_id && (
              <p className="text-xs font-medium text-danger">{fieldErrors.assignee_learner_profile_id}</p>
            )}
          </label>
          <div className="grid grid-cols-2 gap-3">
            <Input
              label="Coin reward"
              type="number"
              min={0}
              value={values.coin_reward}
              onChange={(e) => setValues((v) => ({ ...v, coin_reward: e.target.value }))}
              error={fieldErrors.coin_reward}
              required
            />
            <Input
              label="Due (optional)"
              type="date"
              value={values.due_at}
              onChange={(e) => setValues((v) => ({ ...v, due_at: e.target.value }))}
            />
          </div>
          <div className="flex gap-2">
            <Button type="button" variant="secondary" fullWidth onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" variant="parent" fullWidth loading={createChore.isPending}>
              Create chore
            </Button>
          </div>
        </form>
      )}
    </Modal>
  )
}
