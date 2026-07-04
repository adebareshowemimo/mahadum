import { useState, type FormEvent } from 'react'
import {
  Alert,
  Button,
  Card,
  CardBody,
  Input,
  Modal,
  Progress,
  Skeleton,
  Switch,
} from '@/components/ui'
import { ApiError, type PurchaseSeatsResult } from '@/lib/api'
import { formatMoney } from '@/lib/format'
import { SchoolGate } from '@/components/school/SchoolGate'
import { usePurchaseSeats, useSeats } from '@/lib/school/queries'

export function SeatsPage() {
  return <SchoolGate>{(orgId) => <Seats orgId={orgId} />}</SchoolGate>
}

function Seats({ orgId }: { orgId: number }) {
  const { data, isLoading, isError } = useSeats(orgId)
  const [buyOpen, setBuyOpen] = useState(false)

  if (isLoading) return <Skeleton className="h-40" />
  if (isError || !data) return <Alert variant="danger">Couldn’t load seats.</Alert>

  const pct = data.total_purchased > 0 ? Math.round((data.active_filled / data.total_purchased) * 100) : 0

  return (
    <div className="flex flex-col gap-8">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="font-display text-2xl font-bold text-foreground">Seats</h1>
          <p className="mt-1 text-muted">Manage your school’s student licences.</p>
        </div>
        <Button variant="premium" onClick={() => setBuyOpen(true)}>
          Buy seats
        </Button>
      </div>

      <Card>
        <CardBody className="flex flex-col gap-3">
          <div className="flex items-baseline justify-between">
            <span className="font-display text-3xl font-bold text-foreground">
              {data.active_filled}
              <span className="text-lg text-muted"> / {data.total_purchased}</span>
            </span>
            <span className="text-sm text-muted">{pct}% filled</span>
          </div>
          <Progress value={pct} />
        </CardBody>
      </Card>

      <section>
        <h2 className="mb-3 font-display text-lg font-bold text-foreground">Allocations</h2>
        {data.allocations.length === 0 ? (
          <Card>
            <CardBody className="py-8 text-center text-sm text-muted">
              No seat allocations yet. Buy seats to onboard students.
            </CardBody>
          </Card>
        ) : (
          <div className="flex flex-col gap-2">
            {data.allocations.map((a) => (
              <Card key={a.id}>
                <CardBody className="flex items-center justify-between gap-3 py-3">
                  <div>
                    <p className="font-semibold text-foreground">
                      {a.active_filled}/{a.total_purchased} seats
                    </p>
                    <p className="text-xs text-muted">
                      {a.term_label ?? 'Term'} · expires {a.expires_at ? new Date(a.expires_at).toLocaleDateString() : '—'}
                    </p>
                  </div>
                </CardBody>
              </Card>
            ))}
          </div>
        )}
      </section>

      <BuySeatsModal orgId={orgId} open={buyOpen} onClose={() => setBuyOpen(false)} />
    </div>
  )
}

function BuySeatsModal({ orgId, open, onClose }: { orgId: number; open: boolean; onClose: () => void }) {
  const purchase = usePurchaseSeats(orgId)
  const [qty, setQty] = useState('')
  const [term, setTerm] = useState('')
  const [includeRegistration, setIncludeRegistration] = useState(true)
  const [result, setResult] = useState<PurchaseSeatsResult | null>(null)
  const [error, setError] = useState<string | null>(null)

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setError(null)
    try {
      const res = await purchase.mutateAsync({
        quantity: Number(qty),
        term_label: term || undefined,
        include_registration: includeRegistration,
      })
      setResult(res)
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not complete the purchase.')
    }
  }

  function close() {
    setResult(null)
    setQty('')
    setTerm('')
    setIncludeRegistration(true)
    setError(null)
    onClose()
  }

  return (
    <Modal open={open} onClose={close} title="Buy seats" description="Annual registration + per-student rate, stepping down as your school grows.">
      {result ? (
        <div className="flex flex-col gap-4">
          <Alert variant="success" title="Seats reserved">
            {result.quantity} seats · {result.band}. A proforma invoice (#{result.invoice_id}) has been issued.
          </Alert>
          <Card>
            <CardBody className="flex flex-col gap-1.5 text-sm">
              <Row label={`Seats (${result.quantity} × ${formatMoney(result.per_student_minor, 'NGN')})`} value={formatMoney(result.seats_subtotal_minor, 'NGN')} />
              {result.registration_minor > 0 && (
                <Row label="Annual registration" value={formatMoney(result.registration_minor, 'NGN')} />
              )}
              <div className="mt-1 border-t border-border pt-1.5">
                <Row label="Total" value={formatMoney(result.amount_minor, 'NGN')} strong />
              </div>
            </CardBody>
          </Card>
          <Button fullWidth onClick={close}>
            Done
          </Button>
        </div>
      ) : (
        <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
          {error && <Alert variant="danger">{error}</Alert>}
          <Input
            label="Number of seats"
            type="number"
            min={1}
            value={qty}
            onChange={(e) => setQty(e.target.value)}
            required
          />
          <Input label="Term label (optional)" value={term} onChange={(e) => setTerm(e.target.value)} placeholder="e.g. 2026/27 Session" />
          <label className="flex items-center justify-between gap-3 text-sm">
            <span className="text-foreground">Include annual registration fee</span>
            <Switch checked={includeRegistration} onChange={setIncludeRegistration} />
          </label>
          <div className="flex gap-2">
            <Button type="button" variant="secondary" fullWidth onClick={close}>
              Cancel
            </Button>
            <Button type="submit" variant="premium" fullWidth loading={purchase.isPending} disabled={!qty}>
              Get quote
            </Button>
          </div>
        </form>
      )}
    </Modal>
  )
}

function Row({ label, value, strong = false }: { label: string; value: string; strong?: boolean }) {
  return (
    <div className="flex items-center justify-between gap-3">
      <span className={strong ? 'font-semibold text-foreground' : 'text-muted'}>{label}</span>
      <span className={strong ? 'font-display font-bold text-foreground' : 'text-foreground'}>{value}</span>
    </div>
  )
}
