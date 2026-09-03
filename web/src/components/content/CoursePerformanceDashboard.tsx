import { useMemo, useState } from 'react'
import { Alert, Badge, Button, Card, Icon, LinkButton } from '@/components/ui'
import { AdminToolbar, DataTable, FilterSelect, type Column } from '@/components/admin'
import type { CoursePerformance } from '@/lib/api'
import { useCoursesPerformance } from '@/lib/content/queries'
import { formatMoney } from '@/lib/format'

const PAGE_SIZE = 8

const SUBSCRIPTION_STATUSES: { key: string; label: string; tone: 'success' | 'gold' | 'neutral' }[] = [
  { key: 'active', label: 'active', tone: 'success' },
  { key: 'pending', label: 'pending', tone: 'gold' },
  { key: 'cancelled', label: 'cancelled', tone: 'neutral' },
]

interface CoursePerformanceDashboardProps {
  variant?: 'page' | 'dashboard'
}

/** Shared per-course economics view used on the content-owner home and report page. */
export function CoursePerformanceDashboard({ variant = 'page' }: CoursePerformanceDashboardProps) {
  const { data, isLoading, isFetching, isError } = useCoursesPerformance()
  const [search, setSearch] = useState('')
  const [publication, setPublication] = useState('')
  const [page, setPage] = useState(1)

  const rows = data ?? []
  const totals = useMemo(
    () =>
      rows.reduce(
        (acc, course) => ({
          active: acc.active + (course.subscriptions_by_status.active ?? 0),
          pendingSubscriptions: acc.pendingSubscriptions + (course.subscriptions_by_status.pending ?? 0),
          referralUsers: acc.referralUsers + course.referred_subscribers,
          referralRevenue: acc.referralRevenue + course.referral_revenue_minor,
          pendingRevenue: acc.pendingRevenue + course.pending_revenue_minor + course.pending_commission_minor,
        }),
        { active: 0, pendingSubscriptions: 0, referralUsers: 0, referralRevenue: 0, pendingRevenue: 0 },
      ),
    [rows],
  )

  const filtered = useMemo(() => {
    const term = search.trim().toLocaleLowerCase()
    return rows.filter((course) => {
      const matchesSearch =
        !term || course.title.toLocaleLowerCase().includes(term) || course.language.toLocaleLowerCase().includes(term)
      const matchesPublication =
        !publication || (publication === 'published' ? course.is_published : !course.is_published)
      return matchesSearch && matchesPublication
    })
  }, [publication, rows, search])

  const pageCount = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE))
  const safePage = Math.min(page, pageCount)
  const visibleRows = filtered.slice((safePage - 1) * PAGE_SIZE, safePage * PAGE_SIZE)

  const columns: Column<CoursePerformance>[] = [
    {
      key: 'title',
      header: 'Course',
      render: (course) => (
        <div className="min-w-44">
          <div className="flex flex-wrap items-center gap-2">
            <p className="font-semibold text-foreground">{course.title}</p>
            <Badge variant={course.is_published ? 'success' : 'neutral'}>
              {course.is_published ? 'Published' : 'Draft'}
            </Badge>
          </div>
          <p className="mt-1 text-xs text-muted">
            {course.language} · {course.levels_count} levels · {course.lessons_count} lessons
          </p>
        </div>
      ),
    },
    {
      key: 'subscriptions',
      header: 'Subscriptions',
      render: (course) => <SubscriptionBreakdown course={course} />,
    },
    {
      key: 'referral',
      header: 'Referral',
      render: (course) => (
        <div className="min-w-36">
          <p className="font-semibold text-foreground">{formatMoney(course.referral_revenue_minor, 'NGN')}</p>
          <p className="mt-1 text-xs text-muted">
            {course.referred_subscribers} referred user{course.referred_subscribers === 1 ? '' : 's'}
          </p>
        </div>
      ),
    },
    {
      key: 'pending',
      header: 'Pending',
      render: (course) => (
        <div className="min-w-36">
          <p className="font-semibold text-foreground">
            {formatMoney(course.pending_revenue_minor + course.pending_commission_minor, 'NGN')}
          </p>
          <p className="mt-1 text-xs text-muted">
            {formatMoney(course.pending_revenue_minor, 'NGN')} unconfirmed ·{' '}
            {formatMoney(course.pending_commission_minor, 'NGN')} escrow
          </p>
        </div>
      ),
    },
    {
      key: 'earned',
      header: 'Attributed revenue',
      render: (course) => (
        <div className="min-w-32">
          <p className="font-semibold text-foreground">{formatMoney(course.attributed_revenue_minor, 'NGN')}</p>
          <p className="mt-1 text-xs text-muted">
            {course.subscribers} subscriber{course.subscribers === 1 ? '' : 's'}
          </p>
        </div>
      ),
    },
  ]

  const updateSearch = (value: string) => {
    setSearch(value)
    setPage(1)
  }

  const updatePublication = (value: string) => {
    setPublication(value)
    setPage(1)
  }

  return (
    <section className="flex flex-col gap-5" aria-labelledby="course-performance-heading">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          {variant === 'page' ? (
            <h1 id="course-performance-heading" className="font-display text-2xl font-bold text-foreground">
              Content performance
            </h1>
          ) : (
            <h2 id="course-performance-heading" className="font-display text-xl font-bold text-foreground">
              Performance by course
            </h2>
          )}
          <p className="mt-1 max-w-3xl text-sm text-muted">
            Subscription health, referral contribution, and earnings awaiting confirmation for every course you own.
          </p>
        </div>
        {variant === 'dashboard' && (
          <div className="flex flex-wrap gap-2">
            <LinkButton to="/courses" variant="secondary" size="sm">Manage courses</LinkButton>
            <LinkButton to="/content/performance" variant="outline" size="sm">Open full report</LinkButton>
          </div>
        )}
      </div>

      {rows.length > 0 && (
        <Card className="overflow-hidden shadow-none">
          <div className="grid sm:grid-cols-2 lg:grid-cols-4">
            <Summary label="Active subscriptions" value={totals.active.toLocaleString()} hint={`${totals.pendingSubscriptions} pending`} />
            <Summary label="Referral users" value={totals.referralUsers.toLocaleString()} hint="Across your courses" />
            <Summary label="Referral revenue" value={formatMoney(totals.referralRevenue, 'NGN')} hint="From active subscriptions" />
            <Summary label="Pending earnings" value={formatMoney(totals.pendingRevenue, 'NGN')} hint="Unconfirmed and in escrow" />
          </div>
        </Card>
      )}

      <Alert variant="info" icon={<Icon name="sparkles" className="size-4" />}>
        Revenue is attributed for comparison because subscriptions cover the whole platform. Each subscriber’s plan
        value is divided across the courses their learners use; it is not a billing statement.
      </Alert>

      {isError && <Alert variant="danger">Couldn’t load course performance. Please try again.</Alert>}

      <DataTable
        columns={columns}
        rows={visibleRows}
        getRowId={(course) => course.id}
        isLoading={isLoading}
        empty={rows.length === 0 ? 'No courses yet. Create a course to begin tracking performance.' : 'No courses match these filters.'}
        toolbar={
          <AdminToolbar search={search} onSearch={updateSearch} searchPlaceholder="Search courses or languages">
            <FilterSelect
              label="Status"
              value={publication}
              onChange={updatePublication}
              allLabel="All courses"
              options={[
                { value: 'published', label: 'Published' },
                { value: 'draft', label: 'Draft' },
              ]}
            />
          </AdminToolbar>
        }
      />

      {!isLoading && filtered.length > 0 && (
        <div className="flex flex-wrap items-center justify-between gap-3 text-sm text-muted" aria-live="polite">
          <span>
            Showing {(safePage - 1) * PAGE_SIZE + 1}–{Math.min(safePage * PAGE_SIZE, filtered.length)} of{' '}
            {filtered.length} courses
          </span>
          <div className="flex items-center gap-2">
            <Button variant="ghost" size="sm" disabled={safePage <= 1 || isFetching} onClick={() => setPage((current) => current - 1)}>
              Previous
            </Button>
            <span className="font-medium text-foreground">Page {safePage} of {pageCount}</span>
            <Button variant="ghost" size="sm" disabled={safePage >= pageCount || isFetching} onClick={() => setPage((current) => current + 1)}>
              Next
            </Button>
          </div>
        </div>
      )}
    </section>
  )
}

function SubscriptionBreakdown({ course }: { course: CoursePerformance }) {
  const headline = SUBSCRIPTION_STATUSES.map((status) => ({
    ...status,
    count: course.subscriptions_by_status[status.key] ?? 0,
  }))
  const others = Object.entries(course.subscriptions_by_status).filter(
    ([status, count]) => count > 0 && !SUBSCRIPTION_STATUSES.some((item) => item.key === status),
  )

  return (
    <div className="flex min-w-44 flex-wrap gap-1.5" aria-label={`Subscription status for ${course.title}`}>
      {headline.map((status) => (
        <Badge key={status.key} variant={status.tone}>
          {status.count} {status.label}
        </Badge>
      ))}
      {others.map(([status, count]) => (
        <Badge key={status} variant="neutral">{count} {status.replace(/_/g, ' ')}</Badge>
      ))}
    </div>
  )
}

function Summary({ label, value, hint }: { label: string; value: string; hint: string }) {
  return (
    <div className="border-b border-border p-4 last:border-b-0 sm:odd:border-r lg:border-b-0 lg:border-r lg:last:border-r-0">
      <p className="text-sm font-medium text-muted">{label}</p>
      <p className="mt-1 font-display text-xl font-bold text-foreground">{value}</p>
      <p className="mt-0.5 text-xs text-muted">{hint}</p>
    </div>
  )
}
