import { useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { AdminPageHeader, AdminToolbar, DataTable, FilterSelect, type Column } from '@/components/admin'
import { Alert, Badge, Button } from '@/components/ui'
import { type AdminUserRow, type AdminUsersQuery, type Role } from '@/lib/api'
import { useAdminUsers } from '@/lib/admin/queries'

const ROLES: Role[] = ['super_admin', 'content_owner', 'school_admin', 'teacher', 'supervisor', 'parent', 'student']

function useDebounced<T>(value: T, ms = 300): T {
  const [debounced, setDebounced] = useState(value)
  useEffect(() => {
    const id = setTimeout(() => setDebounced(value), ms)
    return () => clearTimeout(id)
  }, [value, ms])
  return debounced
}

export function UsersPage() {
  const navigate = useNavigate()
  const [search, setSearch] = useState('')
  const [role, setRole] = useState('')
  const [status, setStatus] = useState('')
  const [type, setType] = useState('')
  const [page, setPage] = useState(1)

  const q = useDebounced(search)
  const params: AdminUsersQuery = useMemo(
    () => ({
      q: q || undefined,
      role: role || undefined,
      status: status || undefined,
      type: (type || undefined) as AdminUsersQuery['type'],
      page,
    }),
    [q, role, status, type, page],
  )
  const { data, isLoading, isError, isFetching } = useAdminUsers(params)

  // Reset to page 1 whenever a filter changes.
  function onFilter(setter: (v: string) => void) {
    return (v: string) => {
      setter(v)
      setPage(1)
    }
  }

  const columns: Column<AdminUserRow>[] = [
    {
      key: 'name',
      header: 'User',
      render: (u) => (
        <div>
          <p className="font-semibold text-foreground">{u.name}</p>
          <p className="text-xs text-muted">{u.email}</p>
        </div>
      ),
    },
    {
      key: 'roles',
      header: 'Roles',
      render: (u) => (
        <div className="flex flex-wrap gap-1">
          {u.roles.length ? (
            u.roles.map((r) => (
              <Badge key={r} variant={r === 'super_admin' ? 'premium' : 'neutral'}>
                {r}
              </Badge>
            ))
          ) : (
            <span className="text-muted">—</span>
          )}
        </div>
      ),
    },
    {
      key: 'orgs',
      header: 'Organizations',
      hideOnMobile: true,
      render: (u) =>
        u.organizations.length ? (
          <div className="flex flex-wrap gap-1">
            {u.organizations.map((o) => (
              <Badge key={o.id} variant="neutral">
                {o.name ?? `#${o.id}`}
              </Badge>
            ))}
          </div>
        ) : (
          <span className="text-muted">—</span>
        ),
    },
    {
      key: 'status',
      header: 'Status',
      render: (u) => <Badge variant={u.status === 'active' ? 'success' : 'danger'}>{u.status}</Badge>,
    },
    {
      key: 'verified',
      header: 'Verified',
      hideOnMobile: true,
      render: (u) => (u.email_verified ? '✓' : '—'),
    },
  ]

  if (isError) return <Alert variant="danger">Couldn’t load users.</Alert>

  const meta = data?.meta

  return (
    <div className="flex flex-col gap-6">
      <AdminPageHeader title="Users" description="Everyone with an account across the platform." />

      <DataTable
        columns={columns}
        rows={data?.data ?? []}
        getRowId={(u) => u.id}
        isLoading={isLoading}
        onRowClick={(user) => navigate(`/admin/users/${user.id}`)}
        empty="No users match your filters."
        toolbar={
          <AdminToolbar search={search} onSearch={(v) => { setSearch(v); setPage(1) }} searchPlaceholder="Search name, email, phone…">
            <FilterSelect
              label="Role"
              value={role}
              onChange={onFilter(setRole)}
              options={ROLES.map((r) => ({ label: r, value: r }))}
              allLabel="All roles"
            />
            <FilterSelect
              label="Status"
              value={status}
              onChange={onFilter(setStatus)}
              options={[
                { label: 'Active', value: 'active' },
                { label: 'Suspended', value: 'suspended' },
              ]}
              allLabel="All statuses"
            />
            <FilterSelect
              label="Type"
              value={type}
              onChange={onFilter(setType)}
              options={[
                { label: 'Single', value: 'single' },
                { label: 'Family', value: 'family' },
                { label: 'Educator/School', value: 'school' },
              ]}
              allLabel="All types"
            />
          </AdminToolbar>
        }
      />

      {meta && meta.total > 0 && (
        <div className="flex items-center justify-between text-sm text-muted">
          <span>
            Page {meta.current_page} of {meta.last_page} · {meta.total} users
          </span>
          <div className="flex gap-2">
            <Button size="sm" variant="ghost" disabled={meta.current_page <= 1 || isFetching} onClick={() => setPage((p) => p - 1)}>
              Previous
            </Button>
            <Button
              size="sm"
              variant="ghost"
              disabled={meta.current_page >= meta.last_page || isFetching}
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
