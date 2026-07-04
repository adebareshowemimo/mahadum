import { useState } from 'react'
import { cn } from '@/lib/cn'
import { Avatar, Badge, Button, Card, CardBody } from '@/components/ui'

const naira = (minor: number) => '₦' + (minor / 100).toLocaleString(undefined, { minimumFractionDigits: 2 })

/* ----------------------------------------------------- parent wallet balance */

export function WalletBalanceCard({ currencyMinor, coins, onFund }: { currencyMinor: number; coins: number; onFund?: () => void }) {
  return (
    <Card className="w-80 overflow-hidden border-0 bg-navy-900 text-ivory-100 shadow-lg">
      <CardBody className="flex flex-col gap-4">
        <div className="flex items-center justify-between">
          <span className="text-sm font-semibold text-navy-200">Family wallet</span>
          <Badge variant="premium">Mahadum Plus</Badge>
        </div>
        <div>
          <p className="font-display text-3xl font-bold">{naira(currencyMinor)}</p>
          <p className="mt-1 inline-flex items-center gap-1.5 text-sm text-navy-200">
            <span className="grid size-4 place-items-center rounded-full bg-gold-400 text-[10px] font-bold text-charcoal-900">₵</span>
            {coins.toLocaleString()} coins available
          </p>
        </div>
        <div className="flex gap-2">
          <Button variant="reward" size="sm" onClick={onFund}>Top up</Button>
          <Button variant="outline" size="sm" className="border-navy-700 bg-navy-800 text-ivory-100 hover:bg-navy-700">Transfer</Button>
        </div>
      </CardBody>
    </Card>
  )
}

/* ------------------------------------------------------------------ chore card */

export type ChoreStatus = 'active' | 'pending_review' | 'approved' | 'rejected'

const CHORE_BADGE: Record<ChoreStatus, { variant: 'neutral' | 'info' | 'success' | 'danger'; label: string }> = {
  active: { variant: 'neutral', label: 'In progress' },
  pending_review: { variant: 'info', label: 'Needs review' },
  approved: { variant: 'success', label: 'Approved' },
  rejected: { variant: 'danger', label: 'Rejected' },
}

export function ChoreCard({ title, assignee, reward, status, onApprove }: { title: string; assignee: string; reward: number; status: ChoreStatus; onApprove?: () => void }) {
  const meta = CHORE_BADGE[status]
  const pending = status === 'pending_review'
  return (
    <Card className={cn('w-80', pending && 'border-chore-300 ring-1 ring-chore-200 dark:border-chore-700')}>
      <CardBody className="flex flex-col gap-3">
        <div className="flex items-start justify-between gap-2">
          <div>
            <h4 className="font-display font-semibold text-foreground">{title}</h4>
            <p className="text-sm text-muted">{assignee}</p>
          </div>
          <Badge variant={meta.variant} dot>{meta.label}</Badge>
        </div>
        <div className="flex items-center justify-between">
          <span className="inline-flex items-center gap-1.5 text-sm font-bold text-gold-700 dark:text-gold-300">
            <span className="grid size-4 place-items-center rounded-full bg-gold-400 text-[10px] text-charcoal-900">₵</span>
            {reward} coins
          </span>
          {pending && <Button variant="parent" size="sm" onClick={onApprove}>Approve reward</Button>}
        </div>
      </CardBody>
    </Card>
  )
}

/* ------------------------------------------------------- family leaderboard row */

export function FamilyLeaderboardRow({ rank, name, xp, you = false }: { rank: number; name: string; xp: number; you?: boolean }) {
  const medal = rank === 1 ? '🥇' : rank === 2 ? '🥈' : rank === 3 ? '🥉' : null
  return (
    <div className={cn('flex items-center gap-3 rounded-xl px-3 py-2', you ? 'bg-primary-soft' : 'bg-surface')}>
      <span className="w-6 text-center font-display text-sm font-bold text-muted">{medal ?? rank}</span>
      <Avatar name={name} size="sm" />
      <span className={cn('flex-1 truncate text-sm font-semibold', you ? 'text-primary' : 'text-foreground')}>{name}{you && ' (you)'}</span>
      <span className="text-sm font-bold tabular-nums text-muted">{xp.toLocaleString()} XP</span>
    </div>
  )
}

/* ------------------------------------------------------ child profile switcher */

export function ChildProfileSwitcher({ profiles }: { profiles: { name: string; level: number }[] }) {
  const [active, setActive] = useState(0)
  return (
    <div className="flex flex-wrap gap-2">
      {profiles.map((p, i) => (
        <button
          key={p.name}
          onClick={() => setActive(i)}
          className={cn(
            'flex items-center gap-2.5 rounded-2xl border-2 p-2 pr-4 transition-colors',
            i === active ? 'border-primary bg-primary-soft' : 'border-border bg-surface hover:bg-surface-muted',
          )}
        >
          <Avatar name={p.name} />
          <div className="text-left">
            <p className="text-sm font-semibold text-foreground">{p.name}</p>
            <p className="text-xs text-muted">Level {p.level}</p>
          </div>
        </button>
      ))}
    </div>
  )
}
