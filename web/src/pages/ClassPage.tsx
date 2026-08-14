import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link, useParams, useSearchParams } from 'react-router-dom'
import { Alert, Avatar, Badge, Button, Input, LinkButton, Skeleton } from '@/components/ui'
import { ApiError, schoolApi, type ClassAnalyticsStudent } from '@/lib/api'
import { useAuth } from '@/lib/auth/AuthProvider'
import {
  useAddClassLearner,
  useAssignClassCourse,
  useAvailableClassLearners,
  useClassAssignments,
  useClassCourses,
  useUnassignClassCourse,
} from '@/lib/school/queries'

type ClassTab = 'learners' | 'courses' | 'assignments' | 'analytics'
const TABS: { id: ClassTab; label: string }[] = [
  { id: 'learners', label: 'Learners' },
  { id: 'courses', label: 'Courses' },
  { id: 'assignments', label: 'Assignments' },
  { id: 'analytics', label: 'Analytics' },
]

export function ClassPage() {
  const classId = Number(useParams().classId)
  const [params, setParams] = useSearchParams()
  const requestedTab = params.get('tab') as ClassTab | null
  const tab = TABS.some((item) => item.id === requestedTab) ? requestedTab as ClassTab : 'learners'
  const detail = useQuery({
    queryKey: ['school-classes', 'detail', classId],
    queryFn: () => schoolApi.classDetail(classId),
    enabled: Number.isInteger(classId) && classId > 0,
  })
  const courses = useClassCourses(classId)
  const assignments = useClassAssignments(classId)
  const assignedCourseCount = courses.data?.filter((course) => course.assigned).length ?? 0

  if (detail.isLoading) return <ClassPageSkeleton />
  if (detail.isError || !detail.data) {
    return <Alert variant="danger">We couldn’t load this class. Return to your classes and try again.</Alert>
  }

  return (
    <div className="flex flex-col gap-6">
      <div>
        <Link to="/classes" className="inline-flex min-h-11 items-center text-sm font-semibold text-primary hover:underline">← All classes</Link>
        <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
          <div>
            <div className="flex flex-wrap items-center gap-2">
              <h1 className="font-display text-2xl font-bold text-foreground">{detail.data.name}</h1>
              {detail.data.level && <Badge variant="info">{detail.data.level}</Badge>}
            </div>
            <p className="mt-1 text-muted">Manage this class from one workspace.</p>
          </div>
          <LinkButton to={`/classes/${classId}/invite`}>Invite learner</LinkButton>
        </div>
      </div>

      <dl className="flex flex-wrap gap-x-8 gap-y-3 border-y border-border py-4">
        <ClassStat label="Learners" value={detail.data.students.length} />
        <ClassStat label="Assigned courses" value={assignedCourseCount} loading={courses.isLoading} />
        <ClassStat label="Assignments" value={assignments.data?.length ?? 0} loading={assignments.isLoading} />
        <ClassStat label="Teacher" value={detail.data.teacher ?? 'Not assigned'} />
      </dl>

      <div className="overflow-x-auto border-b border-border" role="tablist" aria-label="Class sections">
        <div className="flex min-w-max gap-6">
          {TABS.map((item) => (
            <button
              key={item.id}
              type="button"
              role="tab"
              aria-selected={tab === item.id}
              onClick={() => setParams(item.id === 'learners' ? {} : { tab: item.id }, { replace: true })}
              className={tab === item.id
                ? 'min-h-11 border-b-2 border-primary px-1 text-sm font-semibold text-primary'
                : 'min-h-11 border-b-2 border-transparent px-1 text-sm font-medium text-muted hover:text-foreground'}
            >
              {item.label}
            </button>
          ))}
        </div>
      </div>

      {tab === 'learners' && <LearnersPanel classId={classId} students={detail.data.students} />}
      {tab === 'courses' && <CoursesPanel classId={classId} />}
      {tab === 'assignments' && <AssignmentsPanel classId={classId} />}
      {tab === 'analytics' && <AnalyticsPanel classId={classId} />}
    </div>
  )
}

function ClassStat({ label, value, loading = false }: { label: string; value: string | number; loading?: boolean }) {
  return (
    <div className="min-w-24">
      <dt className="text-xs font-medium text-muted">{label}</dt>
      <dd className="mt-0.5 text-base font-semibold text-foreground">{loading ? '…' : value}</dd>
    </div>
  )
}

function LearnersPanel({ classId, students }: { classId: number; students: { learner_id: number; display_name: string | null }[] }) {
  const [adding, setAdding] = useState(false)

  return (
    <section aria-labelledby="learners-title" className="flex flex-col gap-4">
      <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
        <div>
          <h2 id="learners-title" className="text-lg font-semibold text-foreground">Class learners</h2>
          <p className="mt-1 text-sm text-muted">Add someone already in your school, or invite a new learner by email.</p>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" onClick={() => setAdding((value) => !value)}>{adding ? 'Close search' : 'Add learner'}</Button>
          <LinkButton to={`/classes/${classId}/invite`}>Invite new learner</LinkButton>
        </div>
      </div>
      {adding && <LearnerSearch classId={classId} onAdded={() => setAdding(false)} />}
      {students.length === 0 ? (
        <EmptyState title="No learners in this class" body="Search your school roster or send an invitation to build the class list." />
      ) : (
        <ul className="divide-y divide-border rounded-xl border border-border bg-surface">
          {students.map((student) => (
            <li key={student.learner_id} className="flex items-center gap-3 px-4 py-3">
              <Avatar name={student.display_name ?? 'Learner'} size="sm" />
              <span className="font-medium text-foreground">{student.display_name ?? 'Learner'}</span>
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}

function LearnerSearch({ classId, onAdded }: { classId: number; onAdded: () => void }) {
  const [search, setSearch] = useState('')
  const [error, setError] = useState<string | null>(null)
  const available = useAvailableClassLearners(classId, search)
  const addLearner = useAddClassLearner(classId)

  async function add(learnerId: number) {
    setError(null)
    try {
      await addLearner.mutateAsync({ learner_id: learnerId })
      onAdded()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not add this learner.')
    }
  }

  return (
    <div className="flex flex-col gap-3 rounded-xl bg-surface-muted p-4">
      {error && <Alert variant="danger">{error}</Alert>}
      <Input label="Search learners in your school" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Name or email—enter at least 2 characters" autoFocus />
      {available.isFetching && <Skeleton className="h-14" />}
      {available.isError && <Alert variant="danger">The school roster search is unavailable. Try again.</Alert>}
      {search.trim().length >= 2 && !available.isFetching && available.data?.length === 0 && (
        <p className="text-sm text-muted">No available learner in this school matches “{search.trim()}”.</p>
      )}
      {available.data && available.data.length > 0 && (
        <ul className="divide-y divide-border rounded-xl border border-border bg-surface">
          {available.data.map((learner) => (
            <li key={learner.id} className="flex items-center justify-between gap-3 p-3">
              <div className="min-w-0">
                <p className="font-medium text-foreground">{learner.display_name}</p>
                <p className="truncate text-sm text-muted">{learner.email ?? learner.level ?? 'School learner'}</p>
              </div>
              <Button size="sm" loading={addLearner.isPending && addLearner.variables?.learner_id === learner.id} onClick={() => void add(learner.id)}>Add</Button>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}

function CoursesPanel({ classId }: { classId: number }) {
  const courses = useClassCourses(classId)
  const assign = useAssignClassCourse(classId)
  const unassign = useUnassignClassCourse(classId)

  if (courses.isLoading) return <Skeleton className="h-64" />
  if (courses.isError || !courses.data) return <Alert variant="danger">We couldn’t load courses for this class.</Alert>

  const assigned = courses.data.filter((course) => course.assigned)
  const available = courses.data.filter((course) => !course.assigned)

  return (
    <section aria-labelledby="courses-title" className="flex flex-col gap-7">
      {(assign.isError || unassign.isError) && <Alert variant="danger">The course assignment could not be updated. Please try again.</Alert>}
      <div>
        <h2 id="courses-title" className="text-lg font-semibold text-foreground">Assigned courses</h2>
        <p className="mt-1 text-sm text-muted">Every current learner is enrolled when you assign a course. Future class members are enrolled automatically.</p>
      </div>
      {assigned.length === 0 ? (
        <EmptyState title="No courses assigned" body="Choose a published course below to make it available to every learner in this class." />
      ) : (
        <CourseList courses={assigned} busyId={unassign.isPending ? unassign.variables : undefined} actionLabel="Stop assigning" onAction={(id) => unassign.mutate(id)} />
      )}

      <div className="border-t border-border pt-6">
        <h3 className="font-semibold text-foreground">Available courses</h3>
        <p className="mt-1 text-sm text-muted">Published courses that are not assigned to this class.</p>
      </div>
      {available.length === 0 ? (
        <p className="text-sm text-muted">No additional published courses are available.</p>
      ) : (
        <CourseList courses={available} busyId={assign.isPending ? assign.variables : undefined} actionLabel="Assign" onAction={(id) => assign.mutate(id)} />
      )}
    </section>
  )
}

function CourseList({ courses, busyId, actionLabel, onAction }: {
  courses: Awaited<ReturnType<typeof schoolApi.classCourses>>
  busyId?: number
  actionLabel: 'Assign' | 'Stop assigning'
  onAction: (id: number) => void
}) {
  return (
    <ul className="divide-y divide-border rounded-xl border border-border bg-surface">
      {courses.map((course) => (
        <li key={course.id} className="flex flex-col justify-between gap-3 p-4 sm:flex-row sm:items-center">
          <div className="min-w-0">
            <div className="flex flex-wrap items-center gap-2">
              <p className="font-semibold text-foreground">{course.title}</p>
              {!course.is_published && <Badge variant="neutral">No longer published</Badge>}
            </div>
            <p className="mt-1 text-sm text-muted">{[course.language?.toUpperCase(), course.level_band].filter(Boolean).join(' · ') || 'All levels'}</p>
            {course.description && <p className="mt-1 max-w-3xl text-sm text-muted">{course.description}</p>}
          </div>
          <Button variant={actionLabel === 'Assign' ? 'primary' : 'outline'} loading={busyId === course.id} onClick={() => onAction(course.id)}>{actionLabel}</Button>
        </li>
      ))}
    </ul>
  )
}

function AssignmentsPanel({ classId }: { classId: number }) {
  const { hasRole } = useAuth()
  const canManageAssignments = hasRole('teacher')
  const assignments = useClassAssignments(classId)
  if (assignments.isLoading) return <Skeleton className="h-56" />
  if (assignments.isError || !assignments.data) return <Alert variant="danger">We couldn’t load assignments for this class.</Alert>

  return (
    <section aria-labelledby="assignments-title" className="flex flex-col gap-4">
      <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
        <div>
          <h2 id="assignments-title" className="text-lg font-semibold text-foreground">Class assignments</h2>
          <p className="mt-1 text-sm text-muted">Create work, review submissions, grade learners, and award badges.</p>
        </div>
        {canManageAssignments && <LinkButton to={`/assignments?class=${classId}`}>Manage assignments</LinkButton>}
      </div>
      {assignments.data.length === 0 ? (
        <EmptyState
          title="No assignments yet"
          body={canManageAssignments ? 'Create the first assignment for this class from the assignment workspace.' : 'The assigned teacher has not created an assignment for this class yet.'}
          action={canManageAssignments ? <LinkButton to={`/assignments?class=${classId}`}>Create assignment</LinkButton> : undefined}
        />
      ) : (
        <ul className="divide-y divide-border rounded-xl border border-border bg-surface">
          {assignments.data.map((assignment) => (
            <li key={assignment.id} className="flex flex-col justify-between gap-2 p-4 sm:flex-row sm:items-center">
              <div>
                <p className="font-semibold text-foreground">{assignment.title}</p>
                <p className="mt-1 text-sm text-muted">{assignment.submitted_count}/{assignment.total_students} submitted · {assignment.graded_count} graded</p>
              </div>
              <div className="flex items-center gap-2">
                {assignment.coin_reward > 0 && <Badge variant="gold">{assignment.coin_reward} coins</Badge>}
                {assignment.due_at && <span className="text-sm text-muted">Due {new Date(assignment.due_at).toLocaleDateString()}</span>}
              </div>
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}

function AnalyticsPanel({ classId }: { classId: number }) {
  const analytics = useQuery({
    queryKey: ['school-classes', 'analytics', classId],
    queryFn: () => schoolApi.classAnalytics(classId),
  })
  if (analytics.isLoading) return <Skeleton className="h-64" />
  if (analytics.isError || !analytics.data) return <Alert variant="danger">We couldn’t load analytics for this class.</Alert>
  if (analytics.data.students.length === 0) return <EmptyState title="No learner activity yet" body="Analytics will appear after learners join the class and begin lessons or assignments." />

  return (
    <section aria-labelledby="analytics-title" className="flex flex-col gap-4">
      <div>
        <h2 id="analytics-title" className="text-lg font-semibold text-foreground">Learner progress</h2>
        <p className="mt-1 text-sm text-muted">Lesson, quiz, speaking, and assignment activity for this class.</p>
      </div>
      <div className="overflow-x-auto rounded-xl border border-border">
        <table className="w-full min-w-[720px] text-left text-sm">
          <thead className="bg-surface-muted text-xs text-muted">
            <tr><th className="px-4 py-3 font-semibold">Learner</th><th className="px-3 py-3 text-right font-semibold">Lessons</th><th className="px-3 py-3 text-right font-semibold">Average</th><th className="px-3 py-3 text-right font-semibold">Quiz</th><th className="px-3 py-3 text-right font-semibold">Speaking</th><th className="px-4 py-3 text-right font-semibold">Assignments</th></tr>
          </thead>
          <tbody className="divide-y divide-border bg-surface">
            {analytics.data.students.map((student) => <AnalyticsRow key={student.learner_id} student={student} />)}
          </tbody>
        </table>
      </div>
    </section>
  )
}

function AnalyticsRow({ student }: { student: ClassAnalyticsStudent }) {
  return (
    <tr>
      <td className="px-4 py-3 font-medium text-foreground">{student.display_name ?? 'Learner'}</td>
      <td className="px-3 py-3 text-right tabular-nums text-foreground">{student.lessons_completed}</td>
      <td className="px-3 py-3 text-right tabular-nums text-muted">{student.avg_score == null ? '—' : `${student.avg_score}%`}</td>
      <td className="px-3 py-3 text-right tabular-nums text-muted">{student.quiz_accuracy == null ? '—' : `${student.quiz_accuracy}%`}</td>
      <td className="px-3 py-3 text-right tabular-nums text-muted">{student.speaking_count}</td>
      <td className="px-4 py-3 text-right tabular-nums text-muted">{student.assignments_submitted === 0 ? '—' : `${student.assignments_passed}/${student.assignments_submitted}`}</td>
    </tr>
  )
}

function EmptyState({ title, body, action }: { title: string; body: string; action?: React.ReactNode }) {
  return (
    <div className="flex flex-col items-start gap-2 rounded-xl bg-surface-muted px-5 py-7">
      <p className="font-semibold text-foreground">{title}</p>
      <p className="max-w-2xl text-sm text-muted">{body}</p>
      {action && <div className="mt-2">{action}</div>}
    </div>
  )
}

function ClassPageSkeleton() {
  return <div className="flex flex-col gap-5"><Skeleton className="h-20" /><Skeleton className="h-14" /><Skeleton className="h-72" /></div>
}
