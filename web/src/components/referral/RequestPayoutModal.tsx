import { useState, type FormEvent } from 'react'
import { Alert, Button, Input, Modal } from '@/components/ui'
import { ApiError } from '@/lib/api'
import { useRequestPayout } from '@/lib/referral/queries'

export const PAYOUT_FLOOR_NAIRA = 5000

/** Shared payout-request modal (floor ₦5,000, cap ₦50k/mo enforced server-side). */
export function RequestPayoutModal({ open, onClose }: { open: boolean; onClose: () => void }) {
  const requestPayout = useRequestPayout()
  const [amount, setAmount] = useState('')
  const [method, setMethod] = useState<'bank' | 'coins'>('bank')
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})
  const [formError, setFormError] = useState<string | null>(null)

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setFieldErrors({})
    setFormError(null)
    const naira = Number(amount)
    if (naira < PAYOUT_FLOOR_NAIRA) {
      setFieldErrors({ amount_minor: `Minimum payout is ₦${PAYOUT_FLOOR_NAIRA.toLocaleString()}.` })
      return
    }
    try {
      await requestPayout.mutateAsync({ amount_minor: Math.round(naira * 100), method })
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
    <Modal open={open} onClose={onClose} title="Request a payout" description={`Minimum ₦${PAYOUT_FLOOR_NAIRA.toLocaleString()}. Cap ₦50,000 / month.`}>
      <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
        {formError && <Alert variant="danger">{formError}</Alert>}
        <Input
          label="Amount (NGN)"
          type="number"
          min={PAYOUT_FLOOR_NAIRA}
          step="0.01"
          value={amount}
          onChange={(e) => setAmount(e.target.value)}
          error={fieldErrors.amount_minor}
          autoFocus
          required
        />
        <label className="flex flex-col gap-1.5">
          <span className="text-sm font-semibold text-foreground">Method</span>
          <select
            value={method}
            onChange={(e) => setMethod(e.target.value as 'bank' | 'coins')}
            className="h-11 rounded-xl border border-border-strong bg-surface px-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
          >
            <option value="bank">Bank transfer</option>
            <option value="coins">Wallet coins</option>
          </select>
        </label>
        <div className="flex gap-2">
          <Button type="button" variant="secondary" fullWidth onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" variant="reward" fullWidth loading={requestPayout.isPending}>
            Request payout
          </Button>
        </div>
      </form>
    </Modal>
  )
}
