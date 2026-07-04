import { Alert, Badge, Card, CardBody, CardHeader, CardTitle, Skeleton } from '@/components/ui'
import type { CommissionStat } from '@/lib/api'
import { formatMoney } from '@/lib/format'
import { useSettlements } from '@/lib/admin/queries'

function humanize(s: string): string {
  return s.replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase())
}

const TONE: Record<string, 'success' | 'gold' | 'danger' | 'neutral'> = {
  cleared: 'success',
  paid: 'success',
  approved: 'success',
  escrow: 'gold',
  escrowed: 'gold',
  requested: 'gold',
  clawback_pending: 'danger',
  clawed_back: 'danger',
  rejected: 'danger',
}

function StatTable({ title, rows }: { title: string; rows: Record<string, CommissionStat> }) {
  const entries = Object.values(rows ?? {})
  return (
    <Card>
      <CardHeader>
        <CardTitle>{title}</CardTitle>
      </CardHeader>
      <CardBody className="flex flex-col gap-2">
        {entries.length === 0 ? (
          <p className="text-sm text-muted">None yet.</p>
        ) : (
          entries.map((r) => (
            <div key={r.status} className="flex items-center justify-between">
              <span className="flex items-center gap-2 text-sm">
                <Badge variant={TONE[r.status] ?? 'neutral'}>{humanize(r.status)}</Badge>
                <span className="text-muted">×{r.c}</span>
              </span>
              <span className="font-semibold text-foreground">{formatMoney(r.total, 'NGN')}</span>
            </div>
          ))
        )}
      </CardBody>
    </Card>
  )
}

export function SettlementsPage() {
  const { data, isLoading, isError } = useSettlements()

  if (isLoading) return <Skeleton className="h-48" />
  if (isError || !data) return <Alert variant="danger">Couldn’t load settlements.</Alert>

  return (
    <div className="flex flex-col gap-8">
      <h1 className="font-display text-2xl font-bold text-foreground">Settlements</h1>

      <div className="grid gap-4 sm:grid-cols-2">
        <Card>
          <CardBody>
            <p className="text-sm text-muted">Telco revenue</p>
            <p className="font-display text-2xl font-bold text-foreground">
              {formatMoney(data.telco_revenue_minor, 'NGN')}
            </p>
          </CardBody>
        </Card>
        <Card className={data.clawback.pending_count > 0 ? 'border-danger' : undefined}>
          <CardBody>
            <p className="text-sm text-muted">Clawback pending</p>
            <p className="font-display text-2xl font-bold text-foreground">
              {formatMoney(data.clawback.pending_minor, 'NGN')}
            </p>
            <p className="text-xs text-muted">{data.clawback.pending_count} commission(s) to recover</p>
          </CardBody>
        </Card>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <StatTable title="Commissions" rows={data.commissions} />
        <StatTable title="Payouts" rows={data.payouts} />
      </div>
    </div>
  )
}
