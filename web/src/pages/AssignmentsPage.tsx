import { useState, type FormEvent } from 'react'
import { Alert, Avatar, Badge, Button, Card, CardBody, Input, Modal, Skeleton, Textarea } from '@/components/ui'
import { ApiError, type ClassAssignmentRosterEntry } from '@/lib/api'
import {
  useClassAssignmentDetail,
  useClassAssignments,
  useClasses,
  useCreateClassAssignment,
  useGradeSubmission,
} from '@/lib/school/queries'

export function AssignmentsPage() {
  const { data: classes, isLoading, isError } = useClasses()
  const [classId, setClassId] = useState<number | null>(null)
  const [openAssignmentId, setOpenAssignmentId] = useState<number | null>(null)
  const [showCreate, setShowCreate] = useState(false)

  const activeClassId = classId ?? classes?.[0]?.id ?? null

  if (isLoading) return <Skeleton className="h-48" />
  if (isError || !classes) return <Alert variant="danger">We couldn’t load your classes. Please refresh and try again.</Alert>

  return (
    <div className="flex flex-col gap-6">
      <div className="flex items-start justify-between gap-4">
        <div>
          <h1 className="font-display text-2xl font-bold text-foreground">Assignments</h1>
          <p className="mt-1 text-muted">Create class assignments and grade submissions.</p>
        </div>
        {activeClassId && <Button onClick={() => setShowCreate(true)}>New assignment</Button>}
      </div>

      {classes.length === 0 ? (
        <Card>
          <CardBody className="py-10 text-center text-sm text-muted">No classes assigned to you yet.</CardBody>
        </Card>
      ) : (
        <>
          {classes.length > 1 && (
            <div className="flex flex-wrap gap-2">
              {classes.map((c) => (
                <button
                  key={c.id}
                  onClick={() => setClassId(c.id)}
                  className={
                    activeClassId === c.id
                      ? 'rounded-full bg-primary px-3.5 py-1.5 text-sm font-semibold text-primary-fg'
                      : 'rounded-full bg-surface-muted px-3.5 py-1.5 text-sm font-medium text-muted hover:bg-surface-sunken'
                  }
                >
                  {c.name}
                </button>
              ))}
            </div>
          )}

          <AssignmentList classId={activeClassId} onOpen={setOpenAssignmentId} />
        </>
      )}

      {activeClassId && (
        <>
          <CreateAssignmentModal classId={activeClassId} open={showCreate} onClose={() => setShowCreate(false)} />
          <AssignmentDetailModal
            classId={activeClassId}
            assignmentId={openAssignmentId}
            onClose={() => setOpenAssignmentId(null)}
          />
        </>
      )}
    </div>
  )
}

function AssignmentList({ classId, onOpen }: { classId: number | null; onOpen: (id: number) => void }) {
  const { data, isLoading, isError } = useClassAssignments(classId)

  if (isLoading) return <Skeleton className="h-32" />
  if (isError || !data) return <Alert variant="danger">Couldn’t load assignments for this class.</Alert>
  if (data.length === 0) {
    return (
      <Card>
        <CardBody className="py-10 text-center text-sm text-muted">No assignments yet for this class.</CardBody>
      </Card>
    )
  }

  return (
    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
      {data.map((a) => (
        <button key={a.id} onClick={() => onOpen(a.id)} className="text-left">
          <Card className="transition-colors hover:bg-surface-muted">
            <CardBody className="flex flex-col gap-1.5">
              <div className="flex items-center justify-between gap-2">
                <p className="font-semibold text-foreground">{a.title}</p>
                {a.coin_reward > 0 && <Badge variant="gold">{a.coin_reward} coins</Badge>}
              </div>
              {a.due_at && <p className="text-xs text-muted">Due {new Date(a.due_at).toLocaleDateString()}</p>}
              <p className="text-sm text-muted">
                {a.submitted_count}/{a.total_students} submitted · {a.graded_count} graded
              </p>
            </CardBody>
          </Card>
        </button>
      ))}
    </div>
  )
}

function CreateAssignmentModal({ classId, open, onClose }: { classId: number; open: boolean; onClose: () => void }) {
  const createAssignment = useCreateClassAssignment(classId)
  const [values, setValues] = useState({ title: '', instructions: '', due_at: '', coin_reward: '' })
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})
  const [formError, setFormError] = useState<string | null>(null)

  function update<K extends keyof typeof values>(key: K, val: (typeof values)[K]) {
    setValues((v) => ({ ...v, [key]: val }))
  }

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setFieldErrors({})
    setFormError(null)
    try {
      await createAssignment.mutateAsync({
        title: values.title.trim(),
        instructions: values.instructions.trim() || undefined,
        due_at: values.due_at || undefined,
        coin_reward: values.coin_reward ? Number(values.coin_reward) : undefined,
      })
      setValues({ title: '', instructions: '', due_at: '', coin_reward: '' })
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
    <Modal open={open} onClose={onClose} title="New assignment">
      <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
        {formError && <Alert variant="danger">{formError}</Alert>}
        <Input
          label="Title"
          value={values.title}
          onChange={(e) => update('title', e.target.value)}
          error={fieldErrors.title}
          autoFocus
          required
        />
        <Textarea
          label="Instructions (optional)"
          value={values.instructions}
          onChange={(e) => update('instructions', e.target.value)}
          error={fieldErrors.instructions}
        />
        <div className="grid grid-cols-2 gap-3">
          <Input
            label="Due date (optional)"
            type="date"
            value={values.due_at}
            onChange={(e) => update('due_at', e.target.value)}
            error={fieldErrors.due_at}
          />
          <Input
            label="Coin reward"
            type="number"
            min={0}
            value={values.coin_reward}
            onChange={(e) => update('coin_reward', e.target.value)}
            error={fieldErrors.coin_reward}
          />
        </div>
        <Button type="submit" loading={createAssignment.isPending}>
          Create assignment
        </Button>
      </form>
    </Modal>
  )
}

function AssignmentDetailModal({
  classId,
  assignmentId,
  onClose,
}: {
  classId: number
  assignmentId: number | null
  onClose: () => void
}) {
  const detail = useClassAssignmentDetail(classId, assignmentId)

  return (
    <Modal
      open={assignmentId != null}
      onClose={onClose}
      title={detail.data?.title ?? 'Assignment'}
      description={detail.data?.instructions ?? undefined}
      className="sm:max-w-xl"
    >
      {detail.isLoading ? (
        <Skeleton className="h-40" />
      ) : detail.isError || !detail.data ? (
        <Alert variant="danger">Couldn’t load this assignment.</Alert>
      ) : detail.data.roster.length === 0 ? (
        <p className="py-6 text-center text-sm text-muted">No students enrolled yet.</p>
      ) : (
        <ul className="flex max-h-96 flex-col gap-2 overflow-y-auto">
          {detail.data.roster.map((entry) => (
            <RosterRow
              key={entry.learner_id}
              entry={entry}
              classId={classId}
              assignmentId={assignmentId as number}
              coinReward={detail.data!.coin_reward}
            />
          ))}
        </ul>
      )}
    </Modal>
  )
}

function RosterRow({
  entry,
  classId,
  assignmentId,
  coinReward,
}: {
  entry: ClassAssignmentRosterEntry
  classId: number
  assignmentId: number
  coinReward: number
}) {
  const [grading, setGrading] = useState(false)
  const gradeSubmission = useGradeSubmission(classId, assignmentId)
  const [score, setScore] = useState('')
  const [feedback, setFeedback] = useState('')
  const [error, setError] = useState<string | null>(null)

  async function grade(passed: boolean) {
    setError(null)
    try {
      await gradeSubmission.mutateAsync({
        submissionId: entry.submission_id as number,
        input: { passed, score: score ? Number(score) : undefined, feedback: feedback.trim() || undefined },
      })
      setGrading(false)
    } catch {
      setError('Could not save this grade. Please try again.')
    }
  }

  return (
    <li className="rounded-xl border border-border p-2.5">
      <div className="flex items-center gap-3">
        <Avatar name={entry.display_name ?? 'Student'} size="sm" />
        <span className="flex-1 font-medium text-foreground">{entry.display_name ?? 'Student'}</span>
        {entry.status === 'graded' ? (
          <Badge variant={entry.passed ? 'success' : 'danger'}>{entry.passed ? 'Passed' : 'Not passed'}</Badge>
        ) : entry.status === 'submitted' ? (
          <Badge variant="info">Submitted</Badge>
        ) : (
          <Badge variant="neutral">Not submitted</Badge>
        )}
        {entry.status === 'submitted' && !grading && (
          <Button size="sm" variant="outline" onClick={() => setGrading(true)}>
            Grade
          </Button>
        )}
      </div>

      {entry.status === 'graded' && entry.feedback && (
        <p className="mt-2 text-sm text-muted">“{entry.feedback}”</p>
      )}

      {grading && (
        <div className="mt-3 flex flex-col gap-2 border-t border-border pt-3">
          {error && <Alert variant="danger">{error}</Alert>}
          <div className="grid grid-cols-2 gap-2">
            <Input label="Score (optional)" type="number" min={0} max={100} value={score} onChange={(e) => setScore(e.target.value)} />
            <div className="flex flex-col justify-end text-xs text-muted">
              {coinReward > 0 ? `${coinReward} coins release on pass` : 'No coin reward set'}
            </div>
          </div>
          <Textarea label="Feedback (optional)" value={feedback} onChange={(e) => setFeedback(e.target.value)} rows={2} />
          <div className="flex gap-2">
            <Button size="sm" variant="parent" loading={gradeSubmission.isPending} onClick={() => grade(true)}>
              Mark passed
            </Button>
            <Button size="sm" variant="danger" loading={gradeSubmission.isPending} onClick={() => grade(false)}>
              Mark not passed
            </Button>
            <Button size="sm" variant="ghost" onClick={() => setGrading(false)}>
              Cancel
            </Button>
          </div>
        </div>
      )}
    </li>
  )
}
