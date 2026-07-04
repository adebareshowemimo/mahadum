import { useState } from 'react'
import { AdminPageHeader, MonthlyTable } from '@/components/admin'
import { Alert, Card, CardBody, Input, Skeleton } from '@/components/ui'
import type { IncomeReportQuery } from '@/lib/api'
import { useGrowthReport } from '@/lib/admin/queries'

function Stat({ label, value }: { label: string; value: number }) {
  return (
    <Card>
      <CardBody className="py-4">
        <p className="text-xs uppercase tracking-wide text-muted">{label}</p>
        <p className="mt-1 text-2xl font-bold tabular-nums text-foreground">{value.toLocaleString()}</p>
      </CardBody>
    </Card>
  )
}

export function GrowthReportPage() {
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const params: IncomeReportQuery = { from: from || undefined, to: to || undefined }
  const { data, isLoading, isError } = useGrowthReport(params)

  return (
    <div className="flex flex-col gap-6">
      <AdminPageHeader
        title="Growth"
        description="New users and organizations over time."
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

      {isError && <Alert variant="danger">Couldn’t load the growth report.</Alert>}
      {isLoading && !data && <Skeleton className="h-64" />}

      {data && (
        <>
          <div className="grid grid-cols-2 gap-3">
            <Stat label="Total users" value={data.totals.users} />
            <Stat label="Total organizations" value={data.totals.organizations} />
          </div>
          <MonthlyTable months={data.months} rows={data.series} rowHeader="Metric" totalHeader="In range" />
        </>
      )}
    </div>
  )
}
