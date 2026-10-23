import { forwardRef, useId, useState, type InputHTMLAttributes, type ReactNode } from 'react'
import { cn } from '@/lib/cn'

export interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
  label?: string
  hint?: string
  error?: string
  leftIcon?: ReactNode
  passwordToggle?: boolean
}

export const Input = forwardRef<HTMLInputElement, InputProps>(function Input(
  { label, hint, error, leftIcon, passwordToggle = false, className, id, type, ...props },
  ref,
) {
  const autoId = useId()
  const inputId = id ?? autoId
  const messageId = `${inputId}-message`
  const [passwordVisible, setPasswordVisible] = useState(false)
  const canTogglePassword = passwordToggle && type === 'password'

  return (
    <div className="flex flex-col gap-1.5">
      {label && (
        <label htmlFor={inputId} className="text-sm font-semibold text-foreground">
          {label}
        </label>
      )}
      <div className="relative">
        {leftIcon && <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted">{leftIcon}</span>}
        <input
          ref={ref}
          id={inputId}
          type={canTogglePassword && passwordVisible ? 'text' : type}
          aria-invalid={error ? true : undefined}
          aria-describedby={error || hint ? messageId : undefined}
          className={cn(
            'h-11 w-full rounded-xl border bg-surface px-3.5 text-sm text-foreground placeholder:text-subtle',
            'transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-1 focus:ring-offset-background',
            leftIcon && 'pl-10',
            canTogglePassword && 'pr-12',
            error ? 'border-danger focus:ring-danger' : 'border-border-strong',
            className,
          )}
          {...props}
        />
        {canTogglePassword && (
          <button
            type="button"
            aria-label={`${passwordVisible ? 'Hide' : 'Show'} ${label?.toLowerCase() || 'password'}`}
            aria-controls={inputId}
            aria-pressed={passwordVisible}
            disabled={props.disabled}
            onClick={() => setPasswordVisible((visible) => !visible)}
            className="absolute right-0 top-0 flex size-11 items-center justify-center rounded-xl text-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
          >
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" className="size-5">
              <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
              <circle cx="12" cy="12" r="3" />
              {passwordVisible && <path d="m3 3 18 18" />}
            </svg>
          </button>
        )}
      </div>
      {error ? (
        <p id={messageId} role="alert" className="text-xs font-medium text-danger">{error}</p>
      ) : hint ? (
        <p id={messageId} className="text-xs text-muted">{hint}</p>
      ) : null}
    </div>
  )
})
