import { useState } from 'react'
import { Alert, Badge, Button, Card, CardBody, Skeleton, Textarea } from '@/components/ui'
import { ApiError } from '@/lib/api'
import { useLearnerTasks, useSubmitTask } from '@/lib/family/queries'
import { useActiveProfile } from '@/lib/profile/ActiveProfile'

export function TasksPage() {
  const { activeLearnerId, activeLearner } = useActiveProfile()
  const adult = activeLearner?.age_band === 'adult' || activeLearner?.is_child === false
  const reviewer = adult ? 'parent' : 'grown-up'
  const tasks = useLearnerTasks(activeLearnerId)
  const submit = useSubmitTask(activeLearnerId)
  const [answers, setAnswers] = useState<Record<number, string>>({})
  const [error, setError] = useState<string | null>(null)

  async function send(kind: 'chore' | 'assignment', id: number) {
    setError(null)
    try { await submit.mutateAsync({ kind, id, text: answers[id]?.trim() }) }
    catch (err) { setError(err instanceof ApiError ? err.message : 'Could not submit your work. Please try again.') }
  }

  if (!activeLearnerId) return <Alert>Choose a learner from the profile menu to see their tasks.</Alert>
  if (tasks.isLoading) return <Skeleton className="h-48" />
  if (tasks.isError || !tasks.data) return <Alert variant="danger">We couldn’t load your tasks. Please try again.</Alert>

  return <div className="flex flex-col gap-6">
    <div><h1 className="font-display text-2xl font-bold text-foreground">My tasks</h1><p className="mt-1 text-muted">{adult ? 'Track your tasks, submissions and reward decisions.' : 'Complete your chores and class assignments. Your grown-up approves coin rewards.'}</p></div>
    {error && <Alert variant="danger">{error}</Alert>}
    <section aria-labelledby="chores-heading" className="flex flex-col gap-3">
      <h2 id="chores-heading" className="font-display text-lg font-bold text-foreground">Chores</h2>
      {tasks.data.chores.length === 0 && <p className="text-muted">No chores assigned.</p>}
      {tasks.data.chores.map(c => <Card key={c.id}><CardBody className="flex flex-wrap items-center justify-between gap-3">
        <div><h3 className="font-semibold text-foreground">{c.title}</h3>{c.description && <p className="text-sm text-muted">{c.description}</p>}<p className="text-sm text-muted">{c.coin_reward} coins on approval{c.due_at ? ` · Due ${new Date(c.due_at).toLocaleDateString()}` : ''}</p></div>
        {c.status === 'approved' ? <Badge variant="success">Approved +{c.coin_reward} coins</Badge> : c.status === 'pending_review' && !c.review_decision ? <Badge variant="info">Waiting for review</Badge> : <div className="flex flex-col gap-2"><Badge variant={c.status === 'rejected' ? 'danger' : 'neutral'}>{c.review_decision === 'more_evidence' ? 'Needs more' : c.status === 'rejected' ? 'Rejected' : 'To do'}</Badge>{c.review_decision === 'more_evidence' && <p className="text-sm text-muted">Your {reviewer} asked you to check your work again.</p>}{c.status === 'rejected' && <p className="text-sm text-muted">Try again, then send it for another review.</p>}<Button loading={submit.isPending && submit.variables?.kind === 'chore' && submit.variables.id === c.id} onClick={() => void send('chore', c.id)}>I finished this chore</Button></div>}
      </CardBody></Card>)}
    </section>
    <section aria-labelledby="assignments-heading" className="flex flex-col gap-3">
      <h2 id="assignments-heading" className="font-display text-lg font-bold text-foreground">Class assignments</h2>
      {tasks.data.assignments.length === 0 && <p className="text-muted">No class assignments yet.</p>}
      {tasks.data.assignments.map(a => <Card key={a.id}><CardBody className="flex flex-col gap-3">
        <div><h3 className="font-semibold text-foreground">{a.title}</h3><p className="text-sm text-muted">{a.class_name}{a.due_at ? ` · Due ${new Date(a.due_at).toLocaleDateString()}` : ''} · {a.coin_reward} coins on parent approval</p></div>
        {a.instructions && <p className="whitespace-pre-wrap text-foreground">{a.instructions}</p>}
        {a.status ? <><Badge variant="info">{a.status === 'graded' ? 'Graded' : 'Submitted'}</Badge>{a.feedback && <p className="text-muted">Teacher feedback: {a.feedback}</p>}{a.parent_review_status === 'pending' && <p className="text-sm text-muted">Coins are waiting for your {reviewer}’s approval.</p>}</> : <>
          <Textarea label={`Your answer for ${a.title}`} value={answers[a.id] ?? ''} maxLength={10000} onChange={e => setAnswers(v => ({ ...v, [a.id]: e.target.value }))} />
          <Button className="self-start" disabled={!answers[a.id]?.trim()} loading={submit.isPending && submit.variables?.kind === 'assignment' && submit.variables.id === a.id} onClick={() => void send('assignment', a.id)}>Submit assignment</Button>
        </>}
      </CardBody></Card>)}
    </section>
  </div>
}
