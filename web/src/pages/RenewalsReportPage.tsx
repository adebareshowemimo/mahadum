import { useState } from 'react'
import { AdminPageHeader, MonthlyTable } from '@/components/admin'
import { Alert, Badge, Card, CardBody, Input, Skeleton } from '@/components/ui'
import type { IncomeReportQuery } from '@/lib/api'
import { formatMoney } from '@/lib/format'
import { useRenewalsReport } from '@/lib/admin/queries'

function Stat({ label, value }: { label: string; value: string | number }) {
  return (
    <Card>
      <CardBody className="py-4">
        <p className="text-xs uppercase tracking-wide text-muted">{label}</p>
        <p className="mt-1 text-2xl font-bold tabular-nums text-foreground">{value}</p>
      </CardBody>
    </Card>
  )
}

export function RenewalsReportPage() {
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const params: IncomeReportQuery = { from: from || undefined, to: to || undefined }
  const { data, isLoading, isError } = useRenewalsReport(params)

  return (
    <div className="flex flex-col gap-6">
      <AdminPageHeader
        title="Upcoming renewals"
        description="Active subscriptions due to renew over the coming months — expected renewal volume, revenue, and reminder coverage."
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

      {isError && <Alert variant="danger">Couldn’t load the renewals report.</Alert>}
      {isLoading && !data && <Skeleton className="h-64" />}

      {data && (
        <>
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <Stat label="Renewals due" value={data.count.total.toLocaleString()} />
            <Stat label="Expected revenue" value={formatMoney(data.revenue.total, 'NGN')} />
            <Stat
              label="Reminders sent"
              value={`${data.reminders.reminded}/${data.reminders.total}`}
            />
          </div>

          <Card>
            <CardBody className="flex flex-col gap-2">
              <p className="text-sm font-semibold text-foreground">Renewals by payment method</p>
              {Object.keys(data.by_method).length ? (
                <div className="flex flex-wrap gap-2">
                  {Object.entries(data.by_method).map(([method, count]) => (
                    <Badge key={method} variant="neutral">
                      {method}: {count}
                    </Badge>
                  ))}
                </div>
              ) : (
                <p className="text-sm text-muted">No renewals fall in this window.</p>
              )}
            </CardBody>
          </Card>

          <MonthlyTable months={data.months} rows={[data.count]} rowHeader="Renewals" totalHeader="In range" />
          <MonthlyTable
            months={data.months}
            rows={[data.revenue]}
            format={(n) => formatMoney(n, 'NGN')}
            rowHeader="Expected revenue"
            totalHeader="Total"
          />
        </>
      )}
    </div>
  )
}
