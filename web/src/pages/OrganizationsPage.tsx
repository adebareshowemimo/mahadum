import { useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { AdminPageHeader, AdminToolbar, DataTable, FilterSelect, type Column } from '@/components/admin'
import { Alert, Badge, Button, Icon } from '@/components/ui'
import type { AdminOrg, AdminOrgQuery } from '@/lib/api'
import { useAdminOrganizations } from '@/lib/admin/queries'

const TONE: Record<string, 'success' | 'gold' | 'danger' | 'neutral'> = {
  active: 'success',
  pending: 'gold',
  suspended: 'danger',
  inactive: 'neutral',
}

function useDebounced<T>(value: T, ms = 300): T {
  const [v, setV] = useState(value)
  useEffect(() => {
    const id = setTimeout(() => setV(value), ms)
    return () => clearTimeout(id)
  }, [value, ms])
  return v
}

export function OrganizationsPage() {
  const navigate = useNavigate()
  const [search, setSearch] = useState('')
  const [type, setType] = useState('')
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)

  const q = useDebounced(search)
  const params: AdminOrgQuery = useMemo(
    () => ({ q: q || undefined, type: type || undefined, status: status || undefined, page }),
    [q, type, status, page],
  )
  const { data, isLoading, isError, isFetching } = useAdminOrganizations(params)

  const typeOptions = (data?.types ?? []).map((t) => ({ label: t, value: t }))

  const columns: Column<AdminOrg>[] = [
    {
      key: 'name',
      header: 'Organization',
      render: (o) => (
        <div>
          <p className="font-semibold text-foreground">{o.name}</p>
          <p className="text-xs capitalize text-muted">{o.type}</p>
        </div>
      ),
    },
    { key: 'members', header: 'Members', className: 'tabular-nums', hideOnMobile: true, render: (o) => o.members },
    { key: 'classes', header: 'Classes', className: 'tabular-nums', hideOnMobile: true, render: (o) => o.classes },
    {
      key: 'status',
      header: 'Status',
      render: (o) => <Badge variant={TONE[o.status] ?? 'neutral'}>{o.status}</Badge>,
    },
    {
      key: 'go',
      header: '',
      className: 'w-8 text-right text-muted',
      render: () => <Icon name="chevron" className="ml-auto size-4 -rotate-90" />,
    },
  ]

  if (isError) return <Alert variant="danger">Couldn’t load organizations.</Alert>

  const meta = data?.meta

  return (
    <div className="flex flex-col gap-6">
      <AdminPageHeader
        title="Organizations"
        description="Every school and partner on the platform."
        actions={
          <Button variant="parent" onClick={() => navigate('/admin/orgs/new')}>
            <Icon name="plus" className="size-4" /> New organization
          </Button>
        }
      />

      <DataTable
        columns={columns}
        rows={data?.data ?? []}
        getRowId={(o) => o.id}
        isLoading={isLoading}
        onRowClick={(o) => navigate(`/admin/orgs/${o.id}`)}
        empty="No organizations match your filters."
        toolbar={
          <AdminToolbar search={search} onSearch={(v) => { setSearch(v); setPage(1) }} searchPlaceholder="Search organizations…">
            <FilterSelect label="Type" value={type} onChange={(v) => { setType(v); setPage(1) }} options={typeOptions} allLabel="All types" />
            <FilterSelect
              label="Status"
              value={status}
              onChange={(v) => { setStatus(v); setPage(1) }}
              options={[
                { label: 'Active', value: 'active' },
                { label: 'Pending', value: 'pending' },
                { label: 'Suspended', value: 'suspended' },
                { label: 'Inactive', value: 'inactive' },
              ]}
              allLabel="All statuses"
            />
          </AdminToolbar>
        }
      />

      {meta && meta.total > 0 && (
        <div className="flex items-center justify-between text-sm text-muted">
          <span>
            Page {meta.current_page} of {meta.last_page} · {meta.total.toLocaleString()} organizations
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
