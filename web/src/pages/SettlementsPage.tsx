import { useState, type FormEvent } from 'react'
import { Alert, Badge, Button, Card, CardBody, CardHeader, CardTitle, Input, Skeleton } from '@/components/ui'
import { ApiError, type CommissionStat } from '@/lib/api'
import { formatMoney } from '@/lib/format'
import { useSettlements, useSettings, useUpdateSettings } from '@/lib/admin/queries'
import { DataTable } from '@/components/admin/DataTable'

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
      <ReferralCommissionSetting />

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

      <Card><CardBody className="flex flex-col gap-2"><h2 className="font-semibold text-foreground">Telco revenue share</h2>
        <p className="text-sm text-muted">Not configured. An approved operator/platform split is required before a share can be calculated.</p>
        <p className="text-xs text-muted">The telco revenue above is gross successful billing; it is not a confirmed provider settlement.</p>
      </CardBody></Card>
      <section className="flex flex-col gap-3" aria-labelledby="daily-billing-title">
        <h2 id="daily-billing-title" className="font-display text-lg font-bold text-foreground">Daily telco billing success</h2>
        {!data.daily_telco ? <p className="text-sm text-muted">Daily billing figures are unavailable.</p> : <>
          <p className="text-sm text-muted">Last seven calendar days · {data.daily_telco.timezone}. All recorded attempts are included; days without attempts have no success rate.</p>
          <DataTable rows={data.daily_telco.days} getRowId={row => row.date} columns={[
            { key: 'date', header: 'Date', render: row => row.date, className: 'whitespace-nowrap' },
            { key: 'attempts', header: 'Attempts', render: row => row.attempts },
            { key: 'success', header: 'Succeeded', render: row => row.success },
            { key: 'rate', header: 'Success rate', render: row => row.success_rate == null ? '—' : `${row.success_rate}%` },
          ]} />
        </>}
      </section>

      <div className="grid gap-4 lg:grid-cols-2">
        <StatTable title="Commissions" rows={data.commissions} />
        <StatTable title="Payouts" rows={data.payouts} />
      </div>
    </div>
  )
}

function ReferralCommissionSetting() {
  const settings = useSettings()
  const update = useUpdateSettings()
  const [draft, setDraft] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const current = settings.data?.groups.flatMap(group => group.settings).find(item => item.key === 'referral.commission_bps')
  const value = draft ?? (current ? String(Number(current.value) / 100) : '')
  async function save(event: FormEvent) {
    event.preventDefault()
    setError(null); setMessage(null)
    if (!/^\d{1,3}(\.\d{1,2})?$/.test(value) || Number(value) > 100) {
      setError('Enter a percentage from 0 to 100 with up to two decimal places.'); return
    }
    try {
      await update.mutateAsync({ 'referral.commission_bps': Math.round(Number(value) * 100) })
      setDraft(null); setMessage('Referral commission updated.')
    } catch (err) { setError(err instanceof ApiError ? err.fieldErrors['referral.commission_bps'] ?? err.message : 'Could not save the referral commission.') }
  }
  return <Card><CardHeader><CardTitle>Referral payout percentage</CardTitle></CardHeader><CardBody className="flex flex-col gap-3">
    <p className="text-sm text-muted">Commission earned on qualifying purchases. This changes the existing referral setting; it does not change escrow, payout limits or previously recorded commissions.</p>
    {settings.isLoading ? <Skeleton className="h-16" /> : settings.isError || !current ? <Alert variant="warning">The referral setting is unavailable. Try again before making changes.</Alert> : <form onSubmit={save} className="flex flex-wrap items-end gap-3" noValidate>
      <Input label="Referral commission (%)" type="number" min={0} max={100} step="0.01" value={value} onChange={event => { setDraft(event.target.value); setMessage(null) }} error={error ?? undefined} />
      <Button type="submit" loading={update.isPending} disabled={draft === null}>Save percentage</Button>
    </form>}
    {message && <p role="status" className="text-sm text-primary">{message}</p>}
  </CardBody></Card>
}
