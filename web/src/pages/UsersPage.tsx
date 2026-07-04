import { useEffect, useMemo, useState } from 'react'
import { AdminPageHeader, AdminToolbar, DataTable, FilterSelect, type Column } from '@/components/admin'
import { Alert, Badge, Button, Modal } from '@/components/ui'
import { ApiError, type AdminUserRow, type AdminUsersQuery, type Role, type UserStatus } from '@/lib/api'
import { useAdminUsers, useAssignUserRole, useSetUserStatus } from '@/lib/admin/queries'

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
  const [search, setSearch] = useState('')
  const [role, setRole] = useState('')
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const [selected, setSelected] = useState<AdminUserRow | null>(null)

  const q = useDebounced(search)
  const params: AdminUsersQuery = useMemo(
    () => ({ q: q || undefined, role: role || undefined, status: status || undefined, page }),
    [q, role, status, page],
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
        onRowClick={setSelected}
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

      {selected && <UserModal user={selected} onClose={() => setSelected(null)} />}
    </div>
  )
}

function UserModal({ user, onClose }: { user: AdminUserRow; onClose: () => void }) {
  const assign = useAssignUserRole()
  const setStatus = useSetUserStatus()
  const [error, setError] = useState<string | null>(null)
  // Local echo of the roles so the toggles reflect changes immediately.
  const [roles, setRoles] = useState<Role[]>(user.roles)
  const [status, setStatusLocal] = useState(user.status)

  async function toggleRole(role: Role) {
    setError(null)
    const action = roles.includes(role) ? 'revoke' : 'assign'
    try {
      const updated = await assign.mutateAsync({ userId: user.id, input: { role, action } })
      setRoles(updated.roles)
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not update role.')
    }
  }

  async function changeStatus(next: UserStatus) {
    setError(null)
    try {
      const updated = await setStatus.mutateAsync({ userId: user.id, status: next })
      setStatusLocal(updated.status)
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not update status.')
    }
  }

  return (
    <Modal open onClose={onClose} title={user.name} description={user.email}>
      <div className="flex flex-col gap-5">
        {error && <Alert variant="danger">{error}</Alert>}

        <div className="flex flex-wrap items-center gap-3 text-sm">
          <Badge variant={status === 'active' ? 'success' : 'danger'}>{status}</Badge>
          {user.phone && <span className="text-muted">{user.phone}</span>}
          {user.email_verified && <span className="text-muted">· verified</span>}
          {user.created_at && <span className="text-muted">· joined {new Date(user.created_at).toLocaleDateString()}</span>}
          <span className="text-muted">
            · {user.last_login_at ? `last seen ${new Date(user.last_login_at).toLocaleDateString()}` : 'never signed in'}
          </span>
        </div>

        <div>
          <p className="mb-2 text-sm font-semibold text-foreground">Organizations</p>
          {user.organizations.length ? (
            <ul className="flex flex-col gap-1.5">
              {user.organizations.map((o) => (
                <li key={o.id} className="flex items-center gap-2 text-sm">
                  <span className="font-medium text-foreground">{o.name ?? `Organization #${o.id}`}</span>
                  <Badge variant="neutral">{o.role}</Badge>
                  <Badge variant={o.status === 'active' ? 'success' : 'neutral'}>{o.status}</Badge>
                </li>
              ))}
            </ul>
          ) : (
            <p className="text-sm text-muted">Not a member of any organization (direct consumer).</p>
          )}
        </div>

        <div>
          <p className="mb-2 text-sm font-semibold text-foreground">Roles</p>
          <div className="flex flex-wrap gap-2">
            {ROLES.map((r) => {
              const on = roles.includes(r)
              return (
                <button
                  key={r}
                  type="button"
                  disabled={assign.isPending}
                  onClick={() => toggleRole(r)}
                  className={
                    on
                      ? 'rounded-full border border-primary bg-primary/10 px-3 py-1 text-xs font-bold text-primary'
                      : 'rounded-full border border-border-strong px-3 py-1 text-xs font-semibold text-muted hover:bg-surface-muted'
                  }
                >
                  {on ? '✓ ' : '+ '}
                  {r}
                </button>
              )
            })}
          </div>
          <p className="mt-2 text-xs text-muted">Click a role to grant or revoke it. Changes are audited.</p>
        </div>

        <div className="flex justify-end gap-2 border-t border-border pt-4">
          {status === 'active' ? (
            <Button variant="ghost" loading={setStatus.isPending} onClick={() => changeStatus('suspended')}>
              Suspend account
            </Button>
          ) : (
            <Button variant="parent" loading={setStatus.isPending} onClick={() => changeStatus('active')}>
              Reactivate account
            </Button>
          )}
          <Button variant="ghost" onClick={onClose}>
            Done
          </Button>
        </div>
      </div>
    </Modal>
  )
}
