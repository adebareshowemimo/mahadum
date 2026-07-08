import { forwardRef, type AnchorHTMLAttributes, type ReactNode } from 'react'
import { Link, type LinkProps } from 'react-router-dom'
import { cn } from '@/lib/cn'
import { BUTTON_BASE_CLASSES, BUTTON_SIZE_CLASSES, BUTTON_VARIANT_CLASSES, type ButtonSize, type ButtonVariant } from './Button'

export interface LinkButtonProps extends LinkProps, Omit<AnchorHTMLAttributes<HTMLAnchorElement>, 'href'> {
  variant?: ButtonVariant
  size?: ButtonSize
  leftIcon?: ReactNode
  rightIcon?: ReactNode
  fullWidth?: boolean
}

/**
 * A navigation link styled identically to Button. Use this instead of
 * `<Link><Button>…</Button></Link>` — nesting a <button> inside an <a> is
 * invalid HTML and gets announced as two separate interactive elements by
 * screen readers.
 */
export const LinkButton = forwardRef<HTMLAnchorElement, LinkButtonProps>(function LinkButton(
  { variant = 'primary', size = 'md', leftIcon, rightIcon, fullWidth, className, children, ...props },
  ref,
) {
  return (
    <Link
      ref={ref}
      className={cn(
        BUTTON_BASE_CLASSES,
        BUTTON_VARIANT_CLASSES[variant],
        BUTTON_SIZE_CLASSES[size],
        fullWidth && 'w-full',
        className,
      )}
      {...props}
    >
      {leftIcon}
      {children}
      {rightIcon}
    </Link>
  )
})
