import { useId } from 'react'
import { cn } from '@/lib/cn'

export interface PhoneInputProps {
  value: string
  onChange: (value: string) => void
  label?: string
  dialCode?: string
  error?: string
  disabled?: boolean
}

/** Phone-first input with a fixed dial code (Nigeria-first). */
export function PhoneInput({ value, onChange, label, dialCode = '+234', error, disabled }: PhoneInputProps) {
  const id = useId()
  return (
    <div className="flex flex-col gap-1.5">
      {label && (
        <label htmlFor={id} className="text-sm font-semibold text-foreground">
          {label}
        </label>
      )}
      <div
        className={cn(
          'flex items-center rounded-xl border bg-surface transition-colors focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-1 focus-within:ring-offset-background',
          error ? 'border-danger' : 'border-border-strong',
          disabled && 'opacity-50',
        )}
      >
        <span className="flex items-center gap-1.5 border-r border-border px-3 text-sm font-semibold text-muted">
          <span aria-hidden>🇳🇬</span> {dialCode}
        </span>
        <input
          id={id}
          type="tel"
          inputMode="numeric"
          disabled={disabled}
          value={value}
          onChange={(e) => onChange(e.target.value.replace(/[^\d]/g, ''))}
          placeholder="803 123 4567"
          className="h-11 flex-1 bg-transparent px-3 text-sm text-foreground placeholder:text-subtle focus:outline-none"
        />
      </div>
      {error && <p className="text-xs font-medium text-danger">{error}</p>}
    </div>
  )
}
