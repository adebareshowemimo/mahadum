import { useState } from 'react'
import { AdminPageHeader, MonthlyTable } from '@/components/admin'
import { Alert, Badge, Card, CardBody, Input, Skeleton } from '@/components/ui'
import type { IncomeReportQuery } from '@/lib/api'
import { useOrgActivityReport } from '@/lib/admin/queries'

const STATUS_TONE: Record<string, 'success' | 'gold' | 'danger' | 'neutral'> = {
  active: 'success',
  pending: 'gold',
  suspended: 'danger',
  inactive: 'neutral',
}

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

export function OrgActivityReportPage() {
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const params: IncomeReportQuery = { from: from || undefined, to: to || undefined }
  const { data, isLoading, isError } = useOrgActivityReport(params)

  return (
    <div className="flex flex-col gap-6">
      <AdminPageHeader
        title="Organizations & schools"
        description="New organizations over time, the status mix, and platform-wide class & student totals."
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

      {isError && <Alert variant="danger">Couldn’t load the organizations report.</Alert>}
      {isLoading && !data && <Skeleton className="h-64" />}

      {data && (
        <>
          <div className="grid grid-cols-3 gap-3">
            <Stat label="Organizations" value={data.totals.organizations} />
            <Stat label="Classes" value={data.totals.classes} />
            <Stat label="Students" value={data.totals.students} />
          </div>

          <Card>
            <CardBody className="flex flex-col gap-2">
              <p className="text-sm font-semibold text-foreground">Organizations by status</p>
              <div className="flex flex-wrap gap-2">
                {Object.entries(data.by_status).map(([status, count]) => (
                  <Badge key={status} variant={STATUS_TONE[status] ?? 'neutral'}>
                    {status}: {count}
                  </Badge>
                ))}
              </div>
            </CardBody>
          </Card>

          <MonthlyTable months={data.months} rows={[data.new]} rowHeader="Metric" totalHeader="In range" />
        </>
      )}
    </div>
  )
}
