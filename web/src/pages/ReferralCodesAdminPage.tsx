import { useState } from 'react'
import { AdminPageHeader, AdminToolbar, DataTable, type Column } from '@/components/admin'
import { Alert, Badge, Button } from '@/components/ui'
import type { AdminReferralCodeRow } from '@/lib/api'
import { useAdminReferralCodes } from '@/lib/admin/queries'

const columns: Column<AdminReferralCodeRow>[] = [
  { key: 'sn', header: 'SN', className: 'tabular-nums w-12', render: (r) => r.sn },
  {
    key: 'code',
    header: 'Code',
    render: (r) => (
      <div>
        <p className="font-mono font-semibold text-foreground">{r.code}</p>
        <p className="text-xs text-muted">{r.owner.name ?? `#${r.owner.id}`} · {r.owner.type}</p>
      </div>
    ),
  },
  { key: 'count_activated', header: 'Activated', className: 'tabular-nums text-right', render: (r) => r.count_activated },
  { key: 'via_email', header: 'Via email', className: 'tabular-nums text-right', hideOnMobile: true, render: (r) => r.via_email },
  { key: 'via_phone', header: 'Via phone', className: 'tabular-nums text-right', hideOnMobile: true, render: (r) => r.via_phone },
  {
    key: 'active',
    header: 'Active',
    className: 'text-right',
    render: (r) => <Badge variant="success">{r.active_count}</Badge>,
  },
  {
    key: 'inactive',
    header: 'Inactive',
    className: 'text-right',
    render: (r) => <Badge variant="neutral">{r.inactive_count}</Badge>,
  },
]

export function ReferralCodesAdminPage() {
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const { data, isLoading, isError } = useAdminReferralCodes({ search: search || undefined, page })

  if (isError) return <Alert variant="danger">Couldn’t load referral codes.</Alert>

  const meta = data?.meta

  return (
    <div className="flex flex-col gap-6">
      <AdminPageHeader
        title="Referral codes"
        description="Every referral code, how many people activated it, the email / phone split, and how many of those referred people are currently active."
      />

      <DataTable
        columns={columns}
        rows={data?.data ?? []}
        getRowId={(r) => r.id}
        isLoading={isLoading}
        empty="No referral codes yet."
        toolbar={
          <AdminToolbar
            search={search}
            onSearch={(v) => {
              setSearch(v)
              setPage(1)
            }}
            searchPlaceholder="Search by code or owner…"
          />
        }
      />

      {meta && meta.last_page > 1 && (
        <div className="flex items-center justify-between text-sm text-muted">
          <span>
            Page {meta.current_page} of {meta.last_page} · {meta.total} codes
          </span>
          <div className="flex gap-2">
            <Button size="sm" variant="outline" disabled={meta.current_page <= 1} onClick={() => setPage((p) => p - 1)}>
              Previous
            </Button>
            <Button
              size="sm"
              variant="outline"
              disabled={meta.current_page >= meta.last_page}
              onClick={() => setPage((p) => p + 1)}
            >
              Next
            </Button>
          </div>
        </div>
      )}
    </div>
  )
}
