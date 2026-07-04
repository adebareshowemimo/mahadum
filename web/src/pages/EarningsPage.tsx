import { useState } from 'react'
import { Alert, Badge, Button, Card, CardBody, CardHeader, CardTitle, Skeleton } from '@/components/ui'
import { formatMoney } from '@/lib/format'
import { RequestPayoutModal } from '@/components/referral/RequestPayoutModal'
import { ReferralStatusAlert } from '@/components/referral/ReferralStatusAlert'
import { usePayouts, useReferralSummary } from '@/lib/referral/queries'

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

export function EarningsPage() {
  const summary = useReferralSummary()
  const payouts = usePayouts()
  const [payoutOpen, setPayoutOpen] = useState(false)

  if (summary.isLoading) return <Skeleton className="h-40" />
  if (summary.isError || !summary.data) return <Alert variant="danger">Couldn’t load your earnings.</Alert>

  const commissions = Object.values(summary.data.commissions ?? {})
  const cleared = commissions.find((c) => c.status === 'cleared')?.total ?? 0
  const pending = commissions
    .filter((c) => c.status === 'escrow' || c.status === 'escrowed' || c.status === 'pending')
    .reduce((sum, c) => sum + c.total, 0)

  return (
    <div className="flex flex-col gap-8">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="font-display text-2xl font-bold text-foreground">Earnings</h1>
          <p className="mt-1 text-muted">Commission from students who join with your code.</p>
        </div>
        <Button variant="reward" onClick={() => setPayoutOpen(true)}>
          Request payout
        </Button>
      </div>

      <ReferralStatusAlert />

      <div className="grid gap-4 sm:grid-cols-2">
        <Card className="overflow-hidden">
          <CardBody className="bg-leaf-50">
            <p className="text-sm font-medium text-leaf-700">Cleared (available)</p>
            <p className="font-display text-3xl font-extrabold text-foreground">{formatMoney(cleared, 'NGN')}</p>
          </CardBody>
        </Card>
        <Card>
          <CardBody>
            <p className="text-sm text-muted">Pending (in escrow)</p>
            <p className="font-display text-3xl font-extrabold text-foreground">{formatMoney(pending, 'NGN')}</p>
          </CardBody>
        </Card>
      </div>

      <section className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Commission breakdown</CardTitle>
          </CardHeader>
          <CardBody className="flex flex-col gap-2">
            {commissions.length === 0 ? (
              <p className="text-sm text-muted">No commissions yet — share your referral code to start earning.</p>
            ) : (
              commissions.map((c) => (
                <div key={c.status} className="flex items-center justify-between">
                  <span className="flex items-center gap-2 text-sm">
                    <Badge variant={TONE[c.status] ?? 'neutral'}>{humanize(c.status)}</Badge>
                    <span className="text-muted">×{c.c}</span>
                  </span>
                  <span className="font-semibold text-foreground">{formatMoney(c.total, 'NGN')}</span>
                </div>
              ))
            )}
          </CardBody>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Payouts</CardTitle>
          </CardHeader>
          <CardBody className="flex flex-col gap-2">
            {payouts.isLoading ? (
              <Skeleton className="h-16" />
            ) : (payouts.data?.length ?? 0) === 0 ? (
              <p className="text-sm text-muted">No payouts yet.</p>
            ) : (
              payouts.data?.map((p) => (
                <div key={p.id} className="flex items-center justify-between">
                  <span className="text-sm">
                    <span className="font-semibold text-foreground">{formatMoney(p.amount_minor, 'NGN')}</span>{' '}
                    <span className="capitalize text-muted">· {p.method}</span>
                  </span>
                  <Badge variant={TONE[p.status] ?? 'neutral'}>{humanize(p.status)}</Badge>
                </div>
              ))
            )}
          </CardBody>
        </Card>
      </section>

      <RequestPayoutModal open={payoutOpen} onClose={() => setPayoutOpen(false)} />
    </div>
  )
}
