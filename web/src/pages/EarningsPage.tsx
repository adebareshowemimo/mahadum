import { useState } from 'react'
import { Alert, Badge, Button, Card, CardBody, CardHeader, CardTitle, Skeleton } from '@/components/ui'
import { formatMoney } from '@/lib/format'
import { RequestPayoutModal } from '@/components/referral/RequestPayoutModal'
import { ReferralStatusAlert } from '@/components/referral/ReferralStatusAlert'
import { RequestTeachingPayoutModal } from '@/components/school/RequestTeachingPayoutModal'
import { usePayouts, useReferralSummary } from '@/lib/referral/queries'
import { useTeacherCompensation } from '@/lib/school/queries'

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
  return (
    <div className="flex flex-col gap-10">
      <div>
        <h1 className="font-display text-2xl font-bold text-foreground">Earnings</h1>
        <p className="mt-1 text-muted">Referral commission and teaching compensation, paid out separately.</p>
      </div>

      <ReferralEarningsSection />
      <TeachingCompensationSection />
    </div>
  )
}

function ReferralEarningsSection() {
  const summary = useReferralSummary()
  const payouts = usePayouts()
  const [payoutOpen, setPayoutOpen] = useState(false)

  if (summary.isLoading) return <Skeleton className="h-40" />
  if (summary.isError || !summary.data) return <Alert variant="danger">Couldn’t load your referral earnings.</Alert>

  const commissions = Object.values(summary.data.commissions ?? {})
  const cleared = commissions.find((c) => c.status === 'cleared')?.total ?? 0
  const pending = commissions
    .filter((c) => c.status === 'escrow' || c.status === 'escrowed' || c.status === 'pending')
    .reduce((sum, c) => sum + c.total, 0)
  const referralPayouts = (payouts.data ?? []).filter((p) => p.source !== 'teaching')

  return (
    <section className="flex flex-col gap-4">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h2 className="font-display text-lg font-bold text-foreground">Referral earnings</h2>
          <p className="text-sm text-muted">Commission from students who join with your code.</p>
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

      <div className="grid gap-4 lg:grid-cols-2">
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
            ) : referralPayouts.length === 0 ? (
              <p className="text-sm text-muted">No payouts yet.</p>
            ) : (
              referralPayouts.map((p) => (
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
      </div>

      <RequestPayoutModal open={payoutOpen} onClose={() => setPayoutOpen(false)} />
    </section>
  )
}

function TeachingCompensationSection() {
  const compensation = useTeacherCompensation()
  const payouts = usePayouts()
  const [payoutOpen, setPayoutOpen] = useState(false)

  if (compensation.isLoading) return <Skeleton className="h-40" />
  if (compensation.isError || !compensation.data) {
    return <Alert variant="danger">Couldn’t load your teaching compensation.</Alert>
  }

  const teachingPayouts = (payouts.data ?? []).filter((p) => p.source === 'teaching')

  return (
    <section className="flex flex-col gap-4">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h2 className="font-display text-lg font-bold text-foreground">Teaching compensation</h2>
          <p className="text-sm text-muted">Accrued monthly per currently-enrolled, paying student in your classes.</p>
        </div>
        <Button
          variant="parent"
          disabled={compensation.data.available_minor === 0}
          onClick={() => setPayoutOpen(true)}
        >
          Request payout
        </Button>
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <Card className="overflow-hidden">
          <CardBody className="bg-chore-50">
            <p className="text-sm font-medium text-chore-700">Available</p>
            <p className="font-display text-3xl font-extrabold text-foreground">
              {formatMoney(compensation.data.available_minor, 'NGN')}
            </p>
          </CardBody>
        </Card>
        <Card>
          <CardBody>
            <p className="text-sm text-muted">Accrued to date</p>
            <p className="font-display text-3xl font-extrabold text-foreground">
              {formatMoney(compensation.data.accrued_total_minor, 'NGN')}
            </p>
          </CardBody>
        </Card>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Monthly accrual</CardTitle>
          </CardHeader>
          <CardBody className="flex flex-col gap-2">
            {compensation.data.months.length === 0 ? (
              <p className="text-sm text-muted">Nothing accrued yet — this fills in once you have paying, enrolled students.</p>
            ) : (
              compensation.data.months.map((m) => (
                <div key={m.period} className="flex items-center justify-between text-sm">
                  <span className="text-muted">
                    {m.period} <span className="text-subtle">· {m.paying_student_count} students</span>
                  </span>
                  <span className="font-semibold text-foreground">{formatMoney(m.amount_minor, 'NGN')}</span>
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
            ) : teachingPayouts.length === 0 ? (
              <p className="text-sm text-muted">No payouts yet.</p>
            ) : (
              teachingPayouts.map((p) => (
                <div key={p.id} className="flex items-center justify-between">
                  <span className="text-sm">
                    <span className="font-semibold text-foreground">{formatMoney(p.amount_minor, 'NGN')}</span>{' '}
                    <span className="text-muted">· bank</span>
                  </span>
                  <Badge variant={TONE[p.status] ?? 'neutral'}>{humanize(p.status)}</Badge>
                </div>
              ))
            )}
          </CardBody>
        </Card>
      </div>

      <RequestTeachingPayoutModal
        open={payoutOpen}
        onClose={() => setPayoutOpen(false)}
        availableMinor={compensation.data.available_minor}
      />
    </section>
  )
}
