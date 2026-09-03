import { useState, type FormEvent } from 'react'
import {
  Alert,
  Badge,
  Button,
  Card,
  CardBody,
  CardHeader,
  CardTitle,
  Icon,
  Input,
  Skeleton,
} from '@/components/ui'
import { formatMoney } from '@/lib/format'
import { ApiError, type ReferralInvitationChannel } from '@/lib/api'
import { PAYOUT_FLOOR_NAIRA, RequestPayoutModal } from '@/components/referral/RequestPayoutModal'
import { ReferralStatusAlert } from '@/components/referral/ReferralStatusAlert'
import {
  usePayouts,
  useReferralActivations,
  useReferralCode,
  useReferralInvitations,
  useReferralSummary,
  useSendInvitation,
} from '@/lib/referral/queries'

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
  const summary = useReferralSummary()
  const available = summary.data?.available_minor ?? 0

  return (
    <div className="flex flex-col gap-8">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="font-display text-2xl font-bold text-foreground">Refer & earn</h1>
          <p className="mt-1 text-muted">Share Mahadum.360 and earn commission when friends subscribe.</p>
        </div>
        <div className="flex flex-col items-end gap-2">
          <div className="text-right">
            <p className="text-xs font-medium uppercase tracking-wide text-muted">Available balance</p>
            <p className="font-display text-2xl font-extrabold text-foreground">{formatMoney(available, 'NGN')}</p>
          </div>
          <Button variant="reward" leftIcon={<Icon name="wallet" className="size-[18px]" />} onClick={() => setPayoutOpen(true)}>
            Request payout
          </Button>
        </div>
      </div>

      <ReferralStatusAlert />
      <ReferralCodeCard />
      <InviteCard />
      <SummarySection />
      <ActivationsSection />
      <PayoutsSection />

      <RequestPayoutModal open={payoutOpen} onClose={() => setPayoutOpen(false)} availableMinor={available} />
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

function InviteCard() {
  const [channel, setChannel] = useState<ReferralInvitationChannel>('email')
  const [contact, setContact] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [sent, setSent] = useState<string | null>(null)
  const send = useSendInvitation()
  const { data: invitations } = useReferralInvitations()

  async function submit(e: FormEvent) {
    e.preventDefault()
    setError(null)
    setSent(null)
    try {
      await send.mutateAsync({ channel, contact: contact.trim() })
      setSent(contact.trim())
      setContact('')
    } catch (err) {
      if (err instanceof ApiError && err.code === 'account_exists') {
        setError('That account already exists and can’t be referred.')
      } else if (err instanceof ApiError) {
        setError(err.fieldErrors.contact ?? err.message)
      } else {
        setError('Couldn’t send that invite. Try again.')
      }
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Invite a friend</CardTitle>
      </CardHeader>
      <CardBody className="flex flex-col gap-4">
        <form onSubmit={submit} className="flex flex-col gap-3">
          <div className="inline-flex w-fit overflow-hidden rounded-xl border border-border">
            {(['email', 'phone'] as const).map((c) => (
              <button
                key={c}
                type="button"
                onClick={() => setChannel(c)}
                className={
                  'px-4 py-2 text-sm font-semibold capitalize transition-colors ' +
                  (channel === c ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-foreground')
                }
              >
                {c}
              </button>
            ))}
          </div>
          <div className="flex flex-wrap items-start gap-2">
            <div className="min-w-[16rem] flex-1">
              <Input
                type={channel === 'email' ? 'email' : 'tel'}
                value={contact}
                onChange={(e) => {
                  setContact(e.target.value)
                  setError(null)
                }}
                placeholder={channel === 'email' ? 'friend@example.com' : '0803 000 1111'}
                aria-label={`Friend's ${channel}`}
              />
            </div>
            <Button type="submit" variant="parent" loading={send.isPending} disabled={!contact.trim()}>
              Send invite
            </Button>
          </div>
          {error && <p className="text-sm text-danger">{error}</p>}
          {sent && <p className="text-sm text-success">Invite recorded for {sent}. It activates once they subscribe and finish a lesson and a quiz.</p>}
        </form>

        {invitations && invitations.length > 0 && (
          <div className="flex flex-col gap-1.5">
            <p className="text-xs font-medium uppercase tracking-wide text-muted">Sent invites</p>
            <div className="flex flex-wrap gap-1.5">
              {invitations.map((inv) => (
                <Badge key={inv.id} variant={inv.status === 'accepted' ? 'success' : 'neutral'}>
                  {inv.contact} · {inv.status}
                </Badge>
              ))}
            </div>
          </div>
        )}
      </CardBody>
    </Card>
  )
}

function ActivationsSection() {
  const [search, setSearch] = useState('')
  const { data, isLoading, isError } = useReferralActivations(search)
  const rows = data?.data ?? []

  return (
    <section className="flex flex-col gap-3">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h2 className="font-display text-lg font-bold text-foreground">Activations</h2>
        <div className="min-w-[14rem]">
          <Input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search by email or phone"
            leftIcon={<Icon name="search" />}
            aria-label="Search activations"
          />
        </div>
      </div>

      {isError ? (
        <Alert variant="danger">Couldn’t load your activations.</Alert>
      ) : isLoading ? (
        <Skeleton className="h-32" />
      ) : rows.length === 0 ? (
        <Card>
          <CardBody className="py-8 text-center text-sm text-muted">
            {search ? 'No activations match that search.' : 'No one has activated your code yet.'}
          </CardBody>
        </Card>
      ) : (
        <Card>
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead>
                <tr className="border-b border-border text-xs uppercase tracking-wide text-muted">
                  <th className="px-4 py-2.5 font-semibold">SN</th>
                  <th className="px-4 py-2.5 font-semibold">Activated</th>
                  <th className="px-4 py-2.5 font-semibold">Code</th>
                  <th className="px-4 py-2.5 font-semibold">Via email</th>
                  <th className="px-4 py-2.5 font-semibold">Via phone</th>
                  <th className="px-4 py-2.5 font-semibold">Status</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((r) => (
                  <tr key={r.sn} className="border-b border-border last:border-0">
                    <td className="px-4 py-3 tabular-nums text-muted">{r.sn}</td>
                    <td className="px-4 py-3 text-foreground">
                      {r.activated_at ? new Date(r.activated_at).toLocaleDateString() : '—'}
                    </td>
                    <td className="px-4 py-3 font-mono text-foreground">{r.code}</td>
                    <td className="px-4 py-3 text-foreground">{r.via_email ?? '—'}</td>
                    <td className="px-4 py-3 text-foreground">{r.via_phone ?? '—'}</td>
                    <td className="px-4 py-3">
                      <Badge variant={r.status === 'active' ? 'success' : 'neutral'}>
                        {r.status === 'active' ? 'Active' : 'Inactive'}
                      </Badge>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Card>
      )}
    </section>
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
