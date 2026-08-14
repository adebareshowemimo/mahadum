import { useState, type FormEvent } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { Alert, Avatar, Badge, Button, Card, CardBody, Input, Modal, Skeleton } from '@/components/ui'
import { ApiError, schoolApi } from '@/lib/api'
import {
  useAddClassLearner,
  useAssignClassCourse,
  useAvailableClassLearners,
  useClassCourses,
  useCreateClass,
  useMyClasses,
  useUnassignClassCourse,
} from '@/lib/school/queries'

export function ClassesPage() {
  const { data, isLoading, isError } = useMyClasses()
  const [openId, setOpenId] = useState<number | null>(null)
  const [showCreate, setShowCreate] = useState(false)

  if (isLoading) return <Skeleton className="h-48" />
  if (isError || !data) return <Alert variant="danger">We couldn’t load your classes. Please refresh and try again.</Alert>

  return (
    <div className="flex flex-col gap-6">
      <div className="flex items-start justify-between gap-4">
        <div>
          <h1 className="font-display text-2xl font-bold text-foreground">My classes</h1>
          <p className="mt-1 text-muted">Create classes, add learners, and assign courses.</p>
        </div>
        <Button onClick={() => setShowCreate(true)}>New class</Button>
      </div>

      {data.length === 0 ? (
        <Card>
          <CardBody className="py-10 text-center text-sm text-muted">
            No classes assigned to you yet.
          </CardBody>
        </Card>
      ) : (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {data.map((c) => (
            <button key={c.id} onClick={() => setOpenId(c.id)} className="text-left">
              <Card className="transition-colors hover:bg-surface-muted">
                <CardBody className="flex flex-col gap-1">
                  <div className="flex items-center justify-between">
                    <p className="font-semibold text-foreground">{c.name}</p>
                    {c.level && <Badge variant="info">{c.level}</Badge>}
                  </div>
                  <p className="text-sm text-muted">{c.students} students</p>
                </CardBody>
              </Card>
            </button>
          ))}
        </div>
      )}

      <ClassDetailModal classId={openId} onClose={() => setOpenId(null)} />
      <CreateClassModal open={showCreate} onClose={() => setShowCreate(false)} />
    </div>
  )
}

function ClassDetailModal({ classId, onClose }: { classId: number | null; onClose: () => void }) {
  const navigate = useNavigate()
  const [tab, setTab] = useState<'students' | 'courses' | 'analytics'>('students')
  const [addingLearner, setAddingLearner] = useState(false)
  const detail = useQuery({
    queryKey: ['school-classes', 'detail', classId],
    queryFn: () => schoolApi.classDetail(classId as number),
    enabled: classId != null,
  })

  return (
    <Modal open={classId != null} onClose={onClose} title={detail.data?.name ?? 'Class'} description={detail.data?.level ?? undefined} className="sm:max-w-2xl">
      <div className="mb-4 flex gap-1 rounded-xl bg-surface-muted p-1">
        {(['students', 'courses', 'analytics'] as const).map((t) => (
          <button
            key={t}
            onClick={() => setTab(t)}
            className={
              tab === t
                ? 'flex-1 rounded-lg bg-surface px-3 py-1.5 text-sm font-semibold capitalize text-foreground shadow-sm'
                : 'flex-1 rounded-lg px-3 py-1.5 text-sm font-medium capitalize text-muted'
            }
          >
            {t}
          </button>
        ))}
      </div>

      {tab === 'students' ? (
        <div className="flex flex-col gap-3">
          <div className="flex justify-end gap-2">
            <Button size="sm" variant="outline" onClick={() => setAddingLearner(true)}>Add learner</Button>
            <Button size="sm" onClick={() => { onClose(); navigate(`/classes/${classId}/invite`) }}>Invite learner</Button>
          </div>
          {detail.isLoading ? (
            <Skeleton className="h-32" />
          ) : detail.isError || !detail.data ? (
            <Alert variant="danger">Couldn’t load this class.</Alert>
          ) : detail.data.students.length === 0 ? (
            <p className="py-6 text-center text-sm text-muted">No learners in this class yet.</p>
          ) : (
            <ul className="flex max-h-80 flex-col gap-2 overflow-y-auto">
              {detail.data.students.map((s) => (
                <li key={s.learner_id} className="flex items-center gap-3 rounded-xl border border-border p-2.5">
                  <Avatar name={s.display_name ?? 'Learner'} size="sm" />
                  <span className="font-medium text-foreground">{s.display_name ?? 'Learner'}</span>
                </li>
              ))}
            </ul>
          )}
          {classId && addingLearner && <AddLearnerForm classId={classId} onDone={() => setAddingLearner(false)} />}
        </div>
      ) : tab === 'courses' ? (
        <ClassCoursesTab classId={classId} />
      ) : (
        <ClassAnalyticsTab classId={classId} />
      )}
    </Modal>
  )
}

function CreateClassModal({ open, onClose }: { open: boolean; onClose: () => void }) {
  const createClass = useCreateClass()
  const [name, setName] = useState('')
  const [level, setLevel] = useState('')
  const [error, setError] = useState<string | null>(null)

  async function submit(e: FormEvent) {
    e.preventDefault()
    setError(null)
    try {
      await createClass.mutateAsync({ name: name.trim(), level: level.trim() || undefined })
      setName('')
      setLevel('')
      onClose()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not create the class.')
    }
  }

  return (
    <Modal open={open} onClose={onClose} title="Create class" description="You will be assigned as this class’s teacher.">
      <form className="flex flex-col gap-4" onSubmit={submit}>
        {error && <Alert variant="danger">{error}</Alert>}
        <Input label="Class name" value={name} onChange={(e) => setName(e.target.value)} required />
        <Input label="Level (optional)" value={level} onChange={(e) => setLevel(e.target.value)} />
        <Button type="submit" loading={createClass.isPending} disabled={!name.trim()}>Create class</Button>
      </form>
    </Modal>
  )
}

function AddLearnerForm({ classId, onDone }: { classId: number; onDone: () => void }) {
  const addLearner = useAddClassLearner(classId)
  const [search, setSearch] = useState('')
  const available = useAvailableClassLearners(classId, search)
  const [message, setMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  async function add(learnerId: number) {
    setError(null)
    try {
      const result = await addLearner.mutateAsync({ learner_id: learnerId })
      setMessage(`${result.display_name} was added${result.courses_enrolled ? ` and enrolled in ${result.courses_enrolled} assigned course${result.courses_enrolled === 1 ? '' : 's'}` : ''}.`)
      setSearch('')
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not add this learner.')
    }
  }

  return (
    <div className="flex flex-col gap-3 rounded-xl border border-border bg-surface-muted p-4">
      <p className="font-semibold text-foreground">Add a learner</p>
      <p className="text-xs text-muted">Search learners in your school by name or email. Learners outside your school are never shown.</p>
      {message && <Alert variant="success">{message}</Alert>}
      {error && <Alert variant="danger">{error}</Alert>}
      <Input label="Search school learners" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Enter at least 2 characters" autoFocus />
      {available.isFetching && <p className="text-sm text-muted">Searching…</p>}
      {search.trim().length >= 2 && !available.isFetching && available.data?.length === 0 && (
        <p className="text-sm text-muted">No available learner in this school matches your search.</p>
      )}
      {available.data && available.data.length > 0 && (
        <ul className="flex max-h-52 flex-col gap-2 overflow-y-auto">
          {available.data.map((learner) => (
            <li key={learner.id} className="flex items-center justify-between gap-3 rounded-lg border border-border bg-surface p-3">
              <div className="min-w-0">
                <p className="font-medium text-foreground">{learner.display_name}</p>
                <p className="truncate text-xs text-muted">{learner.email ?? learner.level ?? 'School learner'}</p>
              </div>
              <Button size="sm" loading={addLearner.isPending && addLearner.variables?.learner_id === learner.id} onClick={() => void add(learner.id)}>Add</Button>
            </li>
          ))}
        </ul>
      )}
      <div className="flex justify-end gap-2">
        <Button type="button" variant="ghost" onClick={onDone}>Close</Button>
      </div>
    </div>
  )
}

function ClassCoursesTab({ classId }: { classId: number | null }) {
  const courses = useClassCourses(classId)
  const assign = useAssignClassCourse(classId as number)
  const unassign = useUnassignClassCourse(classId as number)

  if (courses.isLoading) return <Skeleton className="h-32" />
  if (courses.isError || !courses.data) return <Alert variant="danger">Couldn’t load available courses.</Alert>
  if (courses.data.length === 0) return <p className="py-6 text-center text-sm text-muted">No published courses are available yet.</p>

  return (
    <ul className="flex max-h-96 flex-col gap-2 overflow-y-auto">
      {courses.data.map((course) => (
        <li key={course.id} className="flex items-center justify-between gap-3 rounded-xl border border-border p-3">
          <div className="min-w-0">
            <p className="font-semibold text-foreground">{course.title}</p>
            <p className="text-xs text-muted">{[course.language?.toUpperCase(), course.level_band].filter(Boolean).join(' · ') || 'All levels'}</p>
          </div>
          <Button
            size="sm"
            variant={course.assigned ? 'outline' : 'primary'}
            loading={(assign.isPending && assign.variables === course.id) || (unassign.isPending && unassign.variables === course.id)}
            onClick={() => course.assigned ? unassign.mutate(course.id) : assign.mutate(course.id)}
          >
            {course.assigned ? 'Assigned' : 'Assign course'}
          </Button>
        </li>
      ))}
    </ul>
  )
}

function ClassAnalyticsTab({ classId }: { classId: number | null }) {
  const analytics = useQuery({
    queryKey: ['school-classes', 'analytics', classId],
    queryFn: () => schoolApi.classAnalytics(classId as number),
    enabled: classId != null,
  })

  if (analytics.isLoading) return <Skeleton className="h-32" />
  if (analytics.isError || !analytics.data) return <Alert variant="danger">Couldn’t load analytics.</Alert>

  const rows = analytics.data.students
  return (
    <div className="max-h-80 overflow-auto">
      <table className="w-full text-left text-sm">
        <thead>
          <tr className="border-b border-border text-xs uppercase tracking-wide text-muted">
            <th className="py-2 pr-3 font-semibold">Student</th>
            <th className="px-2 py-2 text-right font-semibold" title="Lessons completed">Lessons</th>
            <th className="px-2 py-2 text-right font-semibold" title="Average lesson score">Avg</th>
            <th className="px-2 py-2 text-right font-semibold" title="Quiz accuracy">Quiz</th>
            <th className="px-2 py-2 text-right font-semibold" title="Speaking submissions">Speaking</th>
            <th className="px-2 py-2 text-right font-semibold" title="Class assignments passed / submitted">Assignments</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((s) => (
            <tr key={s.learner_id} className="border-b border-border last:border-0">
              <td className="py-2 pr-3 font-medium text-foreground">{s.display_name ?? 'Student'}</td>
              <td className="px-2 py-2 text-right tabular-nums text-foreground">{s.lessons_completed}</td>
              <td className="px-2 py-2 text-right tabular-nums text-muted">{s.avg_score == null ? '—' : `${s.avg_score}%`}</td>
              <td className="px-2 py-2 text-right tabular-nums text-muted">
                {s.quiz_accuracy == null ? '—' : `${s.quiz_accuracy}%`}
              </td>
              <td className="px-2 py-2 text-right tabular-nums text-muted">{s.speaking_count}</td>
              <td className="px-2 py-2 text-right tabular-nums text-muted">
                {s.assignments_submitted === 0 ? '—' : `${s.assignments_passed}/${s.assignments_submitted}`}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
