import type { HTMLAttributes, ReactNode } from 'react'
import { cn } from '@/lib/cn'

export type AlertVariant = 'info' | 'success' | 'warning' | 'grace' | 'danger'

const VARIANTS: Record<AlertVariant, string> = {
  info: 'bg-chore-50 border-chore-200 text-chore-700 dark:bg-chore-900/30 dark:border-chore-800 dark:text-chore-100',
  success: 'bg-leaf-50 border-leaf-200 text-leaf-700 dark:bg-leaf-700/25 dark:border-leaf-700 dark:text-leaf-100',
  warning: 'bg-gold-50 border-gold-200 text-gold-800 dark:bg-gold-900/30 dark:border-gold-800 dark:text-gold-100',
  // grace = telco/billing, framed positively (streak protection, not punishment)
  grace: 'bg-gold-50 border-gold-300 text-gold-800 dark:bg-gold-900/30 dark:border-gold-700 dark:text-gold-100',
  danger: 'bg-red-50 border-red-200 text-red-700 dark:bg-red-900/30 dark:border-red-800 dark:text-red-100',
}

export interface AlertProps extends HTMLAttributes<HTMLDivElement> {
  variant?: AlertVariant
  title?: string
  icon?: ReactNode
}

export function Alert({ variant = 'info', title, icon, className, children, ...props }: AlertProps) {
  return (
    <div role="alert" className={cn('flex gap-3 rounded-xl border p-4 text-sm', VARIANTS[variant], className)} {...props}>
      {icon && <span className="mt-0.5 shrink-0">{icon}</span>}
      <div className="flex flex-col gap-0.5">
        {title && <p className="font-display font-semibold">{title}</p>}
        {children && <div className="opacity-90">{children}</div>}
      </div>
    </div>
  )
}
