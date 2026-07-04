import { useState } from 'react'
import { Alert, Button, CodeInput, Input, Modal } from '@/components/ui'
import { ApiError, billingApi, type Plan, type TelcoOperator } from '@/lib/api'
import { formatMoney } from '@/lib/format'
import { useTelcoSubscribe } from '@/lib/billing/queries'

export const OPERATORS: { value: TelcoOperator; label: string }[] = [
  { value: 'mtn', label: 'MTN' },
  { value: 'airtel', label: 'Airtel' },
  { value: 'glo', label: 'Glo' },
  { value: 't2', label: '9mobile' },
]

/** Opt into daily airtime (VAS) billing for a paid plan: OTP verify → subscribe. */
export function TelcoOptInModal({ plan, open, onClose }: { plan: Plan | null; open: boolean; onClose: () => void }) {
  const subscribe = useTelcoSubscribe()
  const [step, setStep] = useState<'phone' | 'code'>('phone')
  const [msisdn, setMsisdn] = useState('')
  const [operator, setOperator] = useState<TelcoOperator>('mtn')
  const [code, setCode] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [done, setDone] = useState(false)

  const dailyMinor = plan ? Math.max(1, Math.round(plan.price_minor / 30)) : 0

  function close() {
    setStep('phone'); setMsisdn(''); setOperator('mtn'); setCode(''); setError(null); setDone(false)
    onClose()
  }

  async function sendCode() {
    setBusy(true); setError(null)
    try {
      await billingApi.telcoRequestOtp({ msisdn, operator })
      setStep('code')
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not send the code.')
    } finally {
      setBusy(false)
    }
  }

  async function confirm() {
    if (!plan || code.length < 6) return
    setBusy(true); setError(null)
    try {
      await billingApi.telcoVerifyOtp({ msisdn, code })
      await subscribe.mutateAsync({ plan_id: plan.id, msisdn, operator })
      setDone(true)
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not verify the code.')
    } finally {
      setBusy(false)
    }
  }

  return (
    <Modal
      open={open}
      onClose={close}
      title="Pay with airtime"
      description={plan ? `${plan.name} · ~${formatMoney(dailyMinor, plan.currency)}/day` : undefined}
    >
      {done ? (
        <div className="flex flex-col gap-4">
          <Alert variant="success" title="You're subscribed">
            Daily airtime billing is active on {msisdn}. To stop, text STOP to 3600.
          </Alert>
          <Button fullWidth onClick={close}>
            Done
          </Button>
        </div>
      ) : step === 'phone' ? (
        <div className="flex flex-col gap-4">
          {error && <Alert variant="danger">{error}</Alert>}
          <Input
            label="Phone number"
            type="tel"
            placeholder="08012345678"
            value={msisdn}
            onChange={(e) => setMsisdn(e.target.value)}
            autoFocus
          />
          <label className="flex flex-col gap-1.5">
            <span className="text-sm font-semibold text-foreground">Network</span>
            <select
              value={operator}
              onChange={(e) => setOperator(e.target.value as TelcoOperator)}
              className="h-11 rounded-xl border border-border-strong bg-surface px-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
            >
              {OPERATORS.map((o) => (
                <option key={o.value} value={o.value}>{o.label}</option>
              ))}
            </select>
          </label>
          <Button fullWidth size="lg" loading={busy} disabled={!msisdn} onClick={sendCode}>
            Send code
          </Button>
        </div>
      ) : (
        <div className="flex flex-col items-center gap-4">
          <p className="text-sm text-muted">Enter the 6-digit code sent to {msisdn}.</p>
          <CodeInput value={code} onChange={(v) => { setCode(v); setError(null) }} length={6} error={!!error} aria-label="OTP code" />
          {error && <p className="text-xs font-medium text-danger">{error}</p>}
          <div className="flex w-full gap-2">
            <Button type="button" variant="secondary" fullWidth onClick={() => setStep('phone')}>
              Back
            </Button>
            <Button fullWidth loading={busy} disabled={code.length < 6} onClick={confirm}>
              Confirm & subscribe
            </Button>
          </div>
        </div>
      )}
    </Modal>
  )
}
