import { useState } from 'react'
import { Link } from 'react-router-dom'
import { AdminPageHeader, AdminToolbar, DataTable, FilterSelect } from '@/components/admin'
import { Alert, Button, Card, CardBody } from '@/components/ui'
import { useDataSales } from '@/lib/admin/queries'
import { formatMoney } from '@/lib/format'
import type { DataSaleRow } from '@/lib/api'

const label = (value: string) => value.replace(/_/g, ' ')
const money = (value: number) => formatMoney(Number(value), 'NGN')

export function DataSalesPage() {
  const [from, setFrom] = useState(() => new Date().toISOString().slice(0, 7) + '-01')
  const [to, setTo] = useState('')
  const [status, setStatus] = useState('')
  const [q, setQ] = useState('')
  const [page, setPage] = useState(1)
  const report = useDataSales({ from: from || undefined, to: to || undefined, status: status || undefined, q: q || undefined, page })
  const data = report.data
  const summary = data?.summary
  const max = Math.max(1, ...(data?.daily.map((d) => Number(d.sales_minor)) ?? []))
  return <div className="space-y-6">
    <AdminPageHeader title="Mobile data sales" description="Monitor purchases, confirmed payments, and data delivery. Updates every 30 seconds." />
    <div className="flex flex-wrap items-end gap-3 rounded-2xl border border-border bg-surface p-4">
      <label className="text-sm">From<input aria-label="From" type="date" value={from} onChange={(e) => { setFrom(e.target.value); setPage(1) }} className="ml-2 rounded-lg border border-border bg-surface p-2" /></label>
      <label className="text-sm">To<input aria-label="To" type="date" value={to} min={from} onChange={(e) => { setTo(e.target.value); setPage(1) }} className="ml-2 rounded-lg border border-border bg-surface p-2" /></label>
      <Button variant="outline" onClick={() => { setFrom(''); setTo(''); setPage(1) }}>All time</Button>
      <Button variant="ghost" loading={report.isFetching} onClick={() => void report.refetch()}>Refresh</Button>
    </div>
    {report.error && <Alert variant="danger">Could not load sales. Check the dates or try refreshing.</Alert>}
    {report.isLoading && <p role="status">Loading sales…</p>}
    {summary && <>
      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {[
          ['Delivered sales', money(summary.delivered_sales_minor), `${summary.successful} completed purchases`],
          ['Verified payments', money(summary.verified_payments_minor), 'Gross paid amount, before refunds and fees'],
          ['Awaiting payment', String(summary.awaiting_payment), `${summary.processing} paid purchases processing`],
          ['Needs attention', String(summary.needs_attention), `${summary.payment_failed} unsuccessful payments`],
        ].map(([title, value, hint]) => <Card key={title}><CardBody><p className="text-sm text-muted">{title}</p><p className="my-2 font-display text-2xl font-bold">{value}</p><p className="text-xs text-muted">{hint}</p></CardBody></Card>)}
      </div>
      <p className="text-xs text-muted">{summary.purchases} purchases started in this period. Dates group purchases by creation time. Delivered sales exclude failed or unconfirmed deliveries; totals represent gross sales, not profit.</p>
      <div className="grid gap-6 lg:grid-cols-2">
        <Card><CardBody><h2 className="mb-4 font-display text-lg font-bold">Delivered sales by day</h2>
          {!data?.daily.length && <p className="text-sm text-muted">No completed sales in this period.</p>}
          <div className="max-h-80 space-y-3 overflow-auto">{data?.daily.map((d) => <div key={d.date} className="space-y-1"><div className="flex justify-between gap-3 text-xs"><span>{d.date} · {d.purchases} sales</span><span>{money(d.sales_minor)}</span></div><div className="h-2 rounded-full bg-surface-muted"><div className="h-2 rounded-full bg-primary" style={{ width: `${Number(d.sales_minor) / max * 100}%` }} /></div></div>)}</div>
        </CardBody></Card>
        <Card><CardBody><h2 className="mb-4 font-display text-lg font-bold">Network performance</h2>
          {!data?.networks.length && <p className="text-sm text-muted">No purchases in this period.</p>}
          <div className="space-y-4">{data?.networks.map((n) => <div key={n.network} className="flex justify-between gap-3 border-b border-border pb-3"><div><p className="font-semibold">{n.network}</p><p className="text-xs text-muted">{n.successful} delivered / {n.purchases} started</p></div><p className="font-semibold">{money(n.sales_minor)}</p></div>)}</div>
        </CardBody></Card>
      </div>
    </>}
    <DataTable<DataSaleRow> rows={data?.data ?? []} getRowId={(r) => r.id} isLoading={report.isLoading} empty="No purchases match this period and filters."
      toolbar={<AdminToolbar search={q} onSearch={(value) => { setQ(value); setPage(1) }} searchPlaceholder="Search buyer email, plan, or reference…"><FilterSelect label="Status" value={status} onChange={(value) => { setStatus(value); setPage(1) }} options={Object.keys(data?.statuses ?? {}).map((value) => ({ value, label: label(value) }))} allLabel="All statuses" /></AdminToolbar>}
      columns={[
        { key: 'buyer', header: 'Buyer / recipient', render: (r) => <div><p>{r.buyer ?? 'Unknown buyer'}</p><p className="text-xs text-muted">Recipient ending {r.recipient_last4 || '—'}</p></div> },
        { key: 'plan', header: 'Plan', render: (r) => <div><p>{r.plan ?? 'Legacy purchase'}</p><p className="text-xs text-muted">{r.network}</p></div> },
        { key: 'amount', header: 'Amount', render: (r) => money(r.amount_minor) },
        { key: 'status', header: 'Status', render: (r) => <span className={r.status === 'success' ? 'font-semibold text-primary' : 'text-muted'}>{label(r.status)}</span> },
        { key: 'date', header: 'Created', render: (r) => r.created_at ? new Date(r.created_at).toLocaleString() : '—', hideOnMobile: true },
        { key: 'reference', header: 'Activity', render: (r) => <div><p className="max-w-48 break-all text-xs text-muted">{r.reference ?? `Purchase #${r.id}`}</p><Link className="inline-flex min-h-9 items-center text-sm text-primary" to={`/admin/audit?q=${encodeURIComponent(r.reference ?? 'billing.data_bundle')}`}>View activity</Link></div> },
      ]} />
    {data && <div className="flex items-center justify-between gap-3 text-sm"><span>{data.meta.total} matching purchases · Page {data.meta.current_page} of {data.meta.last_page}</span><div className="flex gap-2"><Button variant="ghost" disabled={page <= 1 || report.isFetching} onClick={() => setPage(page - 1)}>Previous</Button><Button variant="ghost" disabled={page >= data.meta.last_page || report.isFetching} onClick={() => setPage(page + 1)}>Next</Button></div></div>}
  </div>
}
