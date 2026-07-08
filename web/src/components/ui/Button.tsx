import { forwardRef, type ButtonHTMLAttributes, type ReactNode } from 'react'
import { cn } from '@/lib/cn'
import { Spinner } from './Spinner'

export type ButtonVariant =
  | 'primary' // Heritage — Start / Continue lesson
  | 'reward' // Gold (dark text) — Claim coins, celebrations
  | 'premium' // Navy + gold — Upgrade to Mahadum Plus
  | 'parent' // Chore blue — Approve reward / review
  | 'billing' // Gold — Recharge airtime
  | 'accent'
  | 'secondary'
  | 'outline'
  | 'ghost'
  | 'danger'
  | 'soft'
export type ButtonSize = 'sm' | 'md' | 'lg'

// African Gold (reward/billing) always pairs with charcoal text, never white.
// Exported so LinkButton can render identical styling on an <a> — a <button>
// must never nest inside an <a> (invalid HTML, double-announced to screen
// readers), so navigation-as-button uses this shared class set instead.
export const BUTTON_VARIANT_CLASSES: Record<ButtonVariant, string> = {
  primary: 'bg-primary text-primary-fg hover:bg-primary-hover shadow-sm',
  reward: 'bg-gold-400 text-charcoal-900 hover:bg-gold-500 shadow-gold',
  premium: 'bg-navy-900 text-gold-300 hover:bg-navy-800 shadow-md',
  parent: 'bg-chore-500 text-white hover:bg-chore-600 shadow-sm',
  billing: 'bg-gold-500 text-charcoal-900 hover:bg-gold-600 shadow-sm',
  accent: 'bg-accent text-accent-fg hover:bg-accent-hover shadow-sm',
  secondary: 'bg-surface-muted text-foreground hover:bg-surface-sunken',
  outline: 'border border-border-strong bg-surface text-foreground hover:bg-surface-muted',
  ghost: 'text-foreground hover:bg-surface-muted',
  danger: 'bg-danger text-danger-fg hover:brightness-95 shadow-sm',
  soft: 'bg-primary-soft text-primary hover:bg-heritage-100 dark:hover:bg-heritage-900/40',
}

export const BUTTON_SIZE_CLASSES: Record<ButtonSize, string> = {
  sm: 'h-9 px-3 text-sm rounded-lg gap-1.5',
  md: 'h-11 px-4 text-sm rounded-xl gap-2',
  lg: 'h-12 px-6 text-base rounded-xl gap-2',
}

export const BUTTON_BASE_CLASSES =
  'inline-flex items-center justify-center font-display font-semibold transition-colors ' +
  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background'

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: ButtonVariant
  size?: ButtonSize
  loading?: boolean
  leftIcon?: ReactNode
  rightIcon?: ReactNode
  fullWidth?: boolean
}

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(function Button(
  { variant = 'primary', size = 'md', loading = false, leftIcon, rightIcon, fullWidth, className, children, disabled, ...props },
  ref,
) {
  return (
    <button
      ref={ref}
      disabled={disabled || loading}
      className={cn(
        BUTTON_BASE_CLASSES,
        'disabled:opacity-50 disabled:pointer-events-none',
        BUTTON_VARIANT_CLASSES[variant],
        BUTTON_SIZE_CLASSES[size],
        fullWidth && 'w-full',
        className,
      )}
      {...props}
    >
      {loading ? <Spinner className="size-4" /> : leftIcon}
      {children}
      {!loading && rightIcon}
    </button>
  )
})
