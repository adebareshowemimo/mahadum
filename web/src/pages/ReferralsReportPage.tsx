import { useState } from 'react'
import { AdminPageHeader, MonthlyTable } from '@/components/admin'
import { Alert, Badge, Card, CardBody, Input, Skeleton } from '@/components/ui'
import type { IncomeReportQuery } from '@/lib/api'
import { formatMoney } from '@/lib/format'
import { useReferralsReport } from '@/lib/admin/queries'

const REF_TONE: Record<string, 'success' | 'gold' | 'danger' | 'neutral'> = {
  qualified: 'success',
  pending: 'gold',
  rejected: 'danger',
}
const COMM_TONE: Record<string, 'success' | 'gold' | 'danger' | 'neutral'> = {
  cleared: 'success',
  pending_escrow: 'gold',
  clawback_pending: 'danger',
}

export function ReferralsReportPage() {
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const params: IncomeReportQuery = { from: from || undefined, to: to || undefined }
  const { data, isLoading, isError } = useReferralsReport(params)

  return (
    <div className="flex flex-col gap-6">
      <AdminPageHeader
        title="Referrals & commissions"
        description="New referrals over time, the referral status mix, and commission totals by state."
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

      {isError && <Alert variant="danger">Couldn’t load the referrals report.</Alert>}
      {isLoading && !data && <Skeleton className="h-64" />}

      {data && (
        <>
          <Card>
            <CardBody className="flex flex-col gap-2">
              <p className="text-sm font-semibold text-foreground">Referrals by status</p>
              <div className="flex flex-wrap gap-2">
                {Object.entries(data.referrals_by_status).map(([status, count]) => (
                  <Badge key={status} variant={REF_TONE[status] ?? 'neutral'}>
                    {status}: {count}
                  </Badge>
                ))}
              </div>
            </CardBody>
          </Card>

          <Card>
            <CardBody className="flex flex-col gap-3">
              <p className="text-sm font-semibold text-foreground">Commissions by status</p>
              <div className="overflow-x-auto">
                <table className="w-full text-left text-sm">
                  <thead>
                    <tr className="border-b border-border text-xs uppercase tracking-wide text-muted">
                      <th className="py-2 pr-4 font-semibold">Status</th>
                      <th className="px-4 py-2 text-right font-semibold">Count</th>
                      <th className="px-4 py-2 text-right font-semibold">Total</th>
                    </tr>
                  </thead>
                  <tbody>
                    {Object.entries(data.commissions_by_status).map(([status, v]) => (
                      <tr key={status} className="border-b border-border last:border-0">
                        <td className="py-2 pr-4">
                          <Badge variant={COMM_TONE[status] ?? 'neutral'}>{status}</Badge>
                        </td>
                        <td className="px-4 py-2 text-right tabular-nums text-foreground">{v.count}</td>
                        <td className="px-4 py-2 text-right tabular-nums font-semibold text-foreground">
                          {formatMoney(v.total_minor, 'NGN')}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </CardBody>
          </Card>

          <MonthlyTable months={data.months} rows={[data.new]} rowHeader="Metric" totalHeader="In range" />
        </>
      )}
    </div>
  )
}
