import { useState } from 'react'
import { AdminPageHeader, DataTable, FilterSelect, type Column } from '@/components/admin'
import { Alert, Badge, Button } from '@/components/ui'
import { ApiError, type AdminPayout } from '@/lib/api'
import { formatMoney } from '@/lib/format'
import { useAdminPayouts, useApprovePayout, useRejectPayout } from '@/lib/admin/queries'

const STATUS_TONE: Record<string, 'gold' | 'success' | 'primary' | 'danger' | 'neutral'> = {
  requested: 'gold',
  approved: 'primary',
  paid: 'success',
  rejected: 'danger',
}

export function PayoutsPage() {
  const [status, setStatus] = useState('requested')
  const [page, setPage] = useState(1)
  const [error, setError] = useState<string | null>(null)
  const [actingId, setActingId] = useState<number | null>(null)
  const { data, isLoading, isError, isFetching } = useAdminPayouts({ status: status || undefined, page })
  const approve = useApprovePayout()
  const reject = useRejectPayout()

  async function onReview(payout: AdminPayout, action: 'approve' | 'reject') {
    setError(null)
    setActingId(payout.id)
    try {
      await (action === 'approve' ? approve : reject).mutateAsync(payout.id)
    } catch (err) {
      // Surface the SoD 403 ("cannot approve a payout you would receive") and the
      // 409 ("only a requested payout can be approved/rejected") verbatim.
      setError(err instanceof ApiError ? err.message : `Could not ${action} the payout.`)
    } finally {
      setActingId(null)
    }
  }

  const columns: Column<AdminPayout>[] = [
    {
      key: 'beneficiary',
      header: 'Beneficiary',
      render: (p) => (
        <div>
          <p className="font-semibold text-foreground">{p.beneficiary.name ?? `#${p.beneficiary.id}`}</p>
          <p className="text-xs capitalize text-muted">{p.beneficiary.type}</p>
        </div>
      ),
    },
    {
      key: 'amount',
      header: 'Amount',
      className: 'tabular-nums font-semibold',
      render: (p) => formatMoney(p.amount_minor, 'NGN'),
    },
    { key: 'method', header: 'Method', render: (p) => <span className="capitalize">{p.method}</span> },
    {
      key: 'source',
      header: 'Source',
      render: (p) => <Badge variant={p.source === 'teaching' ? 'info' : 'primary'}>{p.source === 'teaching' ? 'Teaching' : 'Referral'}</Badge>,
    },
    {
      key: 'requested',
      header: 'Requested',
      hideOnMobile: true,
      render: (p) => (p.requested_at ? new Date(p.requested_at).toLocaleDateString() : '—'),
    },
    {
      key: 'status',
      header: 'Status',
      render: (p) => <Badge variant={STATUS_TONE[p.status] ?? 'neutral'}>{p.status}</Badge>,
    },
    {
      key: 'action',
      header: '',
      className: 'text-right',
      render: (p) =>
        p.status === 'requested' ? (
          <div className="flex justify-end gap-2">
            <Button size="sm" variant="ghost" loading={actingId === p.id && reject.isPending} onClick={() => onReview(p, 'reject')}>
              Reject
            </Button>
            <Button size="sm" variant="parent" loading={actingId === p.id && approve.isPending} onClick={() => onReview(p, 'approve')}>
              Approve
            </Button>
          </div>
        ) : null,
    },
  ]

  if (isError) return <Alert variant="danger">Couldn’t load payouts.</Alert>

  return (
    <div className="flex flex-col gap-6">
      <AdminPageHeader
        title="Payouts"
        description="Approve referral & teacher payouts. Floor ₦5,000 · cap ₦50,000/mo · an approver can never be the beneficiary."
      />

      {error && <Alert variant="danger">{error}</Alert>}

      <DataTable
        columns={columns}
        rows={data?.data ?? []}
        getRowId={(p) => p.id}
        isLoading={isLoading}
        empty={status === 'requested' ? 'No payouts awaiting approval. 🎉' : 'No payouts with this status.'}
        toolbar={
          <div className="flex flex-wrap items-center gap-3">
            <FilterSelect
              label="Status"
              value={status}
              onChange={(v) => { setStatus(v); setPage(1) }}
              allLabel="All"
              options={[
                { label: 'Awaiting approval', value: 'requested' },
                { label: 'Approved', value: 'approved' },
                { label: 'Paid', value: 'paid' },
                { label: 'Rejected', value: 'rejected' },
              ]}
            />
          </div>
        }
      />

      {data?.meta && data.meta.total > 0 && (
        <div className="flex items-center justify-between text-sm text-muted">
          <span>
            Page {data.meta.current_page} of {data.meta.last_page} · {data.meta.total.toLocaleString()} payouts
          </span>
          <div className="flex gap-2">
            <Button size="sm" variant="ghost" disabled={data.meta.current_page <= 1 || isFetching} onClick={() => setPage((p) => p - 1)}>
              Previous
            </Button>
            <Button size="sm" variant="ghost" disabled={data.meta.current_page >= data.meta.last_page || isFetching} onClick={() => setPage((p) => p + 1)}>
              Next
            </Button>
          </div>
        </div>
      )}
    </div>
  )
}
