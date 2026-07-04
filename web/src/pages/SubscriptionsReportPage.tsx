import { useState } from 'react'
import { AdminPageHeader, MonthlyTable } from '@/components/admin'
import { Alert, Badge, Card, CardBody, Input, Skeleton } from '@/components/ui'
import type { IncomeReportQuery } from '@/lib/api'
import { useSubscriptionsReport } from '@/lib/admin/queries'

const STATUS_TONE: Record<string, 'success' | 'gold' | 'danger' | 'neutral'> = {
  active: 'success',
  pending: 'gold',
  cancelled: 'danger',
  expired: 'neutral',
}

export function SubscriptionsReportPage() {
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const params: IncomeReportQuery = { from: from || undefined, to: to || undefined }
  const { data, isLoading, isError } = useSubscriptionsReport(params)

  return (
    <div className="flex flex-col gap-6">
      <AdminPageHeader
        title="Subscriptions"
        description="New subscriptions over time, plus the current status mix."
        backTo="/admin/reports"
        backLabel="Reports"
      />

      <Card>
        <CardBody className="flex flex-wrap items-end gap-4">
          <Input label="From" type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="w-auto" />
          <Input label="To" type="date" value={to} onChange={(e) => setTo(e.target.value)} className="w-auto" />
          {data && (
            <p className="pb-2.5 text-xs text-muted">
              Showing {data.from} → {data.to}
            </p>
          )}
        </CardBody>
      </Card>

      {isError && <Alert variant="danger">Couldn’t load the subscriptions report.</Alert>}
      {isLoading && !data && <Skeleton className="h-64" />}

      {data && (
        <>
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <Card>
              <CardBody className="py-4">
                <p className="text-xs uppercase tracking-wide text-muted">Total</p>
                <p className="mt-1 text-2xl font-bold tabular-nums text-foreground">{data.total.toLocaleString()}</p>
              </CardBody>
            </Card>
            <Card>
              <CardBody className="py-4">
                <p className="text-xs uppercase tracking-wide text-muted">Active</p>
                <p className="mt-1 text-2xl font-bold tabular-nums text-leaf-600">{data.active.toLocaleString()}</p>
              </CardBody>
            </Card>
          </div>

          <Card>
            <CardBody className="flex flex-wrap gap-2">
              {Object.entries(data.by_status).map(([status, count]) => (
                <Badge key={status} variant={STATUS_TONE[status] ?? 'neutral'}>
                  {status}: {count}
                </Badge>
              ))}
            </CardBody>
          </Card>

          <MonthlyTable months={data.months} rows={[data.new]} rowHeader="Metric" totalHeader="In range" />
        </>
      )}
    </div>
  )
}
