import { useId } from 'react'
import { cn } from '@/lib/cn'

/** Curated calling codes — Nigeria first, then the main diaspora destinations. */
export const DIAL_CODES: { code: string; label: string; flag: string }[] = [
  { code: '+234', label: 'Nigeria', flag: '🇳🇬' },
  { code: '+44', label: 'United Kingdom', flag: '🇬🇧' },
  { code: '+1', label: 'USA / Canada', flag: '🇺🇸' },
  { code: '+233', label: 'Ghana', flag: '🇬🇭' },
  { code: '+27', label: 'South Africa', flag: '🇿🇦' },
  { code: '+254', label: 'Kenya', flag: '🇰🇪' },
  { code: '+971', label: 'United Arab Emirates', flag: '🇦🇪' },
  { code: '+353', label: 'Ireland', flag: '🇮🇪' },
  { code: '+61', label: 'Australia', flag: '🇦🇺' },
  { code: '+49', label: 'Germany', flag: '🇩🇪' },
]

export interface PhoneInputProps {
  value: string
  onChange: (value: string) => void
  label?: string
  /** Fixed dial code (no selector). Ignored when `onDialCodeChange` is provided. */
  dialCode?: string
  /** When provided, renders a country selector and reports the chosen calling code. */
  dialCodeValue?: string
  onDialCodeChange?: (code: string) => void
  error?: string
  disabled?: boolean
  required?: boolean
}

/** Phone-first input. Nigeria-first, with an optional country-code selector. */
export function PhoneInput({
  value,
  onChange,
  label,
  dialCode = '+234',
  dialCodeValue,
  onDialCodeChange,
  error,
  disabled,
  required,
}: PhoneInputProps) {
  const id = useId()
  const selectable = typeof onDialCodeChange === 'function'
  const activeCode = selectable ? (dialCodeValue ?? '+234') : dialCode
  const flag = DIAL_CODES.find((c) => c.code === activeCode)?.flag ?? '🌍'

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
        {selectable ? (
          <select
            aria-label="Country calling code"
            disabled={disabled}
            value={activeCode}
            onChange={(e) => onDialCodeChange!(e.target.value)}
            className="h-11 shrink-0 border-r border-border bg-transparent px-2 text-sm font-semibold text-muted focus:outline-none"
          >
            {DIAL_CODES.map((c) => (
              <option key={c.code} value={c.code}>
                {c.flag} {c.code} {c.label}
              </option>
            ))}
          </select>
        ) : (
          <span className="flex items-center gap-1.5 border-r border-border px-3 text-sm font-semibold text-muted">
            <span aria-hidden>{flag}</span> {activeCode}
          </span>
        )}
        <input
          id={id}
          type="tel"
          inputMode="numeric"
          autoComplete="tel-national"
          required={required}
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
