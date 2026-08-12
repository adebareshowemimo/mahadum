import { useState } from 'react'
import { Button, CodeInput, Modal } from '@/components/ui'
import { ApiError } from '@/lib/api'
import { useSetPin } from '@/lib/family/queries'

/** Shared 4-digit parental-PIN set/change modal — used on the Family page and Billing page. */
export function SetPinModal({ open, onClose, hasPin }: { open: boolean; onClose: () => void; hasPin: boolean }) {
  const setPin = useSetPin()
  const [pin, setPinValue] = useState('')
  const [error, setError] = useState<string | null>(null)

  async function submit() {
    if (pin.length < 4) return
    setError(null)
    try {
      await setPin.mutateAsync(pin)
      setPinValue('')
      onClose()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not save the PIN.')
    }
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={hasPin ? 'Change parental PIN' : 'Set a parental PIN'}
      description="A 4-digit PIN protects child profile switching."
    >
      <form
        className="flex flex-col items-center gap-4"
        onSubmit={(e) => {
          e.preventDefault()
          void submit()
        }}
      >
        <CodeInput value={pin} onChange={(v) => { setPinValue(v); setError(null) }} length={4} mask error={!!error} aria-label="Parental PIN" />
        {error && <p className="text-xs font-medium text-danger">{error}</p>}
        <div className="flex w-full gap-2">
          <Button type="button" variant="secondary" fullWidth onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" fullWidth loading={setPin.isPending} disabled={pin.length < 4}>
            Save PIN
          </Button>
        </div>
      </form>
    </Modal>
  )
}
