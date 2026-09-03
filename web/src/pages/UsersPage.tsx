import { useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { AdminPageHeader, AdminToolbar, DataTable, FilterSelect, type Column } from '@/components/admin'
import { Alert, Badge, Button, Input, Modal } from '@/components/ui'
import { ApiError, type AdminUserRow, type AdminUsersQuery, type CreateAdminUserInput, type Role } from '@/lib/api'
import { useAdminOrganizations, useAdminUsers, useCreateAdminUser } from '@/lib/admin/queries'

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
  const [creating, setCreating] = useState(false)

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
      <AdminPageHeader
        title="Users"
        description="Everyone with an account across the platform."
        actions={<Button onClick={() => setCreating(true)}>Create user</Button>}
      />

      {creating && <CreateUserModal onClose={() => setCreating(false)} />}

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

function CreateUserModal({ onClose }: { onClose: () => void }) {
  const create = useCreateAdminUser()
  const organizations = useAdminOrganizations({ status: 'active' })
  const [form, setForm] = useState<CreateAdminUserInput>({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    username: '',
    locale: 'en',
    status: 'active',
    role: 'parent',
  })
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const needsOrganization = ['school_admin', 'teacher', 'supervisor'].includes(form.role)

  function change<K extends keyof CreateAdminUserInput>(key: K, value: CreateAdminUserInput[K]) {
    setForm((current) => ({ ...current, [key]: value }))
  }

  async function submit(event: React.FormEvent) {
    event.preventDefault()
    setErrors({})
    setFormError(null)
    try {
      await create.mutateAsync({
        ...form,
        first_name: form.first_name.trim(),
        last_name: form.last_name.trim(),
        email: form.email.trim(),
        phone: form.phone?.trim() || undefined,
        username: form.username?.trim() || undefined,
        organization_id: needsOrganization ? form.organization_id : undefined,
      })
      onClose()
    } catch (error) {
      if (error instanceof ApiError) {
        setErrors(error.fieldErrors)
        if (Object.keys(error.fieldErrors).length === 0) setFormError(error.message)
      } else setFormError('Could not create the user.')
    }
  }

  return (
    <Modal open onClose={onClose} title="Create user" description="The user receives a secure link to set their own password.">
      <form className="flex flex-col gap-4" onSubmit={submit}>
        {formError && <Alert variant="danger">{formError}</Alert>}
        <div className="grid gap-3 sm:grid-cols-2">
          <Input label="First name" value={form.first_name} error={errors.first_name} onChange={(event) => change('first_name', event.target.value)} required />
          <Input label="Last name" value={form.last_name} error={errors.last_name} onChange={(event) => change('last_name', event.target.value)} required />
        </div>
        <Input label="Email" type="email" value={form.email} error={errors.email} onChange={(event) => change('email', event.target.value)} required />
        <div className="grid gap-3 sm:grid-cols-2">
          <Input label="Phone (optional)" value={form.phone ?? ''} error={errors.phone} onChange={(event) => change('phone', event.target.value)} />
          <Input label="Username (optional)" value={form.username ?? ''} error={errors.username} onChange={(event) => change('username', event.target.value)} />
        </div>
        <label className="flex flex-col gap-1.5 text-sm font-medium text-foreground">
          Role
          <select
            className="h-11 rounded-xl border border-border bg-surface px-3"
            value={form.role}
            onChange={(event) => change('role', event.target.value as Role)}
          >
            {ROLES.map((role) => <option key={role} value={role}>{role}</option>)}
          </select>
          {errors.role && <span className="text-xs text-danger">{errors.role}</span>}
        </label>
        {needsOrganization && (
          <label className="flex flex-col gap-1.5 text-sm font-medium text-foreground">
            Organization
            <select
              className="h-11 rounded-xl border border-border bg-surface px-3"
              value={form.organization_id ?? ''}
              onChange={(event) => change('organization_id', event.target.value ? Number(event.target.value) : undefined)}
              required
            >
              <option value="">Choose an organization</option>
              {(organizations.data?.data ?? []).map((organization) => (
                <option key={organization.id} value={organization.id}>{organization.name}</option>
              ))}
            </select>
            {errors.organization_id && <span className="text-xs text-danger">{errors.organization_id}</span>}
          </label>
        )}
        <div className="flex gap-2">
          <Button type="button" variant="secondary" fullWidth onClick={onClose}>Cancel</Button>
          <Button type="submit" fullWidth loading={create.isPending}>Create and invite</Button>
        </div>
      </form>
    </Modal>
  )
}
