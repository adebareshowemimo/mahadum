import { useState } from 'react'
import { Button, CodeInput, Modal } from '@/components/ui'
import { ApiError } from '@/lib/api'
import { useSetChildPin } from '@/lib/family/queries'

/** Set/change/remove one child's own 4-digit PIN — used on the Family page. */
export function SetPinModal({
  open,
  onClose,
  learnerId,
  learnerName,
  hasPin,
}: {
  open: boolean
  onClose: () => void
  learnerId: number
  learnerName: string
  hasPin: boolean
}) {
  const setChildPin = useSetChildPin()
  const [pin, setPinValue] = useState('')
  const [error, setError] = useState<string | null>(null)

  async function submit() {
    if (pin.length < 4) return
    setError(null)
    try {
      await setChildPin.mutateAsync({ learnerId, pin })
      setPinValue('')
      onClose()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not save the PIN.')
    }
  }

  async function remove() {
    setError(null)
    try {
      await setChildPin.mutateAsync({ learnerId, pin: null })
      setPinValue('')
      onClose()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not remove the PIN.')
    }
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={hasPin ? `Change ${learnerName}'s PIN` : `Set a PIN for ${learnerName}`}
      description="A unique 4-digit PIN protects this profile — no other family member can switch into it without it."
    >
      <form
        className="flex flex-col items-center gap-4"
        onSubmit={(e) => {
          e.preventDefault()
          void submit()
        }}
      >
        <CodeInput
          value={pin}
          onChange={(v) => {
            setPinValue(v)
            setError(null)
          }}
          length={4}
          mask
          error={!!error}
          aria-label={`PIN for ${learnerName}`}
        />
        {error && <p className="text-xs font-medium text-danger">{error}</p>}
        <div className="flex w-full gap-2">
          <Button type="button" variant="secondary" fullWidth onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" fullWidth loading={setChildPin.isPending} disabled={pin.length < 4}>
            Save PIN
          </Button>
        </div>
        {hasPin && (
          <Button
            type="button"
            variant="danger"
            fullWidth
            loading={setChildPin.isPending}
            onClick={() => void remove()}
          >
            Remove PIN
          </Button>
        )}
      </form>
    </Modal>
  )
}
