import { useState, type FormEvent } from 'react'
import { Alert, Button, Input, Modal } from '@/components/ui'
import { ApiError } from '@/lib/api'
import { formatMoney } from '@/lib/format'
import { useRequestTeacherCompensationPayout } from '@/lib/school/queries'

export const TEACHING_PAYOUT_FLOOR_NAIRA = 5000

/** Bank-only payout against a teacher's accrued class compensation (see EarningsPage). */
export function RequestTeachingPayoutModal({
  open,
  onClose,
  availableMinor,
}: {
  open: boolean
  onClose: () => void
  availableMinor: number
}) {
  const requestPayout = useRequestTeacherCompensationPayout()
  const [amount, setAmount] = useState('')
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})
  const [formError, setFormError] = useState<string | null>(null)

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setFieldErrors({})
    setFormError(null)
    const naira = Number(amount)
    if (naira < TEACHING_PAYOUT_FLOOR_NAIRA) {
      setFieldErrors({ amount_minor: `Minimum payout is ₦${TEACHING_PAYOUT_FLOOR_NAIRA.toLocaleString()}.` })
      return
    }
    try {
      await requestPayout.mutateAsync({ amount_minor: Math.round(naira * 100) })
      setAmount('')
      onClose()
    } catch (err) {
      if (err instanceof ApiError) {
        setFieldErrors(err.fieldErrors)
        if (!Object.keys(err.fieldErrors).length) setFormError(err.message)
      } else {
        setFormError('Something went wrong. Please try again.')
      }
    }
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Request a teaching payout"
      description={`Available: ${formatMoney(availableMinor, 'NGN')}. Paid to your bank account.`}
    >
      <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
        {formError && <Alert variant="danger">{formError}</Alert>}
        <Input
          label="Amount (NGN)"
          type="number"
          min={TEACHING_PAYOUT_FLOOR_NAIRA}
          step="0.01"
          value={amount}
          onChange={(e) => setAmount(e.target.value)}
          error={fieldErrors.amount_minor}
          autoFocus
          required
        />
        <div className="flex gap-2">
          <Button type="button" variant="secondary" fullWidth onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" variant="parent" fullWidth loading={requestPayout.isPending}>
            Request payout
          </Button>
        </div>
      </form>
    </Modal>
  )
}
