import { Alert, Badge, Card, CardBody, CardHeader, CardTitle, Icon, Skeleton } from '@/components/ui'
import type { IconName } from '@/components/ui'
import { formatMoney } from '@/lib/format'
import { useAdminMetrics, useBillingHealth } from '@/lib/admin/queries'
import { DataTable } from '@/components/admin/DataTable'

function sum(map: Record<string, number>): number {
  return Object.values(map ?? {}).reduce((a, b) => a + b, 0)
}

function StatusChips({ map, labels }: { map: Record<string, number>; labels?: Record<string, string> }) {
  const entries = Object.entries(map ?? {})
  if (entries.length === 0) return <span className="text-sm text-muted">None</span>
  return (
    <div className="flex flex-wrap gap-1.5">
      {entries.map(([status, count]) => (
        <Badge key={status} variant="neutral">
          {count} {labels?.[status] ?? status}
        </Badge>
      ))}
    </div>
  )
}

export function AdminOverviewPage() {
  const metrics = useAdminMetrics()
  const health = useBillingHealth()

  if (metrics.isLoading) return <Skeleton className="h-40" />
  if (metrics.isError || !metrics.data) return <Alert variant="danger">Couldn’t load platform metrics.</Alert>

  const m = metrics.data
  const rate = health.data?.telco.success_rate

  return (
    <div className="flex flex-col gap-8">
      <h1 className="font-display text-2xl font-bold text-foreground">Platform overview</h1>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Kpi icon="users" label="Users" value={m.users.toLocaleString()} />
        <Kpi icon="wallet" label="Recorded revenue" value={formatMoney(m.revenue_minor, 'NGN')} />
        <Kpi icon="building" label="Organizations" value={sum(m.organizations)} />
        <Kpi icon="book" label="Languages" value={m.languages} />
      </div>

      {m.revenue_channels && <Card><CardBody className="flex flex-col gap-3">
        <h2 className="font-semibold text-foreground">Recorded receipts · NGN</h2>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">{Object.entries(m.revenue_channels).map(([key, amount]) => <div key={key}><p className="text-xs text-muted">{{wallet_funding_minor: 'Wallet funding', school_invoices_minor: 'Paid school invoices', telco_minor: 'Telco billing', subscription_payments_minor: 'Subscription payments, less refunds'}[key] ?? key}</p><p className="font-semibold text-foreground">{formatMoney(amount, 'NGN')}</p></div>)}</div>
        <p className="text-xs text-muted">Subscription receipts are recorded from this release onward; earlier subscription charges are unavailable. Data sales are excluded. These receipts are not profit or provider settlement totals.</p>
      </CardBody></Card>}

      <div className="grid gap-4 lg:grid-cols-3">
        <Card>
          <CardHeader>
            <CardTitle>Users by type</CardTitle>
          </CardHeader>
          <CardBody>
            <StatusChips map={m.users_by_type} labels={{ single: 'Individual' }} />
          </CardBody>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle>Organizations</CardTitle>
          </CardHeader>
          <CardBody>
            <StatusChips map={m.organizations} />
          </CardBody>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle>Subscriptions</CardTitle>
          </CardHeader>
          <CardBody>
            <StatusChips map={m.subscriptions} />
          </CardBody>
        </Card>
      </div>

      <section className="flex flex-col gap-3" aria-labelledby="language-analytics-title">
        <h2 id="language-analytics-title" className="font-display text-lg font-bold text-foreground">Language analytics</h2>
        <p className="text-sm text-muted">All-time enrolled learners, completed lessons and completed scored quiz attempts, including inactive languages.</p>
        <DataTable rows={m.language_analytics ?? []} getRowId={row => row.id} empty="No language activity recorded." columns={[
          { key: 'language', header: 'Language', render: row => <span className="font-semibold">{row.name}{!row.active && <span className="ml-2 text-xs text-muted">Inactive</span>}</span> },
          { key: 'learners', header: 'Learners', render: row => row.learners },
          { key: 'lessons', header: 'Completed lessons', render: row => row.lessons_completed },
          { key: 'quizzes', header: 'Scored quizzes', render: row => row.quizzes_scored },
          { key: 'score', header: 'Average quiz score', render: row => row.avg_quiz_score == null ? '—' : `${row.avg_quiz_score}%` },
        ]} />
      </section>
      <Card><CardHeader><CardTitle>AI pronunciation analytics</CardTitle></CardHeader><CardBody className="flex flex-col gap-3">
        <p className="text-sm text-muted">AI scoring is deferred. The feature flag is {m.ai_analytics?.enabled ? 'on' : 'off'}; it does not prove that scoring is available.</p>
        {m.ai_analytics ? <dl className="grid gap-4 sm:grid-cols-4">{Object.entries({Submissions: m.ai_analytics.submissions, 'Scored submissions': m.ai_analytics.scored, 'Needs review': m.ai_analytics.needs_review, 'Average stored score': m.ai_analytics.avg_stored_score ?? '—'}).map(([label, value]) => <div key={label}><dt className="text-xs text-muted">{label}</dt><dd className="mt-1 text-xl font-bold text-foreground">{value}</dd></div>)}</dl> : <p className="text-sm text-muted">No scoring data available.</p>}
        <p className="text-xs text-muted">Existing stored scores only. Provider usage, cost and latency are not recorded.</p>
      </CardBody></Card>

      <section>
        <h2 className="mb-3 font-display text-lg font-bold text-foreground">Billing health</h2>
        {health.isLoading ? (
          <Skeleton className="h-28" />
        ) : health.isError || !health.data ? (
          <Alert variant="warning">Billing health is unavailable right now.</Alert>
        ) : (
          <div className="grid gap-4 lg:grid-cols-3">
            <Card>
              <CardHeader>
                <CardTitle>Telco billing</CardTitle>
              </CardHeader>
              <CardBody className="flex flex-col gap-1">
                <p className="font-display text-3xl font-bold text-foreground">
                  {rate == null ? '—' : `${Math.round(rate * 100)}%`}
                </p>
                <p className="text-sm text-muted">
                  {health.data.telco.success}/{health.data.telco.attempts} attempts succeeded
                </p>
              </CardBody>
            </Card>
            <Card>
              <CardHeader>
                <CardTitle>Wallet funding</CardTitle>
              </CardHeader>
              <CardBody>
                <StatusChips map={health.data.funding} />
              </CardBody>
            </Card>
            <Card>
              <CardHeader>
                <CardTitle>Subscriptions</CardTitle>
              </CardHeader>
              <CardBody>
                <StatusChips map={health.data.subscriptions} />
              </CardBody>
            </Card>
          </div>
        )}
      </section>
    </div>
  )
}

function Kpi({ icon, label, value }: { icon: IconName; label: string; value: string | number }) {
  return (
    <Card>
      <CardBody className="flex items-center gap-3">
        <span className="flex size-11 items-center justify-center rounded-xl bg-primary-soft text-primary">
          <Icon name={icon} />
        </span>
        <div>
          <p className="font-display text-2xl font-bold text-foreground">{value}</p>
          <p className="text-xs text-muted">{label}</p>
        </div>
      </CardBody>
    </Card>
  )
}
