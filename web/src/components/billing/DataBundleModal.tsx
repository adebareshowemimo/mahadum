import { useState } from 'react'
import { Alert, Button, Modal } from '@/components/ui'
import { ApiError, billingApi, type DataBundle, type TelcoOperator } from '@/lib/api'
import { cn } from '@/lib/cn'
import { formatMoney } from '@/lib/format'
import { useDataBundles } from '@/lib/billing/queries'
import { OPERATORS } from './TelcoOptInModal'

function label(mb: number): string {
  return mb >= 1024 ? `${(mb / 1024).toFixed(mb % 1024 === 0 ? 0 : 1)}GB` : `${mb}MB`
}

/** Carrier data-bundle store: pick a bundle + network, consent, one-tap charge. */
export function DataBundleModal({ open, onClose }: { open: boolean; onClose: () => void }) {
  const { data: bundles, isLoading } = useDataBundles()
  const [operator, setOperator] = useState<TelcoOperator>('mtn')
  const [selected, setSelected] = useState<DataBundle | null>(null)
  const [consent, setConsent] = useState(false)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [done, setDone] = useState(false)

  function close() {
    setSelected(null); setConsent(false); setError(null); setDone(false); setOperator('mtn')
    onClose()
  }

  async function buy() {
    if (!selected || !consent) return
    setBusy(true); setError(null)
    try {
      await billingApi.purchaseDataBundle({ operator, bundle_mb: selected.bundle_mb, consent })
      setDone(true)
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not start the purchase.')
    } finally {
      setBusy(false)
    }
  }

  return (
    <Modal open={open} onClose={close} title="Buy mobile data" description="Charged to your airtime balance.">
      {done ? (
        <div className="flex flex-col gap-4">
          <Alert variant="success" title="Purchase started">
            Your data bundle is being processed and will be credited shortly.
          </Alert>
          <Button fullWidth onClick={close}>Done</Button>
        </div>
      ) : (
        <div className="flex flex-col gap-4">
          {error && <Alert variant="danger">{error}</Alert>}

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

          {isLoading ? (
            <p className="text-sm text-muted">Loading bundles…</p>
          ) : (
            <div className="grid grid-cols-3 gap-2">
              {bundles?.map((b) => {
                const active = selected?.bundle_mb === b.bundle_mb
                return (
                  <button
                    key={b.bundle_mb}
                    type="button"
                    onClick={() => setSelected(b)}
                    className={cn(
                      'flex flex-col items-center gap-1 rounded-xl border-2 p-3 text-center transition-colors',
                      active ? 'border-primary bg-primary-soft' : 'border-border-strong hover:bg-surface-muted',
                    )}
                  >
                    <span className="font-display text-lg font-bold text-foreground">{label(b.bundle_mb)}</span>
                    <span className="text-xs text-muted">{formatMoney(b.amount_minor, b.currency)}</span>
                  </button>
                )
              })}
            </div>
          )}

          <label className="flex items-start gap-2.5 text-sm text-foreground">
            <input
              type="checkbox"
              checked={consent}
              onChange={(e) => setConsent(e.target.checked)}
              className="mt-0.5 size-4 rounded border-border-strong text-primary focus:ring-ring"
            />
            <span>I consent to charging this purchase to my carrier airtime balance.</span>
          </label>

          <Button variant="billing" size="lg" fullWidth loading={busy} disabled={!selected || !consent} onClick={buy}>
            {selected ? `Buy ${label(selected.bundle_mb)}` : 'Choose a bundle'}
          </Button>
        </div>
      )}
    </Modal>
  )
}
