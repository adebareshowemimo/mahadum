import type { HTMLAttributes } from 'react'
import { cn } from '@/lib/cn'

export type BadgeVariant =
  | 'neutral'
  | 'primary' // heritage
  | 'gold' // reward / streak / premium-soft
  | 'success' // leaf
  | 'warning' // gold
  | 'danger'
  | 'info' // chore blue
  | 'ai' // speaking / AI
  | 'clay' // cultural
  | 'premium' // solid navy + gold

const VARIANTS: Record<BadgeVariant, string> = {
  neutral: 'bg-surface-muted text-muted',
  primary: 'bg-heritage-100 text-heritage-700 dark:bg-heritage-900/40 dark:text-heritage-200',
  gold: 'bg-gold-100 text-gold-800 dark:bg-gold-900/40 dark:text-gold-200',
  success: 'bg-leaf-100 text-leaf-700 dark:bg-leaf-700/30 dark:text-leaf-100',
  warning: 'bg-gold-100 text-gold-800 dark:bg-gold-900/40 dark:text-gold-100',
  danger: 'bg-red-100 text-red-700 dark:bg-red-700/30 dark:text-red-100',
  info: 'bg-chore-100 text-chore-700 dark:bg-chore-900/40 dark:text-chore-200',
  ai: 'bg-ai-100 text-ai-700 dark:bg-ai-900/40 dark:text-ai-200',
  clay: 'bg-clay-100 text-clay-700 dark:bg-clay-700/30 dark:text-clay-100',
  premium: 'bg-navy-900 text-gold-300',
}

export interface BadgeProps extends HTMLAttributes<HTMLSpanElement> {
  variant?: BadgeVariant
  dot?: boolean
}

export function Badge({ variant = 'neutral', dot, className, children, ...props }: BadgeProps) {
  return (
    <span
      className={cn(
        'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-bold',
        VARIANTS[variant],
        className,
      )}
      {...props}
    >
      {dot && <span className="size-1.5 rounded-full bg-current" />}
      {children}
    </span>
  )
}
