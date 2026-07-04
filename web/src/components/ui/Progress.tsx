import { cn } from '@/lib/cn'

export type ProgressTone = 'primary' | 'accent' | 'success'

const TONES: Record<ProgressTone, string> = {
  primary: 'bg-primary',
  accent: 'bg-accent',
  success: 'bg-success',
}

export interface ProgressProps {
  value: number
  max?: number
  tone?: ProgressTone
  className?: string
  showLabel?: boolean
}

export function Progress({ value, max = 100, tone = 'primary', className, showLabel }: ProgressProps) {
  const pct = Math.max(0, Math.min(100, (value / max) * 100))
  return (
    <div className={cn('flex items-center gap-3', className)}>
      <div
        className="h-2.5 flex-1 overflow-hidden rounded-full bg-surface-sunken"
        role="progressbar"
        aria-valuenow={Math.round(pct)}
        aria-valuemin={0}
        aria-valuemax={100}
      >
        <div className={cn('h-full rounded-full transition-[width] duration-300', TONES[tone])} style={{ width: `${pct}%` }} />
      </div>
      {showLabel && <span className="w-10 text-right text-xs font-bold text-muted tabular-nums">{Math.round(pct)}%</span>}
    </div>
  )
}
