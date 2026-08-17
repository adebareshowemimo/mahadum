import { Alert, Badge, Card, CardBody, Icon } from '@/components/ui'
import { Column, DataTable } from '@/components/admin/DataTable'
import type { CoursePerformance } from '@/lib/api'
import { formatMoney } from '@/lib/format'
import { useCoursesPerformance } from '@/lib/content/queries'

/** Subscription statuses shown in the per-course breakdown, in reporting order. */
const SUBSCRIPTION_STATUSES: { key: string; label: string; tone: 'success' | 'gold' | 'neutral' }[] = [
  { key: 'active', label: 'active', tone: 'success' },
  { key: 'pending', label: 'pending', tone: 'gold' },
  { key: 'cancelled', label: 'cancelled', tone: 'neutral' },
]

export function CoursePerformancePage() {
  const { data, isLoading, isError } = useCoursesPerformance()

  const columns: Column<CoursePerformance>[] = [
    {
      key: 'title',
      header: 'Course',
      render: (c) => (
        <div>
          <p className="font-semibold text-foreground">{c.title}</p>
          <p className="text-xs text-muted">
            {c.language} · {c.levels_count} levels · {c.lessons_count} lessons
          </p>
        </div>
      ),
    },
    {
      key: 'status',
      header: 'Status',
      render: (c) => <Badge variant={c.is_published ? 'success' : 'neutral'}>{c.is_published ? 'Published' : 'Draft'}</Badge>,
      hideOnMobile: true,
    },
    {
      key: 'subscriptions',
      header: 'Subscriptions',
      render: (c) => {
        const shown = SUBSCRIPTION_STATUSES.filter((s) => (c.subscriptions_by_status[s.key] ?? 0) > 0)
        // Any status the backend reports that isn't one of the three headline
        // ones (e.g. past_due) still needs to be visible, not silently dropped.
        const others = Object.entries(c.subscriptions_by_status).filter(
          ([status, count]) => count > 0 && !SUBSCRIPTION_STATUSES.some((s) => s.key === status),
        )

        if (shown.length === 0 && others.length === 0) {
          return <span className="text-muted">—</span>
        }

        return (
          <div className="flex flex-wrap gap-1.5">
            {shown.map((s) => (
              <Badge key={s.key} variant={s.tone}>
                {c.subscriptions_by_status[s.key]} {s.label}
              </Badge>
            ))}
            {others.map(([status, count]) => (
              <Badge key={status} variant="neutral">
                {count} {status.replace(/_/g, ' ')}
              </Badge>
            ))}
          </div>
        )
      },
    },
    {
      key: 'earned',
      header: 'Attributed revenue',
      render: (c) => (
        <div>
          <p className="font-semibold text-foreground">{formatMoney(c.attributed_revenue_minor, 'NGN')}</p>
          <p className="text-xs text-muted">{c.subscribers} subscriber{c.subscribers === 1 ? '' : 's'}</p>
        </div>
      ),
    },
    {
      key: 'referral',
      header: 'Referral',
      render: (c) => (
        <div>
          <p className="font-semibold text-foreground">{formatMoney(c.referral_revenue_minor, 'NGN')}</p>
          <p className="text-xs text-muted">
            {c.referred_subscribers} referred · {formatMoney(c.referral_commission_minor, 'NGN')} commission
          </p>
        </div>
      ),
    },
    {
      key: 'pending',
      header: 'Pending',
      render: (c) => (
        <div>
          <p className="font-semibold text-foreground">{formatMoney(c.pending_revenue_minor, 'NGN')}</p>
          <p className="text-xs text-muted">{formatMoney(c.pending_commission_minor, 'NGN')} in escrow</p>
        </div>
      ),
    },
    {
      key: 'learning',
      header: 'Learning',
      render: (c) => (
        <div>
          <p className="font-semibold text-foreground">{c.enrollments} enrolled</p>
          <p className="text-xs text-muted">
            {c.lesson_completions} completions ·{' '}
            {c.quiz_accuracy == null ? 'no quiz data' : `${Math.round(c.quiz_accuracy * 100)}% accuracy`}
          </p>
        </div>
      ),
      hideOnMobile: true,
    },
  ]

  const rows = data ?? []
  const totals = rows.reduce(
    (acc, c) => ({
      revenue: acc.revenue + c.attributed_revenue_minor,
      pending: acc.pending + c.pending_revenue_minor,
      referral: acc.referral + c.referral_revenue_minor,
      escrow: acc.escrow + c.pending_commission_minor,
    }),
    { revenue: 0, pending: 0, referral: 0, escrow: 0 },
  )

  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="font-display text-2xl font-bold text-foreground">Content performance</h1>
        <p className="mt-1 text-muted">
          How your courses are earning and being learned — subscriptions, referral revenue and pending income per course.
        </p>
      </div>

      {rows.length > 0 && (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <StatTile label="Attributed revenue" value={formatMoney(totals.revenue, 'NGN')} hint="Active subscriptions" />
          <StatTile label="Pending revenue" value={formatMoney(totals.pending, 'NGN')} hint="Not yet confirmed" />
          <StatTile label="From referrals" value={formatMoney(totals.referral, 'NGN')} hint="Referred subscribers" />
          <StatTile label="Commission in escrow" value={formatMoney(totals.escrow, 'NGN')} hint="Clears after 14 days" />
        </div>
      )}

      <Alert variant="info" icon={<Icon name="sparkles" className="size-4" />}>
        <strong>Attributed, not billed.</strong> MAHADUM.360 sells platform subscriptions rather than individual
        courses, so revenue here is estimated: each subscriber’s plan price is split evenly across the courses their
        learners are enrolled in. A parent on a ₦6,000 plan with learners in three courses contributes ₦2,000 to each.
        Use it to compare courses against each other, not as a billing statement.
      </Alert>

      {isError && <Alert variant="danger">Couldn’t load course performance.</Alert>}

      <DataTable
        columns={columns}
        rows={rows}
        getRowId={(c) => c.id}
        isLoading={isLoading}
        empty="No courses yet."
      />
    </div>
  )
}

function StatTile({ label, value, hint }: { label: string; value: string; hint: string }) {
  return (
    <Card>
      <CardBody className="flex flex-col gap-1 py-4">
        <p className="text-xs font-medium uppercase tracking-wide text-muted">{label}</p>
        <p className="font-display text-2xl font-extrabold text-foreground">{value}</p>
        <p className="text-xs text-muted">{hint}</p>
      </CardBody>
    </Card>
  )
}
