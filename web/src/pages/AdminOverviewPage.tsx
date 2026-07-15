import { Alert, Badge, Card, CardBody, CardHeader, CardTitle, Icon, Skeleton } from '@/components/ui'
import type { IconName } from '@/components/ui'
import { formatMoney } from '@/lib/format'
import { useAdminMetrics, useBillingHealth } from '@/lib/admin/queries'

function sum(map: Record<string, number>): number {
  return Object.values(map ?? {}).reduce((a, b) => a + b, 0)
}

function StatusChips({ map }: { map: Record<string, number> }) {
  const entries = Object.entries(map ?? {})
  if (entries.length === 0) return <span className="text-sm text-muted">None</span>
  return (
    <div className="flex flex-wrap gap-1.5">
      {entries.map(([status, count]) => (
        <Badge key={status} variant="neutral">
          {count} {status}
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
        <Kpi icon="wallet" label="Revenue" value={formatMoney(m.revenue_minor, 'NGN')} />
        <Kpi icon="building" label="Organizations" value={sum(m.organizations)} />
        <Kpi icon="book" label="Languages" value={m.languages} />
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        <Card>
          <CardHeader>
            <CardTitle>Users by type</CardTitle>
          </CardHeader>
          <CardBody>
            <StatusChips map={m.users_by_type} />
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
