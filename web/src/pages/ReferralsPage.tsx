import { useState } from 'react'
import {
  Alert,
  Badge,
  Button,
  Card,
  CardBody,
  CardHeader,
  CardTitle,
  Icon,
  Skeleton,
} from '@/components/ui'
import { formatMoney } from '@/lib/format'
import { PAYOUT_FLOOR_NAIRA, RequestPayoutModal } from '@/components/referral/RequestPayoutModal'
import { ReferralStatusAlert } from '@/components/referral/ReferralStatusAlert'
import { usePayouts, useReferralCode, useReferralSummary } from '@/lib/referral/queries'

function humanize(status: string): string {
  return status.replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase())
}

const STATUS_TONE: Record<string, 'success' | 'gold' | 'danger' | 'neutral'> = {
  cleared: 'success',
  paid: 'success',
  approved: 'success',
  escrow: 'gold',
  escrowed: 'gold',
  pending: 'gold',
  requested: 'gold',
  clawed_back: 'danger',
  rejected: 'danger',
}

export function ReferralsPage() {
  const [payoutOpen, setPayoutOpen] = useState(false)

  return (
    <div className="flex flex-col gap-8">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="font-display text-2xl font-bold text-foreground">Refer & earn</h1>
          <p className="mt-1 text-muted">Share Mahadum.360 and earn commission when friends subscribe.</p>
        </div>
        <Button variant="reward" leftIcon={<Icon name="wallet" className="size-[18px]" />} onClick={() => setPayoutOpen(true)}>
          Request payout
        </Button>
      </div>

      <ReferralStatusAlert />
      <ReferralCodeCard />
      <SummarySection />
      <PayoutsSection />

      <RequestPayoutModal open={payoutOpen} onClose={() => setPayoutOpen(false)} />
    </div>
  )
}

function ReferralCodeCard() {
  const { data, isLoading, isError } = useReferralCode()
  const [copied, setCopied] = useState(false)

  if (isLoading) return <Skeleton className="h-40" />
  if (isError || !data) return <Alert variant="danger">Couldn’t load your referral code.</Alert>

  async function copy() {
    try {
      await navigator.clipboard.writeText(data!.share_url)
      setCopied(true)
      setTimeout(() => setCopied(false), 1800)
    } catch {
      /* clipboard blocked — ignore */
    }
  }

  const message = encodeURIComponent(`${data.share_text} ${data.share_url}`)

  return (
    <Card className="overflow-hidden">
      <CardBody className="flex flex-col gap-4 bg-primary-soft">
        <div>
          <p className="text-sm font-medium text-primary">Your referral code</p>
          <p className="font-display text-3xl font-extrabold tracking-wide text-foreground">{data.code}</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button size="sm" variant="secondary" onClick={copy}>
            {copied ? 'Copied ✓' : 'Copy link'}
          </Button>
          <a href={`https://wa.me/?text=${message}`} target="_blank" rel="noreferrer">
            <Button size="sm" variant="outline">
              Share on WhatsApp
            </Button>
          </a>
          <a href={`sms:?&body=${message}`}>
            <Button size="sm" variant="outline">
              Share via SMS
            </Button>
          </a>
        </div>
      </CardBody>
    </Card>
  )
}

function SummarySection() {
  const { data, isLoading, isError } = useReferralSummary()

  if (isLoading) return <Skeleton className="h-32" />
  if (isError || !data) return <Alert variant="danger">Couldn’t load your referral summary.</Alert>

  const referralEntries = Object.entries(data.referrals ?? {})
  const totalReferrals = referralEntries.reduce((sum, [, c]) => sum + c, 0)
  const commissionEntries = Object.values(data.commissions ?? {})

  return (
    <div className="grid gap-4 lg:grid-cols-2">
      <Card>
        <CardHeader>
          <CardTitle>Referrals</CardTitle>
        </CardHeader>
        <CardBody className="flex flex-col gap-3">
          <p className="font-display text-3xl font-extrabold text-foreground">{totalReferrals}</p>
          {referralEntries.length === 0 ? (
            <p className="text-sm text-muted">No referrals yet — share your code to get started.</p>
          ) : (
            <div className="flex flex-wrap gap-1.5">
              {referralEntries.map(([status, count]) => (
                <Badge key={status} variant={STATUS_TONE[status] ?? 'neutral'}>
                  {count} {humanize(status).toLowerCase()}
                </Badge>
              ))}
            </div>
          )}
        </CardBody>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Commissions</CardTitle>
        </CardHeader>
        <CardBody className="flex flex-col gap-2">
          {commissionEntries.length === 0 ? (
            <p className="text-sm text-muted">No commissions yet.</p>
          ) : (
            commissionEntries.map((c) => (
              <div key={c.status} className="flex items-center justify-between">
                <span className="flex items-center gap-2 text-sm">
                  <Badge variant={STATUS_TONE[c.status] ?? 'neutral'}>{humanize(c.status)}</Badge>
                  <span className="text-muted">×{c.c}</span>
                </span>
                <span className="font-semibold text-foreground">{formatMoney(c.total, 'NGN')}</span>
              </div>
            ))
          )}
        </CardBody>
      </Card>
    </div>
  )
}

function PayoutsSection() {
  const { data, isLoading, isError } = usePayouts()

  return (
    <section className="flex flex-col gap-3">
      <h2 className="font-display text-lg font-bold text-foreground">Payouts</h2>
      {isLoading ? (
        <Skeleton className="h-24" />
      ) : isError ? (
        <Alert variant="danger">Couldn’t load payouts.</Alert>
      ) : (data?.length ?? 0) === 0 ? (
        <Card>
          <CardBody className="py-8 text-center text-sm text-muted">
            No payouts yet. Cleared commissions of ₦{PAYOUT_FLOOR_NAIRA.toLocaleString()}+ can be withdrawn.
          </CardBody>
        </Card>
      ) : (
        <div className="flex flex-col gap-2">
          {data?.map((p) => (
            <Card key={p.id}>
              <CardBody className="flex items-center justify-between gap-3 py-3">
                <div>
                  <p className="font-semibold text-foreground">{formatMoney(p.amount_minor, 'NGN')}</p>
                  <p className="text-xs capitalize text-muted">
                    {p.method} · {p.requested_at ? new Date(p.requested_at).toLocaleDateString() : '—'}
                  </p>
                </div>
                <Badge variant={STATUS_TONE[p.status] ?? 'neutral'}>{humanize(p.status)}</Badge>
              </CardBody>
            </Card>
          ))}
        </div>
      )}
    </section>
  )
}

