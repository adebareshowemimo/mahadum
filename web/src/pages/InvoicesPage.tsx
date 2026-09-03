import { useState } from 'react'
import { Alert, Badge, Button, Card, CardBody, Icon, Skeleton } from '@/components/ui'
import { ApiError, schoolApi, type SchoolInvoice } from '@/lib/api'
import { formatMoney } from '@/lib/format'
import { SchoolGate } from '@/components/school/SchoolGate'
import { useInvoices, usePayInvoice } from '@/lib/school/queries'

export function InvoicesPage() {
  return <SchoolGate>{(orgId) => <Invoices orgId={orgId} />}</SchoolGate>
}

const STATUS_TONE: Record<string, 'success' | 'gold' | 'danger' | 'neutral'> = {
  paid: 'success',
  unpaid: 'gold',
  overdue: 'danger',
  cancelled: 'neutral',
}

function Invoices({ orgId }: { orgId: number }) {
  const { data, isLoading, isError } = useInvoices(orgId)
  const pay = usePayInvoice(orgId)
  const [downloading, setDownloading] = useState<number | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [checkoutUrl, setCheckoutUrl] = useState<string | null>(null)

  async function download(id: number) {
    setDownloading(id)
    setError(null)
    try {
      await schoolApi.downloadInvoice(orgId, id)
    } catch (err) {
      setError(
        err instanceof ApiError ? err.message : 'Could not download that invoice. Please try again.',
      )
    } finally {
      setDownloading(null)
    }
  }

  async function payInvoiceNow(id: number) {
    setError(null)
    setCheckoutUrl(null)
    try {
      const result = await pay.mutateAsync({ invoiceId: id })
      if (result.checkout_url) setCheckoutUrl(result.checkout_url)
      else setError('Payment started — the invoice will be marked paid once confirmed.')
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not start payment.')
    }
  }

  if (isLoading) return <Skeleton className="h-40" />
  if (isError || !data) return <Alert variant="danger">Couldn’t load invoices.</Alert>

  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="font-display text-2xl font-bold text-foreground">Invoices</h1>
        <p className="mt-1 max-w-2xl text-muted">
          Review student school fees, registration fees, tax, and the final amount before paying.
        </p>
      </div>

      {error && <Alert variant="danger">{error}</Alert>}
      {checkoutUrl && (
        <Alert variant="info" title="Checkout started">
          <a href={checkoutUrl} className="font-semibold underline" target="_blank" rel="noreferrer">
            Open secure checkout
          </a>
        </Alert>
      )}

      {data.length === 0 ? (
        <Card>
          <CardBody className="py-10 text-center text-sm text-muted">
            No invoices yet. Buying seats generates a proforma invoice.
          </CardBody>
        </Card>
      ) : (
        <div className="overflow-hidden rounded-2xl border border-border bg-surface">
          {data.map((inv) => (
            <article key={inv.id} className="border-b border-border p-5 last:border-b-0 sm:p-6">
              <div className="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div className="min-w-0 flex-1">
                  <div className="flex flex-wrap items-center gap-2">
                    <h2 className="font-semibold text-foreground">Invoice #{String(inv.id).padStart(6, '0')}</h2>
                    <Badge variant={STATUS_TONE[inv.status] ?? 'neutral'}>{inv.status}</Badge>
                    <span className="text-sm capitalize text-muted">{inv.type}</span>
                  </div>
                  <p className="mt-1 text-xs text-muted">
                    Issued {inv.issued_at ? new Date(inv.issued_at).toLocaleDateString() : '—'}
                    {inv.paid_at ? ` · Paid ${new Date(inv.paid_at).toLocaleDateString()}` : ''}
                  </p>

                  <InvoiceBreakdown invoice={inv} />
                </div>
                <div className="flex shrink-0 flex-wrap items-center gap-2 lg:pt-1">
                  {inv.status === 'unpaid' && (
                    <Button
                      size="sm"
                      variant="parent"
                      loading={pay.isPending && pay.variables?.invoiceId === inv.id}
                      onClick={() => payInvoiceNow(inv.id)}
                    >
                      Pay now
                    </Button>
                  )}
                  <Button size="sm" variant="outline" loading={downloading === inv.id} onClick={() => download(inv.id)}>
                    <Icon name="clipboard" className="size-4" />
                    Download PDF
                  </Button>
                </div>
              </div>
            </article>
          ))}
        </div>
      )}
    </div>
  )
}

function InvoiceBreakdown({ invoice }: { invoice: SchoolInvoice }) {
  const lines = invoice.lines ?? []
  const schoolFees = lines.find((line) => line.description.toLowerCase().includes('student school'))
  const registrationFees = lines.find((line) => line.description.toLowerCase().includes('registration'))
  const additionalLines = lines.filter((line) => line !== schoolFees && line !== registrationFees)
  const hasFeeBreakdown = Boolean(schoolFees || registrationFees)

  return (
    <div className="mt-4 max-w-xl rounded-xl bg-surface-muted p-4">
      <dl className="flex flex-col gap-3 text-sm">
        <BreakdownRow label="Student School Fees" amount={schoolFees?.amount_minor} />
        <BreakdownRow label="Registration Fees" amount={registrationFees?.amount_minor} />
        {additionalLines.map((line, index) => (
          <BreakdownRow key={`${line.description}-${index}`} label={line.description} amount={line.amount_minor} subdued />
        ))}
        <div className="border-t border-border pt-3">
          <div className="flex items-baseline justify-between gap-6">
            <dt className="font-semibold text-foreground">Total {invoice.status === 'paid' ? 'paid' : 'due'}</dt>
            <dd className="text-base font-bold tabular-nums text-foreground">{formatMoney(invoice.amount_minor, 'NGN')}</dd>
          </div>
        </div>
      </dl>
      {!hasFeeBreakdown && (
        <p className="mt-3 text-xs text-muted">The detailed fee split is unavailable for this older invoice.</p>
      )}
    </div>
  )
}

function BreakdownRow({ label, amount, subdued = false }: { label: string; amount?: number; subdued?: boolean }) {
  return (
    <div className="flex items-baseline justify-between gap-6">
      <dt className={subdued ? 'text-muted' : 'font-medium text-foreground'}>{label}</dt>
      <dd className={`tabular-nums ${subdued ? 'text-muted' : 'font-semibold text-foreground'}`}>
        {amount == null ? '—' : formatMoney(amount, 'NGN')}
      </dd>
    </div>
  )
}
