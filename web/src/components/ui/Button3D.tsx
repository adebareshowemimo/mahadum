import { forwardRef, type ButtonHTMLAttributes, type ReactNode } from 'react'
import { cn } from '@/lib/cn'

export type Button3DVariant = 'primary' | 'reward' | 'premium' | 'parent' | 'billing' | 'danger' | 'neutral'
export type Button3DSize = 'sm' | 'md' | 'lg'

/**
 * Chunky 3D button (v2). The solid offset shadow is the button's "side"; on
 * press it sinks (`active:translate-y` + shorter shadow) for a tactile, playful
 * feel. African Gold variants keep charcoal text. Edge colours use the palette's
 * darker shades via CSS vars so they track the theme.
 */
const VARIANTS: Record<Button3DVariant, string> = {
  primary: 'bg-heritage-500 text-white shadow-[0_6px_0_var(--heritage-700)] hover:bg-heritage-400 active:shadow-[0_2px_0_var(--heritage-700)]',
  reward: 'bg-gold-400 text-charcoal-900 shadow-[0_6px_0_var(--gold-600)] hover:bg-gold-300 active:shadow-[0_2px_0_var(--gold-600)]',
  premium: 'bg-navy-800 text-gold-300 shadow-[0_6px_0_var(--navy-950)] hover:bg-navy-700 active:shadow-[0_2px_0_var(--navy-950)]',
  parent: 'bg-chore-500 text-white shadow-[0_6px_0_var(--chore-700)] hover:bg-chore-400 active:shadow-[0_2px_0_var(--chore-700)]',
  billing: 'bg-gold-500 text-charcoal-900 shadow-[0_6px_0_var(--gold-700)] hover:bg-gold-400 active:shadow-[0_2px_0_var(--gold-700)]',
  danger: 'bg-red-500 text-white shadow-[0_6px_0_var(--red-700)] hover:bg-red-400 active:shadow-[0_2px_0_var(--red-700)]',
  neutral: 'bg-surface text-foreground shadow-[0_6px_0_var(--border-strong)] hover:bg-surface-muted active:shadow-[0_2px_0_var(--border-strong)]',
}

const SIZES: Record<Button3DSize, string> = {
  // 40px painted + an invisible 2px band top/bottom = a 44px WCAG 2.5.5 target,
  // without thickening the chunky 3D silhouette.
  sm: "relative h-10 px-4 text-sm rounded-xl gap-1.5 after:absolute after:inset-x-0 after:-inset-y-0.5 after:content-['']",
  md: 'h-12 px-6 text-base rounded-2xl gap-2',
  lg: 'h-14 px-8 text-lg rounded-2xl gap-2.5',
}

export interface Button3DProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Button3DVariant
  size?: Button3DSize
  fullWidth?: boolean
  leftIcon?: ReactNode
}

export const Button3D = forwardRef<HTMLButtonElement, Button3DProps>(function Button3D(
  { variant = 'primary', size = 'md', fullWidth, leftIcon, className, children, ...props },
  ref,
) {
  return (
    <button
      ref={ref}
      className={cn(
        'inline-flex select-none items-center justify-center font-display font-bold uppercase tracking-wide',
        'transition-[transform,box-shadow,background-color] duration-100 active:translate-y-[4px]',
        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background',
        'disabled:pointer-events-none disabled:opacity-50',
        VARIANTS[variant],
        SIZES[size],
        fullWidth && 'w-full',
        className,
      )}
      {...props}
    >
      {leftIcon}
      {children}
    </button>
  )
})
