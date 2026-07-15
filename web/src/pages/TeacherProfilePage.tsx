import { useQueries } from '@tanstack/react-query'
import { Alert, Badge, Card, CardBody, CardHeader, CardTitle, Icon, Skeleton } from '@/components/ui'
import type { IconName } from '@/components/ui'
import { schoolApi } from '@/lib/api'
import { useAuth } from '@/lib/auth/AuthProvider'
import { schoolKeys, useMyClasses } from '@/lib/school/queries'

export function TeacherProfilePage() {
  const { user } = useAuth()
  const classes = useMyClasses()

  const assignmentQueries = useQueries({
    queries: (classes.data ?? []).map((c) => ({
      queryKey: schoolKeys.assignments(c.id),
      queryFn: () => schoolApi.classAssignments(c.id),
      enabled: !!classes.data,
    })),
  })

  const assignmentsCreated = assignmentQueries.reduce((sum, q) => sum + (q.data?.length ?? 0), 0)
  const totalStudents = (classes.data ?? []).reduce((sum, c) => sum + c.students, 0)
  const loadingStats = classes.isLoading || assignmentQueries.some((q) => q.isLoading)

  return (
    <div className="flex flex-col gap-8">
      <h1 className="font-display text-2xl font-bold text-foreground">Your teaching profile</h1>

      <div className="grid gap-4 sm:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Your account</CardTitle>
          </CardHeader>
          <CardBody className="flex flex-col gap-2 text-sm">
            <Row label="Email" value={user?.user.email} />
            <Row label="Verified" value={user?.user.email_verified ? 'Yes' : 'No'} />
            <Row
              label="Institution"
              value={user?.organizations.length ? user.organizations.map((o) => o.name).filter(Boolean).join(', ') : undefined}
            />
          </CardBody>
        </Card>

        <div className="grid grid-cols-3 gap-3">
          <Kpi icon="cap" label="Classes" value={classes.data?.length ?? 0} loading={classes.isLoading} />
          <Kpi icon="users" label="Students" value={totalStudents} loading={loadingStats} />
          <Kpi icon="clipboard" label="Assignments" value={assignmentsCreated} loading={loadingStats} />
        </div>
      </div>

      <section>
        <h2 className="mb-3 font-display text-lg font-bold text-foreground">Classes you teach</h2>
        {classes.isLoading ? (
          <Skeleton className="h-24" />
        ) : classes.isError ? (
          <Alert variant="danger">Couldn’t load your classes.</Alert>
        ) : (classes.data?.length ?? 0) === 0 ? (
          <Card>
            <CardBody className="py-8 text-center text-sm text-muted">You aren’t teaching any classes yet.</CardBody>
          </Card>
        ) : (
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {classes.data?.map((c) => (
              <Card key={c.id}>
                <CardBody className="flex flex-col gap-1">
                  <div className="flex items-center justify-between">
                    <p className="font-semibold text-foreground">{c.name}</p>
                    {c.level && <Badge variant="info">{c.level}</Badge>}
                  </div>
                  <p className="text-sm text-muted">{c.students} students</p>
                </CardBody>
              </Card>
            ))}
          </div>
        )}
      </section>
    </div>
  )
}

function Row({ label, value }: { label: string; value?: string }) {
  return (
    <div className="flex items-center justify-between">
      <span className="text-muted">{label}</span>
      <span className="font-medium text-foreground">{value ?? '—'}</span>
    </div>
  )
}

function Kpi({ icon, label, value, loading }: { icon: IconName; label: string; value: number; loading?: boolean }) {
  return (
    <Card>
      <CardBody className="flex flex-col items-center gap-1.5 text-center">
        <span className="flex size-9 items-center justify-center rounded-xl bg-primary-soft text-primary">
          <Icon name={icon} />
        </span>
        <p className="font-display text-xl font-bold text-foreground">{loading ? '—' : value}</p>
        <p className="text-xs text-muted">{label}</p>
      </CardBody>
    </Card>
  )
}
