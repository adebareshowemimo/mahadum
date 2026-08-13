import { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { AdminPageHeader, AdminToolbar, DataTable, FilterSelect, type Column } from '@/components/admin'
import { Alert, Badge, Button, Icon } from '@/components/ui'
import type { AdminAdvertPlacementQuery, AdvertPlacement } from '@/lib/api'
import { useAdminAdvertPlacements } from '@/lib/admin/queries'

export function AdvertsPage() {
  const navigate = useNavigate()
  const [search, setSearch] = useState('')
  const [position, setPosition] = useState('')
  const [active, setActive] = useState('')
  const [page, setPage] = useState(1)

  const params: AdminAdvertPlacementQuery = useMemo(
    () => ({
      position: (position || undefined) as AdminAdvertPlacementQuery['position'],
      is_active: active === '' ? undefined : active === 'true',
      page,
    }),
    [position, active, page],
  )
  const { data, isLoading, isError, isFetching } = useAdminAdvertPlacements(params)
  const rows = (data?.data ?? []).filter((a) => a.name.toLowerCase().includes(search.trim().toLowerCase()))

  const columns: Column<AdvertPlacement>[] = [
    {
      key: 'name',
      header: 'Advert',
      render: (a) => (
        <div className="flex items-center gap-3">
          <img src={a.image_url} alt="" className="h-10 w-16 rounded-lg object-cover" />
          <div>
            <p className="font-semibold text-foreground">{a.name}</p>
            <p className="text-xs capitalize text-muted">{a.position}{a.size ? ` · ${a.size}` : ''}</p>
          </div>
        </div>
      ),
    },
    { key: 'impressions', header: 'Impressions', className: 'tabular-nums', hideOnMobile: true, render: (a) => a.impressions_count.toLocaleString() },
    { key: 'clicks', header: 'Clicks', className: 'tabular-nums', hideOnMobile: true, render: (a) => a.clicks_count.toLocaleString() },
    {
      key: 'status',
      header: 'Status',
      render: (a) => <Badge variant={a.is_active ? 'success' : 'neutral'}>{a.is_active ? 'Active' : 'Inactive'}</Badge>,
    },
    {
      key: 'go',
      header: '',
      className: 'w-8 text-right text-muted',
      render: () => <Icon name="chevron" className="ml-auto size-4 -rotate-90" />,
    },
  ]

  if (isError) return <Alert variant="danger">Couldn’t load adverts.</Alert>

  const meta = data?.meta

  return (
    <div className="flex flex-col gap-6">
      <AdminPageHeader
        title="Adverts"
        description="Banner placements shown to logged-out visitors and free-tier users."
        actions={
          <Button variant="parent" onClick={() => navigate('/admin/adverts/new')}>
            <Icon name="plus" className="size-4" /> New advert
          </Button>
        }
      />

      <DataTable
        columns={columns}
        rows={rows}
        getRowId={(a) => a.id}
        isLoading={isLoading}
        onRowClick={(a) => navigate(`/admin/adverts/${a.id}`)}
        empty="No adverts match your filters."
        toolbar={
          <AdminToolbar search={search} onSearch={setSearch} searchPlaceholder="Search adverts…">
            <FilterSelect
              label="Position"
              value={position}
              onChange={(v) => { setPosition(v); setPage(1) }}
              options={[
                { label: 'Leaderboard', value: 'leaderboard' },
                { label: 'Inline', value: 'inline' },
              ]}
              allLabel="All positions"
            />
            <FilterSelect
              label="Status"
              value={active}
              onChange={(v) => { setActive(v); setPage(1) }}
              options={[
                { label: 'Active', value: 'true' },
                { label: 'Inactive', value: 'false' },
              ]}
              allLabel="All statuses"
            />
          </AdminToolbar>
        }
      />

      {meta && meta.total > 0 && (
        <div className="flex items-center justify-between text-sm text-muted">
          <span>
            Page {meta.current_page} of {meta.last_page} · {meta.total.toLocaleString()} adverts
          </span>
          <div className="flex gap-2">
            <Button size="sm" variant="ghost" disabled={meta.current_page <= 1 || isFetching} onClick={() => setPage((p) => p - 1)}>
              Previous
            </Button>
            <Button size="sm" variant="ghost" disabled={meta.current_page >= meta.last_page || isFetching} onClick={() => setPage((p) => p + 1)}>
              Next
            </Button>
          </div>
        </div>
      )}
    </div>
  )
}
