import { useState } from 'react'
import { AdminPageHeader } from '@/components/admin'
import { Alert, Button, Card, CardBody, Input, Skeleton } from '@/components/ui'
import type { IncomeReport, IncomeReportQuery } from '@/lib/api'
import { formatMoney } from '@/lib/format'
import { useIncomeReport } from '@/lib/admin/queries'

/** '2026-06' → 'Jun 26'. */
function monthLabel(key: string): string {
  const [y, m] = key.split('-').map(Number)
  const name = new Date(y, (m ?? 1) - 1, 1).toLocaleString(undefined, { month: 'short' })
  return `${name} ${String(y).slice(2)}`
}

function toCsv(report: IncomeReport): string {
  const head = ['Channel', ...report.months.map(monthLabel), 'Gross', 'Refunds', 'Net']
  const rows = report.channels.map((c) => [
    c.label,
    ...report.months.map((m) => ((c.by_month[m] ?? 0) / 100).toFixed(2)),
    (c.gross / 100).toFixed(2),
    (c.refunds / 100).toFixed(2),
    (c.net / 100).toFixed(2),
  ])
  const totals = [
    'Total',
    ...report.months.map((m) => ((report.totals.by_month[m] ?? 0) / 100).toFixed(2)),
    (report.totals.gross / 100).toFixed(2),
    (report.totals.refunds / 100).toFixed(2),
    (report.totals.net / 100).toFixed(2),
  ]
  return [head, ...rows, totals].map((r) => r.join(',')).join('\n')
}

function download(report: IncomeReport) {
  const blob = new Blob([toCsv(report)], { type: 'text/csv;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `income_${report.from}_to_${report.to}.csv`
  a.click()
  URL.revokeObjectURL(url)
}

function SummaryCard({ label, value, tone }: { label: string; value: number; tone?: 'net' | 'refund' }) {
  return (
    <Card>
      <CardBody className="py-4">
        <p className="text-xs uppercase tracking-wide text-muted">{label}</p>
        <p
          className={
            tone === 'net'
              ? 'mt-1 text-2xl font-bold tabular-nums text-leaf-600'
              : tone === 'refund'
                ? 'mt-1 text-2xl font-bold tabular-nums text-danger'
                : 'mt-1 text-2xl font-bold tabular-nums text-foreground'
          }
        >
          {formatMoney(value, 'NGN')}
        </p>
      </CardBody>
    </Card>
  )
}

export function AdminIncomePage() {
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const params: IncomeReportQuery = { from: from || undefined, to: to || undefined }
  const { data, isLoading, isError } = useIncomeReport(params)

  return (
    <div className="flex flex-col gap-6">
      <AdminPageHeader
        title="Income report"
        description="Revenue by channel, month by month. Net = gross − refunds & clawback."
        actions={
          data ? (
            <Button variant="ghost" onClick={() => download(data)}>
              Export CSV
            </Button>
          ) : undefined
        }
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

      {isError && <Alert variant="danger">Couldn’t load the income report.</Alert>}
      {isLoading && !data && <Skeleton className="h-64" />}

      {data && (
        <>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <SummaryCard label="Gross" value={data.totals.gross} />
            <SummaryCard label="Refunds & clawback" value={data.totals.refunds} tone="refund" />
            <SummaryCard label="Net" value={data.totals.net} tone="net" />
          </div>

          <Card>
            <div className="overflow-x-auto">
              <table className="w-full text-right text-sm">
                <thead>
                  <tr className="border-b border-border text-xs uppercase tracking-wide text-muted">
                    <th className="px-4 py-2.5 text-left font-semibold">Channel</th>
                    {data.months.map((m) => (
                      <th key={m} className="px-4 py-2.5 font-semibold tabular-nums">
                        {monthLabel(m)}
                      </th>
                    ))}
                    <th className="px-4 py-2.5 font-semibold">Net</th>
                  </tr>
                </thead>
                <tbody>
                  {data.channels.map((c) => (
                    <tr key={c.key} className="border-b border-border last:border-0">
                      <td className="px-4 py-3 text-left font-semibold text-foreground">{c.label}</td>
                      {data.months.map((m) => (
                        <td key={m} className="px-4 py-3 tabular-nums text-muted">
                          {c.by_month[m] ? formatMoney(c.by_month[m], 'NGN') : '—'}
                        </td>
                      ))}
                      <td className="px-4 py-3 font-semibold tabular-nums text-foreground">{formatMoney(c.net, 'NGN')}</td>
                    </tr>
                  ))}
                </tbody>
                <tfoot>
                  <tr className="border-t-2 border-border-strong font-bold">
                    <td className="px-4 py-3 text-left">Total</td>
                    {data.months.map((m) => (
                      <td key={m} className="px-4 py-3 tabular-nums text-foreground">
                        {data.totals.by_month[m] ? formatMoney(data.totals.by_month[m], 'NGN') : '—'}
                      </td>
                    ))}
                    <td className="px-4 py-3 tabular-nums text-leaf-600">{formatMoney(data.totals.net, 'NGN')}</td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </Card>
        </>
      )}
    </div>
  )
}
