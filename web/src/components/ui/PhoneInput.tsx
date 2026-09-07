import { useId, useState } from 'react'
import { getCountries, getCountryCallingCode } from 'libphonenumber-js/min'
import { cn } from '@/lib/cn'

/** All countries and territories with calling codes, Nigeria first. */
const countryNames = new Intl.DisplayNames(['en'], { type: 'region' })
export const DIAL_CODES = getCountries().map((country) => ({
  country,
  code: `+${getCountryCallingCode(country)}`,
  label: countryNames.of(country) ?? country,
  flag: String.fromCodePoint(...Array.from(country, (letter) => 127397 + letter.charCodeAt(0))),
})).sort((a, b) => {
  if (a.country === 'NG') return -1
  if (b.country === 'NG') return 1
  return a.label.localeCompare(b.label, 'en')
})

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
  // Keep country identity separate: many countries share +1, +44, etc.
  const [selectedCountry, setSelectedCountry] = useState('NG')
  const activeCountry = DIAL_CODES.find((c) => c.country === selectedCountry && c.code === activeCode)
    ?? DIAL_CODES.find((c) => c.code === activeCode)
  const flag = activeCountry?.flag ?? '🌍'

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
            value={activeCountry?.country ?? ''}
            onChange={(e) => {
              const country = DIAL_CODES.find((c) => c.country === e.target.value)
              if (country) {
                setSelectedCountry(country.country)
                onDialCodeChange!(country.code)
              }
            }}
            className="h-11 w-1/2 min-w-0 shrink-0 truncate border-r border-border bg-transparent px-2 text-sm font-semibold text-muted focus:outline-none"
          >
            {DIAL_CODES.map((c) => (
              <option key={c.country} value={c.country}>
                {c.label} ({c.code})
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
          className="h-11 min-w-0 flex-1 bg-transparent px-3 text-sm text-foreground placeholder:text-subtle focus:outline-none"
        />
      </div>
      {error && <p className="text-xs font-medium text-danger">{error}</p>}
    </div>
  )
}
