import { useState, type FormEvent } from 'react'
import { Alert, Badge, Button, Card, CardBody, CardHeader, CardTitle, Icon, Input, Modal, Skeleton } from '@/components/ui'
import { ApiError } from '@/lib/api'
import { formatMoney } from '@/lib/format'
import { SchoolGate } from '@/components/school/SchoolGate'
import { useRequestSchoolReferralPayout, useSchoolReferrals } from '@/lib/school/queries'

export const SCHOOL_PAYOUT_FLOOR_NAIRA = 5000

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
  pending_escrow: 'gold',
  requested: 'gold',
  qualified: 'success',
  clawback_pending: 'danger',
  clawed_back: 'danger',
  rejected: 'danger',
  flagged: 'danger',
  frozen: 'danger',
}

export function SchoolReferralsPage() {
  return <SchoolGate>{(orgId) => <SchoolReferrals orgId={orgId} />}</SchoolGate>
}

function SchoolReferrals({ orgId }: { orgId: number }) {
  const { data, isLoading, isError } = useSchoolReferrals(orgId)
  const [copied, setCopied] = useState(false)
  const [payoutOpen, setPayoutOpen] = useState(false)

  if (isLoading) return <Skeleton className="h-40" />
  if (isError || !data) return <Alert variant="danger">Couldn’t load your school’s referral code.</Alert>

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
  const referralEntries = Object.entries(data.referrals ?? {})
  const totalReferrals = referralEntries.reduce((sum, [, count]) => sum + count, 0)
  const commissionEntries = Object.values(data.commissions ?? {})

  return (
    <div className="flex flex-col gap-8">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="font-display text-2xl font-bold text-foreground">School referrals</h1>
          <p className="mt-1 text-muted">Your school’s own code — commission accrues to the school, not an individual.</p>
        </div>
        <Button variant="reward" leftIcon={<Icon name="wallet" className="size-[18px]" />} onClick={() => setPayoutOpen(true)}>
          Request payout
        </Button>
      </div>

      {(data.status === 'flagged' || data.status === 'frozen') && (
        <Alert variant="warning" title="Referral code under review">
          Some recent activity was flagged, so new referral rewards are paused while we take a look.
        </Alert>
      )}

      <Card className="overflow-hidden">
        <CardBody className="flex flex-col gap-4 bg-primary-soft">
          <div>
            <p className="text-sm font-medium text-primary">Your school’s referral code</p>
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

      <section className="flex flex-col gap-3">
        <h2 className="font-display text-lg font-bold text-foreground">Payouts</h2>
        {data.payouts.length === 0 ? (
          <Card>
            <CardBody className="py-8 text-center text-sm text-muted">
              No payouts yet. Cleared commissions of ₦{SCHOOL_PAYOUT_FLOOR_NAIRA.toLocaleString()}+ can be withdrawn.
            </CardBody>
          </Card>
        ) : (
          <div className="flex flex-col gap-2">
            {data.payouts.map((p) => (
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

      <RequestSchoolPayoutModal orgId={orgId} open={payoutOpen} onClose={() => setPayoutOpen(false)} />
    </div>
  )
}

function RequestSchoolPayoutModal({ orgId, open, onClose }: { orgId: number; open: boolean; onClose: () => void }) {
  const requestPayout = useRequestSchoolReferralPayout(orgId)
  const [amount, setAmount] = useState('')
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})
  const [formError, setFormError] = useState<string | null>(null)

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setFieldErrors({})
    setFormError(null)
    const naira = Number(amount)
    if (naira < SCHOOL_PAYOUT_FLOOR_NAIRA) {
      setFieldErrors({ amount_minor: `Minimum payout is ₦${SCHOOL_PAYOUT_FLOOR_NAIRA.toLocaleString()}.` })
      return
    }
    try {
      await requestPayout.mutateAsync({ amount_minor: Math.round(naira * 100), method: 'bank' })
      setAmount('')
      onClose()
    } catch (err) {
      if (err instanceof ApiError) {
        setFieldErrors(err.fieldErrors)
        if (!Object.keys(err.fieldErrors).length) setFormError(err.message)
      } else {
        setFormError('Something went wrong. Please try again.')
      }
    }
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Request a payout"
      description={`Minimum ₦${SCHOOL_PAYOUT_FLOOR_NAIRA.toLocaleString()}. Paid to the school's bank account.`}
    >
      <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
        {formError && <Alert variant="danger">{formError}</Alert>}
        <Input
          label="Amount (NGN)"
          type="number"
          min={SCHOOL_PAYOUT_FLOOR_NAIRA}
          step="0.01"
          value={amount}
          onChange={(e) => setAmount(e.target.value)}
          error={fieldErrors.amount_minor}
          autoFocus
          required
        />
        <div className="flex gap-2">
          <Button type="button" variant="secondary" fullWidth onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" variant="reward" fullWidth loading={requestPayout.isPending}>
            Request payout
          </Button>
        </div>
      </form>
    </Modal>
  )
}
