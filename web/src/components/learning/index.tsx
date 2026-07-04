import { cn } from '@/lib/cn'
import { Badge, Button3D, Card, CardBody, Progress } from '@/components/ui'

/* ----------------------------------------------------------------- counters */

export function HeartsCounter({ current, max = 5 }: { current: number; max?: number }) {
  return (
    <div className="inline-flex items-center gap-1.5 rounded-full bg-surface px-3 py-1.5 shadow-sm" aria-label={`${current} of ${max} hearts`}>
      <span className="flex gap-0.5">
        {Array.from({ length: max }).map((_, i) => (
          <Heart key={i} filled={i < current} />
        ))}
      </span>
      <span className="text-sm font-bold tabular-nums text-foreground">{current}</span>
    </div>
  )
}

function Heart({ filled }: { filled: boolean }) {
  return (
    <svg viewBox="0 0 24 24" className={cn('size-4', filled ? 'text-red-500' : 'text-charcoal-200')} fill="currentColor" aria-hidden>
      <path d="M12 21s-7.5-4.6-10-9.2C.3 8.4 2 5 5.2 5c2 0 3.3 1.1 4.8 2.8C11.5 6.1 12.8 5 14.8 5 18 5 19.7 8.4 22 11.8 19.5 16.4 12 21 12 21z" />
    </svg>
  )
}

export function XpCounter({ value }: { value: number }) {
  return (
    <div className="inline-flex items-center gap-1.5 rounded-full bg-heritage-100 px-3 py-1.5 dark:bg-heritage-900/40">
      <span aria-hidden>⚡</span>
      <span className="text-sm font-bold tabular-nums text-heritage-700 dark:text-heritage-200">{value.toLocaleString()} XP</span>
    </div>
  )
}

export function CoinPill({ amount }: { amount: number }) {
  return (
    <div className="inline-flex items-center gap-1.5 rounded-full bg-gold-100 px-3 py-1.5 dark:bg-gold-900/40">
      <span className="grid size-4 place-items-center rounded-full bg-gold-400 text-[10px] font-bold text-charcoal-900">₵</span>
      <span className="text-sm font-bold tabular-nums text-gold-800 dark:text-gold-200">{amount.toLocaleString()}</span>
    </div>
  )
}

/* --------------------------------------------------------- speaking gauge (AI) */

export function SpeakingScoreGauge({ score, label = 'Speaking' }: { score: number; label?: string }) {
  const r = 26
  const c = 2 * Math.PI * r
  const tone = score >= 80 ? 'text-heritage-500' : score >= 55 ? 'text-gold-500' : 'text-clay-500'
  return (
    <div className="inline-flex flex-col items-center gap-1">
      <div className="relative size-20">
        <svg viewBox="0 0 64 64" className="size-20 -rotate-90">
          <circle cx="32" cy="32" r={r} fill="none" strokeWidth="6" className="stroke-surface-sunken" />
          <circle
            cx="32" cy="32" r={r} fill="none" strokeWidth="6" strokeLinecap="round"
            className={cn('transition-[stroke-dashoffset] duration-700', tone)}
            stroke="currentColor" strokeDasharray={c} strokeDashoffset={c - (c * Math.min(100, score)) / 100}
          />
        </svg>
        <span className="absolute inset-0 grid place-items-center font-display text-lg font-bold text-foreground">{score}</span>
      </div>
      <span className="text-xs font-semibold text-muted">{label}</span>
    </div>
  )
}

/* --------------------------------------------------------- lesson pathway node */

export type NodeState = 'locked' | 'current' | 'completed'

export function LessonNode({ state, label, icon = '📘' }: { state: NodeState; label?: string; icon?: string }) {
  const styles: Record<NodeState, string> = {
    locked: 'bg-surface-muted text-subtle border-border',
    current: 'bg-primary text-primary-fg border-primary shadow-heritage ring-4 ring-primary/20 animate-pulse',
    completed: 'bg-gold-400 text-charcoal-900 border-gold-500 shadow-gold',
  }
  return (
    <div className="flex flex-col items-center gap-1.5">
      <div className={cn('grid size-14 place-items-center rounded-full border-2 text-xl font-bold', styles[state])}>
        {state === 'locked' ? '🔒' : state === 'completed' ? '★' : icon}
      </div>
      {label && <span className="max-w-20 text-center text-xs font-semibold text-muted">{label}</span>}
    </div>
  )
}

export function LessonStepProgress({ steps, current }: { steps: string[]; current: number }) {
  return (
    <div className="flex items-center gap-1.5">
      {steps.map((s, i) => (
        <div key={s} className="flex flex-1 flex-col items-center gap-1">
          <div className={cn('h-1.5 w-full rounded-full', i < current ? 'bg-primary' : i === current ? 'bg-gold-400' : 'bg-surface-sunken')} />
          <span className={cn('text-[10px] font-semibold', i <= current ? 'text-foreground' : 'text-subtle')}>{s}</span>
        </div>
      ))}
    </div>
  )
}

/* --------------------------------------------------------- current lesson card */

export interface CurrentLessonCardProps {
  language: string
  title: string
  unit: string
  hearts: number
  xp: number
  speakingScore: number
  progress: number
  onContinue?: () => void
}

export function CurrentLessonCard({ language, title, unit, hearts, xp, speakingScore, progress, onContinue }: CurrentLessonCardProps) {
  return (
    <Card className="w-80 overflow-hidden">
      <div className="flex items-center justify-between bg-heritage-500 px-5 py-3 text-primary-fg">
        <span className="font-display text-sm font-semibold">{language} · {unit}</span>
        <Badge variant="gold">Continue 🔥</Badge>
      </div>
      <CardBody className="flex flex-col gap-4">
        <div>
          <h3 className="font-display text-xl font-semibold text-foreground">{title}</h3>
          <p className="text-sm text-muted">Pick up where you left off</p>
        </div>
        <div className="flex items-center gap-2">
          <HeartsCounter current={hearts} />
          <XpCounter value={xp} />
        </div>
        <div className="flex items-center justify-between">
          <Progress value={progress} className="flex-1" showLabel />
          <div className="pl-4"><SpeakingScoreGauge score={speakingScore} /></div>
        </div>
        <Button3D fullWidth onClick={onContinue}>Continue lesson →</Button3D>
      </CardBody>
    </Card>
  )
}

/* --------------------------------------------------------- cultural badge card */

export function CulturalBadgeCard({ emoji, title, subtitle, earned = true }: { emoji: string; title: string; subtitle: string; earned?: boolean }) {
  return (
    <div className={cn('flex w-40 flex-col items-center gap-2 rounded-2xl border p-4 text-center', earned ? 'border-gold-200 bg-gold-50 dark:border-gold-800 dark:bg-gold-900/20' : 'border-border bg-surface-muted opacity-60')}>
      <span className={cn('grid size-14 place-items-center rounded-full text-3xl', earned ? 'bg-gold-100 shadow-gold dark:bg-gold-900/40' : 'bg-surface-sunken grayscale')}>{emoji}</span>
      <div>
        <p className="font-display text-sm font-bold text-foreground">{title}</p>
        <p className="text-xs text-muted">{subtitle}</p>
      </div>
    </div>
  )
}

/* --------------------------------------------------------- reward celebration */

export function RewardCelebrationModal({ open, coins, onClose }: { open: boolean; coins: number; onClose: () => void }) {
  if (!open) return null
  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-[var(--overlay)] p-4" role="dialog" aria-modal="true">
      <Card className="w-80 overflow-hidden text-center">
        <div className="bg-gradient-to-b from-gold-400 to-gold-500 px-6 py-8">
          <div className="mx-auto grid size-20 place-items-center rounded-full bg-white/30 text-5xl">🎉</div>
        </div>
        <CardBody className="flex flex-col items-center gap-3">
          <h3 className="font-display text-2xl font-bold text-foreground">Lesson complete!</h3>
          <CoinPill amount={coins} />
          <p className="text-sm text-muted">You earned {coins} coins and protected your streak.</p>
          <Button3D variant="reward" fullWidth onClick={onClose}>Claim coins</Button3D>
        </CardBody>
      </Card>
    </div>
  )
}
