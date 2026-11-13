import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { AdminPageHeader, AdminToolbar, DataTable, FilterSelect, MonthlyTable, type Column } from '@/components/admin'
import { Alert, Badge, Button, Card, CardBody, Input, Skeleton } from '@/components/ui'
import type { IncomeReportQuery, SubscriptionRecord } from '@/lib/api'
import { useSubscriptionRecords, useSubscriptionsReport } from '@/lib/admin/queries'

const STATUS_TONE: Record<string, 'success' | 'gold' | 'danger' | 'neutral'> = {
  active: 'success',
  pending: 'gold',
  cancelled: 'danger',
  expired: 'neutral',
}

function dateLabel(date: string | null) {
  if (!date) return '—'
  const [year, month, day] = date.split('-')
  return `${Number(month)}/${Number(day)}/${year}`
}

const columns: Column<SubscriptionRecord>[] = [
  { key: 'name', header: 'Name', className: 'whitespace-nowrap', render: (row) => <Link className="font-semibold text-primary hover:underline" to={`/admin/users/${row.user_id}`}>{row.name}</Link> },
  { key: 'email', header: 'Email', className: 'whitespace-nowrap', render: (row) => row.email },
  { key: 'phone', header: 'Phone', className: 'whitespace-nowrap tabular-nums', render: (row) => row.phone || '—' },
  { key: 'account_status', header: 'Account status', className: 'whitespace-nowrap', render: (row) => <Badge variant={row.account_status === 'active' ? 'success' : 'neutral'}>{row.account_status}</Badge> },
  { key: 'subscription_plan', header: 'Subscription plan', className: 'whitespace-nowrap', render: (row) => row.subscription_plan || '—' },
  { key: 'subscription_status', header: 'Subscription status', className: 'whitespace-nowrap', render: (row) => <Badge variant={STATUS_TONE[row.subscription_status] ?? 'neutral'}>{row.subscription_status}</Badge> },
  { key: 'subscription_start', header: 'Subscription start', className: 'whitespace-nowrap tabular-nums', render: (row) => dateLabel(row.subscription_start) },
  { key: 'subscription_expiry', header: 'Subscription expiry', className: 'whitespace-nowrap tabular-nums', render: (row) => dateLabel(row.subscription_expiry) },
  { key: 'payment_status', header: 'Payment status', className: 'whitespace-nowrap', render: (row) => <Badge variant={row.payment_status === 'paid' ? 'success' : row.payment_status === 'pending' ? 'gold' : 'neutral'}>{row.payment_status.replace('_', ' ')}</Badge> },
]

export function SubscriptionsReportPage() {
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [search, setSearch] = useState('')
  const [debouncedSearch, setDebouncedSearch] = useState('')
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const params: IncomeReportQuery = { from: from || undefined, to: to || undefined }
  const { data, isLoading, isError } = useSubscriptionsReport(params)
  const records = useSubscriptionRecords({ q: debouncedSearch || undefined, status: status || undefined, page })
  const meta = records.data?.meta

  useEffect(() => {
    const timer = window.setTimeout(() => setDebouncedSearch(search), 300)
    return () => window.clearTimeout(timer)
  }, [search])

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
                <p className="text-xs uppercase tracking-wide text-muted">All-time subscriptions</p>
                <p className="mt-1 text-2xl font-bold tabular-nums text-foreground">{data.total.toLocaleString()}</p>
              </CardBody>
            </Card>
            <Card>
              <CardBody className="py-4">
                <p className="text-xs uppercase tracking-wide text-muted">Active now</p>
                <p className="mt-1 text-2xl font-bold tabular-nums text-leaf-600">{data.active.toLocaleString()}</p>
              </CardBody>
            </Card>
          </div>

          <Card>
            <CardBody className="flex flex-wrap items-center gap-2">
              <span className="mr-1 text-xs font-semibold uppercase tracking-wide text-muted">Current status mix</span>
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

      <section className="flex flex-col gap-4" aria-labelledby="subscriber-details-heading">
        <div>
          <h2 id="subscriber-details-heading" className="font-display text-xl font-bold text-foreground">Subscriber details</h2>
          <p className="mt-1 text-sm text-muted">Most recent subscription per account, across all dates. Search and status filters apply to this table.</p>
        </div>
        {records.isError && <Alert variant="danger">Couldn’t load subscriber details.</Alert>}
        <DataTable
          columns={columns}
          rows={records.data?.data ?? []}
          getRowId={(row) => row.id}
          isLoading={records.isLoading}
          empty="No subscribers match your search or status filter."
          toolbar={
            <AdminToolbar search={search} onSearch={(value) => { setSearch(value); setPage(1) }} searchPlaceholder="Search name, email, or phone…">
              <FilterSelect label="Subscription status" value={status} onChange={(value) => { setStatus(value); setPage(1) }} options={[
                { label: 'Active', value: 'active' },
                { label: 'Pending', value: 'pending' },
                { label: 'Grace', value: 'grace' },
                { label: 'Cancelled', value: 'cancelled' },
                { label: 'Expired', value: 'expired' },
              ]} allLabel="All statuses" />
            </AdminToolbar>
          }
        />
        {meta && meta.total > 0 && (
          <div className="flex flex-wrap items-center justify-between gap-3 text-sm text-muted">
            <span>Page {meta.current_page} of {meta.last_page} · {meta.total.toLocaleString()} subscribers</span>
            <div className="flex gap-2">
              <Button size="sm" variant="ghost" disabled={meta.current_page <= 1 || records.isFetching} onClick={() => setPage((current) => current - 1)}>Previous</Button>
              <Button size="sm" variant="ghost" disabled={meta.current_page >= meta.last_page || records.isFetching} onClick={() => setPage((current) => current + 1)}>Next</Button>
            </div>
          </div>
        )}
      </section>
    </div>
  )
}
