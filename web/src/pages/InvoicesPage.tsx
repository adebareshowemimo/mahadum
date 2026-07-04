import { useState } from 'react'
import { Alert, Badge, Button, Card, CardBody, Skeleton } from '@/components/ui'
import { schoolApi } from '@/lib/api'
import { formatMoney } from '@/lib/format'
import { SchoolGate } from '@/components/school/SchoolGate'
import { useInvoices } from '@/lib/school/queries'

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
  const [downloading, setDownloading] = useState<number | null>(null)
  const [error, setError] = useState<string | null>(null)

  async function download(id: number) {
    setDownloading(id)
    setError(null)
    try {
      await schoolApi.downloadInvoice(orgId, id)
    } catch {
      setError('Could not download that invoice. Please try again.')
    } finally {
      setDownloading(null)
    }
  }

  if (isLoading) return <Skeleton className="h-40" />
  if (isError || !data) return <Alert variant="danger">Couldn’t load invoices.</Alert>

  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="font-display text-2xl font-bold text-foreground">Invoices</h1>
        <p className="mt-1 text-muted">Billing history for your school.</p>
      </div>

      {error && <Alert variant="danger">{error}</Alert>}

      {data.length === 0 ? (
        <Card>
          <CardBody className="py-10 text-center text-sm text-muted">
            No invoices yet. Buying seats generates a proforma invoice.
          </CardBody>
        </Card>
      ) : (
        <div className="flex flex-col gap-2">
          {data.map((inv) => (
            <Card key={inv.id}>
              <CardBody className="flex flex-wrap items-center justify-between gap-3 py-3">
                <div>
                  <p className="font-semibold text-foreground">
                    {formatMoney(inv.amount_minor, 'NGN')}{' '}
                    <span className="text-sm font-normal capitalize text-muted">· {inv.type}</span>
                  </p>
                  <p className="text-xs text-muted">
                    Issued {inv.issued_at ? new Date(inv.issued_at).toLocaleDateString() : '—'}
                  </p>
                </div>
                <div className="flex items-center gap-3">
                  <Badge variant={STATUS_TONE[inv.status] ?? 'neutral'}>{inv.status}</Badge>
                  <Button size="sm" variant="outline" loading={downloading === inv.id} onClick={() => download(inv.id)}>
                    PDF
                  </Button>
                </div>
              </CardBody>
            </Card>
          ))}
        </div>
      )}
    </div>
  )
}
