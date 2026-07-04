import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Alert, Avatar, Badge, Card, CardBody, Modal, Skeleton } from '@/components/ui'
import { schoolApi } from '@/lib/api'
import { useClasses, schoolKeys } from '@/lib/school/queries'

export function ClassesPage() {
  const { data, isLoading, isError } = useClasses()
  const [openId, setOpenId] = useState<number | null>(null)

  if (isLoading) return <Skeleton className="h-48" />
  if (isError || !data) return <Alert variant="danger">We couldn’t load your classes. Please refresh and try again.</Alert>

  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="font-display text-2xl font-bold text-foreground">My classes</h1>
        <p className="mt-1 text-muted">Tap a class to see its students.</p>
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
    </div>
  )
}

function ClassDetailModal({ classId, onClose }: { classId: number | null; onClose: () => void }) {
  const [tab, setTab] = useState<'students' | 'analytics'>('students')
  const detail = useQuery({
    queryKey: [...schoolKeys.classes, 'detail', classId],
    queryFn: () => schoolApi.classDetail(classId as number),
    enabled: classId != null,
  })

  return (
    <Modal open={classId != null} onClose={onClose} title={detail.data?.name ?? 'Class'} description={detail.data?.level ?? undefined} className="sm:max-w-2xl">
      <div className="mb-4 flex gap-1 rounded-xl bg-surface-muted p-1">
        {(['students', 'analytics'] as const).map((t) => (
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
        detail.isLoading ? (
          <Skeleton className="h-32" />
        ) : detail.isError || !detail.data ? (
          <Alert variant="danger">Couldn’t load this class.</Alert>
        ) : detail.data.students.length === 0 ? (
          <p className="py-6 text-center text-sm text-muted">No students enrolled yet.</p>
        ) : (
          <ul className="flex max-h-80 flex-col gap-2 overflow-y-auto">
            {detail.data.students.map((s) => (
              <li key={s.learner_id} className="flex items-center gap-3 rounded-xl border border-border p-2.5">
                <Avatar name={s.display_name ?? 'Student'} size="sm" />
                <span className="font-medium text-foreground">{s.display_name ?? 'Student'}</span>
              </li>
            ))}
          </ul>
        )
      ) : (
        <ClassAnalyticsTab classId={classId} />
      )}
    </Modal>
  )
}

function ClassAnalyticsTab({ classId }: { classId: number | null }) {
  const analytics = useQuery({
    queryKey: [...schoolKeys.classes, 'analytics', classId],
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
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
