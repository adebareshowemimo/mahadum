import { forwardRef, type ButtonHTMLAttributes, type ReactNode } from 'react'
import { cn } from '@/lib/cn'

export type IconButtonVariant =
  | 'ghost' // muted glyph on a transparent chip — topbars, drawer, modal close
  | 'danger' // destructive row action
  | 'overlay' // on the player's gilded canvas, where `surface-*` tokens don't apply
  | 'plain' // caller supplies the whole skin via className (toggles, swatches)
export type IconButtonSize = 'sm' | 'md' | 'lg'
export type IconButtonShape = 'square' | 'circle'

/**
 * WCAG 2.5.5 / 2.5.8 require a 44x44 CSS-px target. Several controls in this
 * design language are deliberately small (a 32px modal close, a 36px topbar
 * chip) and enlarging them visually would coarsen the layout, so the hit area
 * is expanded *invisibly* with a centred, transparent ::after overlay instead.
 * The painted box keeps its `size-*`; only the tappable region grows.
 *
 * `lg` is already 44px, so its overlay is a no-op — use it where neighbouring
 * controls sit flush and an overflowing hit area would steal their taps.
 */
const HIT_AREA_44 =
  "relative after:absolute after:left-1/2 after:top-1/2 after:size-11 after:-translate-x-1/2 after:-translate-y-1/2 after:content-['']"

const VARIANTS: Record<IconButtonVariant, string> = {
  ghost: 'text-muted hover:bg-surface-muted',
  danger: 'text-danger hover:bg-surface-muted',
  overlay: 'text-foreground/60 hover:bg-foreground/10 hover:text-foreground',
  plain: '',
}

const SIZES: Record<IconButtonSize, string> = {
  sm: 'size-8', // 32px painted
  md: 'size-9', // 36px painted
  lg: 'size-11', // 44px painted — no overlay overhang
}

const SHAPES: Record<IconButtonShape, string> = {
  square: 'rounded-lg',
  circle: 'rounded-full',
}

export interface IconButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  /** Required — an icon-only control has no accessible name otherwise. */
  'aria-label': string
  variant?: IconButtonVariant
  size?: IconButtonSize
  shape?: IconButtonShape
  children: ReactNode
}

export const IconButton = forwardRef<HTMLButtonElement, IconButtonProps>(function IconButton(
  { variant = 'ghost', size = 'md', shape = 'square', className, children, type = 'button', ...props },
  ref,
) {
  return (
    <button
      ref={ref}
      type={type}
      className={cn(
        'inline-flex shrink-0 items-center justify-center transition-colors',
        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background',
        'disabled:pointer-events-none',
        HIT_AREA_44,
        SIZES[size],
        SHAPES[shape],
        VARIANTS[variant],
        className,
      )}
      {...props}
    >
      {children}
    </button>
  )
})
