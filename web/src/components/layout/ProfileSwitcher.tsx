import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Avatar, Button, CodeInput, Icon } from '@/components/ui'
import { cn } from '@/lib/cn'
import { ApiError, profileApi, type LearnerProfile } from '@/lib/api'
import { useActiveProfile } from '@/lib/profile/ActiveProfile'

const PIN_LENGTH = 4

/** Topbar control to switch into a child learner profile (PIN-gated when set). */
export function ProfileSwitcher() {
  const navigate = useNavigate()
  const { learners, activeLearner, setActiveLearner } = useActiveProfile()
  const [open, setOpen] = useState(false)
  const [pending, setPending] = useState<LearnerProfile | null>(null)
  const ref = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (!open) return
    function onDown(e: MouseEvent) {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false)
    }
    document.addEventListener('mousedown', onDown)
    return () => document.removeEventListener('mousedown', onDown)
  }, [open])

  if (learners.length === 0) return null

  async function enter(learner: LearnerProfile, pin?: string) {
    await profileApi.switch(learner.id, pin)
    setActiveLearner(learner.id)
    setOpen(false)
    setPending(null)
    navigate('/learn')
  }

  function onSelect(learner: LearnerProfile) {
    if (learner.id === activeLearner?.id) {
      setOpen(false)
      return
    }
    if (learner.pin_protected) setPending(learner)
    else void enter(learner).catch(() => undefined)
  }

  function exitToParent() {
    setActiveLearner(null)
    setOpen(false)
    navigate('/home')
  }

  return (
    <div className="relative" ref={ref}>
      <button
        onClick={() => setOpen((v) => !v)}
        className="flex items-center gap-2 rounded-full p-1 pr-2 hover:bg-surface-muted"
        aria-haspopup="menu"
        aria-expanded={open}
        title="Switch profile"
      >
        {activeLearner ? (
          <Avatar name={activeLearner.display_name} size="sm" />
        ) : (
          <span className="flex size-8 items-center justify-center rounded-full bg-surface-muted text-muted">
            <Icon name="users" className="size-[18px]" />
          </span>
        )}
        <span className="hidden max-w-[8rem] truncate text-sm font-medium text-foreground sm:inline">
          {activeLearner ? activeLearner.display_name : 'Profiles'}
        </span>
        <Icon name="chevron" className="size-4 text-muted" />
      </button>

      {open && (
        <div
          role="menu"
          className="absolute right-0 mt-2 w-64 overflow-hidden rounded-xl border border-border bg-surface shadow-lg animate-step-in"
        >
          <p className="px-4 pb-1 pt-3 text-[11px] font-semibold uppercase tracking-wide text-subtle">
            Learner profiles
          </p>
          <ul className="max-h-72 overflow-y-auto pb-1">
            {learners.map((learner) => {
              const active = learner.id === activeLearner?.id
              return (
                <li key={learner.id}>
                  <button
                    role="menuitemradio"
                    aria-checked={active}
                    onClick={() => onSelect(learner)}
                    className={cn(
                      'flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm hover:bg-surface-muted',
                      active && 'bg-primary-soft',
                    )}
                  >
                    <Avatar name={learner.display_name} size="sm" />
                    <span className="min-w-0 flex-1">
                      <span className="block truncate font-medium text-foreground">
                        {learner.display_name}
                      </span>
                      {learner.target_language && (
                        <span className="block text-xs uppercase text-muted">
                          {learner.target_language}
                        </span>
                      )}
                    </span>
                    {learner.pin_protected && <Icon name="shield" className="size-4 text-muted" />}
                    {active && <Icon name="chevron" className="size-4 rotate-90 text-primary" />}
                  </button>
                </li>
              )
            })}
          </ul>

          {activeLearner && (
            <button
              role="menuitem"
              onClick={exitToParent}
              className="flex w-full items-center gap-2.5 border-t border-border px-4 py-2.5 text-sm text-foreground hover:bg-surface-muted"
            >
              <Icon name="logout" className="size-[18px] text-muted" />
              Exit to parent
            </button>
          )}
        </div>
      )}

      {pending && (
        <PinModal
          learner={pending}
          onCancel={() => setPending(null)}
          onConfirm={(pin) => enter(pending, pin)}
        />
      )}
    </div>
  )
}

function PinModal({
  learner,
  onCancel,
  onConfirm,
}: {
  learner: LearnerProfile
  onCancel: () => void
  onConfirm: (pin: string) => Promise<void>
}) {
  const [pin, setPin] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  async function submit() {
    if (pin.length < PIN_LENGTH) return
    setSubmitting(true)
    setError(null)
    try {
      await onConfirm(pin)
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not switch profile.')
      setPin('')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-charcoal-900/50" onClick={onCancel} aria-hidden="true" />
      <div
        role="dialog"
        aria-modal="true"
        aria-label={`Enter PIN for ${learner.display_name}`}
        className="relative w-full max-w-xs rounded-2xl border border-border bg-surface p-6 shadow-lg animate-step-in"
      >
        <div className="flex flex-col items-center gap-3 text-center">
          <Avatar name={learner.display_name} size="lg" />
          <div>
            <h2 className="font-display text-lg font-bold text-foreground">{learner.display_name}</h2>
            <p className="mt-0.5 text-sm text-muted">Enter the parental PIN to continue.</p>
          </div>

          <form
            className="mt-2 flex flex-col items-center gap-3"
            onSubmit={(e) => {
              e.preventDefault()
              void submit()
            }}
          >
            <CodeInput
              value={pin}
              onChange={(v) => {
                setPin(v)
                setError(null)
              }}
              length={PIN_LENGTH}
              mask
              error={!!error}
              aria-label="Parental PIN"
            />
            {error && <p className="text-xs font-medium text-danger">{error}</p>}
            <div className="mt-1 flex w-full gap-2">
              <Button type="button" variant="secondary" fullWidth onClick={onCancel}>
                Cancel
              </Button>
              <Button type="submit" fullWidth loading={submitting} disabled={pin.length < PIN_LENGTH}>
                Continue
              </Button>
            </div>
          </form>
        </div>
      </div>
    </div>
  )
}
