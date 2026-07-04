import { useRef, type ClipboardEvent, type KeyboardEvent } from 'react'
import { cn } from '@/lib/cn'

export interface CodeInputProps {
  value: string
  onChange: (value: string) => void
  length?: number
  mask?: boolean
  disabled?: boolean
  error?: boolean
  'aria-label'?: string
}

/** N-cell code entry — OTP (length 6), parental PIN (length 4 + mask), etc. */
export function CodeInput({ value, onChange, length = 6, mask = false, disabled, error, ...aria }: CodeInputProps) {
  const refs = useRef<(HTMLInputElement | null)[]>([])
  const digits = value.padEnd(length).slice(0, length).split('')

  const setAt = (i: number, char: string) => {
    const next = digits.map((d, idx) => (idx === i ? char : d)).join('').replace(/\s/g, '')
    onChange(next.slice(0, length))
    if (char && i < length - 1) refs.current[i + 1]?.focus()
  }

  const onKeyDown = (i: number, e: KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Backspace' && !digits[i].trim() && i > 0) refs.current[i - 1]?.focus()
  }

  const onPaste = (e: ClipboardEvent<HTMLInputElement>) => {
    e.preventDefault()
    const pasted = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, length)
    if (pasted) {
      onChange(pasted)
      refs.current[Math.min(pasted.length, length - 1)]?.focus()
    }
  }

  return (
    <div className="flex gap-2" role="group" aria-label={aria['aria-label'] ?? 'Verification code'}>
      {Array.from({ length }).map((_, i) => (
        <input
          key={i}
          ref={(el) => { refs.current[i] = el }}
          inputMode="numeric"
          maxLength={1}
          type={mask ? 'password' : 'text'}
          disabled={disabled}
          value={digits[i].trim()}
          onChange={(e) => setAt(i, e.target.value.replace(/\D/g, '').slice(-1))}
          onKeyDown={(e) => onKeyDown(i, e)}
          onPaste={onPaste}
          className={cn(
            'size-12 rounded-xl border bg-surface text-center font-display text-xl font-semibold text-foreground',
            'focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-1 focus:ring-offset-background',
            'disabled:opacity-50',
            error ? 'border-danger' : 'border-border-strong',
          )}
        />
      ))}
    </div>
  )
}
