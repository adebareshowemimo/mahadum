import { useState } from 'react'
import { cn } from '@/lib/cn'
import { Badge, Button, Card, CardBody } from '@/components/ui'

/* ---------------------------------------------- telco grace / daily billing banner */

/** Framed as streak protection, not punishment (amber/gold, never harsh red). */
export function TelcoGraceBanner({ hoursLeft = 48, onRecharge }: { hoursLeft?: number; onRecharge?: () => void }) {
  return (
    <div className="flex items-center gap-3 rounded-2xl border border-gold-300 bg-gold-50 p-4 dark:border-gold-700 dark:bg-gold-900/25">
      <span className="grid size-10 shrink-0 place-items-center rounded-full bg-gold-100 text-xl dark:bg-gold-900/50">🛡️</span>
      <div className="flex-1">
        <p className="font-display font-semibold text-gold-800 dark:text-gold-100">Your streak is protected for {hoursLeft} hours</p>
        <p className="text-sm text-gold-700/90 dark:text-gold-200/90">Recharge airtime soon to keep learning without interruption.</p>
      </div>
      <Button variant="billing" size="sm" onClick={onRecharge}>Recharge</Button>
    </div>
  )
}

/* ----------------------------------------------------------- subscription badge */

export type SubState = 'active' | 'grace' | 'pending' | 'cancelled' | 'free'

export function SubscriptionStatusBadge({ state }: { state: SubState }) {
  const map: Record<SubState, { variant: 'success' | 'gold' | 'info' | 'neutral'; label: string }> = {
    active: { variant: 'success', label: 'Plus · active' },
    grace: { variant: 'gold', label: 'Grace period' },
    pending: { variant: 'info', label: 'Payment pending' },
    cancelled: { variant: 'neutral', label: 'Cancelled' },
    free: { variant: 'neutral', label: 'Free tier' },
  }
  const m = map[state]
  return <Badge variant={m.variant} dot>{m.label}</Badge>
}

/* --------------------------------------------------------------- recharge CTA */

export function AirtimeRechargeCTA({ msisdn, dailyMinor }: { msisdn: string; dailyMinor: number }) {
  return (
    <Card className="w-80">
      <CardBody className="flex flex-col gap-3">
        <div className="flex items-center justify-between">
          <span className="font-display font-semibold text-foreground">Airtime billing</span>
          <SubscriptionStatusBadge state="grace" />
        </div>
        <p className="text-sm text-muted">
          {msisdn} · ₦{(dailyMinor / 100).toFixed(2)}/day via your network.
        </p>
        <Button variant="billing" fullWidth leftIcon={<span>📲</span>}>Recharge airtime</Button>
      </CardBody>
    </Card>
  )
}

/* ----------------------------------------------------------- referral code card */

export function ReferralCodeCard({ code, referrals, pendingMinor }: { code: string; referrals: number; pendingMinor: number }) {
  const [copied, setCopied] = useState(false)
  const copy = () => {
    navigator.clipboard?.writeText(code)
    setCopied(true)
    setTimeout(() => setCopied(false), 1500)
  }
  return (
    <Card className="w-80">
      <CardBody className="flex flex-col gap-3">
        <div className="flex items-center justify-between">
          <span className="font-display font-semibold text-foreground">Invite & earn</span>
          <CommissionBadge state="pending" amountMinor={pendingMinor} />
        </div>
        <button onClick={copy} className="flex items-center justify-between rounded-xl border border-dashed border-border-strong bg-surface-muted px-4 py-3 text-left">
          <span className="font-mono text-lg font-bold tracking-wider text-foreground">{code}</span>
          <span className={cn('text-sm font-semibold', copied ? 'text-leaf-600' : 'text-primary')}>{copied ? 'Copied ✓' : 'Copy'}</span>
        </button>
        <div className="flex gap-2">
          <Button variant="primary" size="sm" leftIcon={<span>📱</span>}>Share on WhatsApp</Button>
          <span className="self-center text-sm text-muted">{referrals} joined</span>
        </div>
      </CardBody>
    </Card>
  )
}

export function CommissionBadge({ state, amountMinor }: { state: 'pending' | 'cleared'; amountMinor: number }) {
  const amt = '₦' + (amountMinor / 100).toLocaleString()
  return state === 'cleared'
    ? <Badge variant="success">{amt} cleared</Badge>
    : <Badge variant="gold">{amt} pending</Badge>
}

/* --------------------------------------------------------- ad-supported tier note */

export function RemoveAdsCTA() {
  return (
    <div className="flex items-center gap-3 rounded-2xl bg-navy-900 p-4 text-ivory-100">
      <span className="text-2xl" aria-hidden>✨</span>
      <div className="flex-1">
        <p className="font-display font-semibold">Learning, ad-free</p>
        <p className="text-sm text-navy-200">Upgrade to Mahadum Plus — no ads, offline lessons.</p>
      </div>
      <Button variant="premium" size="sm" className="bg-gold-400 text-charcoal-900 hover:bg-gold-300">Upgrade</Button>
    </div>
  )
}
