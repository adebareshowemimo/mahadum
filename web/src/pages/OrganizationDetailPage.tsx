import { useEffect, useState, type FormEvent } from 'react'
import { useParams, useSearchParams } from 'react-router-dom'
import { AdminPageHeader, DataTable, type Column } from '@/components/admin'
import { Alert, Badge, Button, Card, CardBody, Input, Modal, Skeleton } from '@/components/ui'
import {
  ApiError,
  type AdminOrgAuditEntry,
  type AdminOrgClass,
  type AdminOrgDetail,
  type AdminOrgInvoice,
  type AdminOrgMember,
  type AdminOrgReferral,
  type InviteOrgAdminInput,
  type OrgStatus,
  type UpdateOrgInput,
} from '@/lib/api'
import { formatMoney } from '@/lib/format'
import { cn } from '@/lib/cn'
import { useAdminOrganization, useInviteOrgAdmin, useSetOrgStatus, useUpdateOrg } from '@/lib/admin/queries'

const TONE: Record<string, 'success' | 'gold' | 'danger' | 'neutral'> = {
  active: 'success',
  pending: 'gold',
  suspended: 'danger',
  inactive: 'neutral',
}
const INVOICE_TONE: Record<string, 'success' | 'gold' | 'danger' | 'neutral'> = {
  paid: 'success',
  unpaid: 'gold',
  overdue: 'danger',
}

const TABS = ['overview', 'members', 'classes', 'invoices', 'referrals', 'audit'] as const
type Tab = (typeof TABS)[number]
const TAB_LABELS: Record<Tab, string> = {
  overview: 'Overview',
  members: 'Members',
  classes: 'Classes',
  invoices: 'Invoices',
  referrals: 'Referrals',
  audit: 'Audit',
}

function Stat({ label, value }: { label: string; value: number | string }) {
  return (
    <Card>
      <CardBody className="py-4">
        <p className="text-xs uppercase tracking-wide text-muted">{label}</p>
        <p className="mt-1 text-2xl font-bold tabular-nums text-foreground">{value}</p>
      </CardBody>
    </Card>
  )
}

function fmtDate(iso: string | null) {
  return iso ? new Date(iso).toLocaleDateString() : '—'
}

export function OrganizationDetailPage() {
  const { orgId } = useParams()
  const id = Number(orgId)
  const { data, isLoading, isError } = useAdminOrganization(id)
  const setStatus = useSetOrgStatus()
  const [error, setError] = useState<string | null>(null)
  const [editing, setEditing] = useState(false)
  const [inviting, setInviting] = useState(false)

  const [searchParams, setSearchParams] = useSearchParams()
  const tabParam = searchParams.get('tab')
  const tab: Tab = (TABS as readonly string[]).includes(tabParam ?? '') ? (tabParam as Tab) : 'overview'
  function selectTab(next: Tab) {
    setSearchParams((prev) => {
      const p = new URLSearchParams(prev)
      if (next === 'overview') p.delete('tab')
      else p.set('tab', next)
      return p
    })
  }

  async function onStatus(status: OrgStatus) {
    setError(null)
    try {
      await setStatus.mutateAsync({ orgId: id, status })
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not update status.')
    }
  }

  if (isLoading) return <Skeleton className="h-96" />
  if (isError || !data) return <Alert variant="danger">Couldn’t load this organization.</Alert>

  const count: Record<Tab, number | null> = {
    overview: null,
    members: data.members.length,
    classes: data.classes.length,
    invoices: data.invoices.length,
    referrals: data.referrals.length,
    audit: data.audit.length,
  }

  return (
    <div className="flex flex-col gap-6">
      <AdminPageHeader
        title={data.name}
        description={`${data.type} · ${data.slug}`}
        backTo="/admin/orgs"
        backLabel="Organizations"
        actions={
          <div className="flex flex-wrap items-center gap-2">
            <Badge variant={TONE[data.status] ?? 'neutral'}>{data.status}</Badge>
            <Button variant="ghost" onClick={() => setInviting(true)}>
              Invite admin
            </Button>
            <Button variant="ghost" onClick={() => setEditing(true)}>
              Edit
            </Button>
            {data.status !== 'active' ? (
              <Button variant="parent" loading={setStatus.isPending} onClick={() => onStatus('active')}>
                Activate
              </Button>
            ) : (
              <Button variant="ghost" loading={setStatus.isPending} onClick={() => onStatus('suspended')}>
                Suspend
              </Button>
            )}
          </div>
        }
      />

      {error && <Alert variant="danger">{error}</Alert>}

      <TabBar tab={tab} counts={count} onSelect={selectTab} />

      {tab === 'overview' && (
        <div className="flex flex-col gap-6">
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <Stat label="Members" value={data.counts.members} />
            <Stat label="Classes" value={data.counts.classes} />
            <Stat label="Families" value={data.counts.families} />
            <Stat label="Learners" value={data.counts.learners} />
          </div>
          <ProfileCard org={data} />
        </div>
      )}

      {tab === 'members' && <MembersTable members={data.members} />}
      {tab === 'classes' && <ClassesTable classes={data.classes} />}
      {tab === 'invoices' && <InvoicesTable invoices={data.invoices} />}
      {tab === 'referrals' && <ReferralsTable referrals={data.referrals} />}
      {tab === 'audit' && <AuditTable entries={data.audit} />}

      {editing && <EditOrgModal org={data} onClose={() => setEditing(false)} />}
      {inviting && <InviteAdminModal org={data} onClose={() => setInviting(false)} />}
    </div>
  )
}

function TabBar({ tab, counts, onSelect }: { tab: Tab; counts: Record<Tab, number | null>; onSelect: (t: Tab) => void }) {
  return (
    <div role="tablist" aria-label="Organization sections" className="flex flex-wrap gap-1 border-b border-border">
      {TABS.map((t) => {
        const active = t === tab
        return (
          <button
            key={t}
            role="tab"
            type="button"
            aria-selected={active}
            onClick={() => onSelect(t)}
            className={cn(
              'relative -mb-px rounded-t-lg px-3.5 py-2 text-sm font-semibold transition-colors',
              active
                ? 'border-b-2 border-primary text-foreground'
                : 'text-muted hover:text-foreground',
            )}
          >
            {TAB_LABELS[t]}
            {counts[t] !== null && <span className="ml-1.5 text-xs text-muted">{counts[t]}</span>}
          </button>
        )
      })}
    </div>
  )
}

function ProfileCard({ org }: { org: AdminOrgDetail }) {
  const rows: [string, string | null][] = [
    ['Contact email', org.contact_email],
    ['Domain', org.domain],
    ['Domain verified', org.domain_verified_at ? new Date(org.domain_verified_at).toLocaleDateString() : '—'],
    ['CAC number', org.cac_number],
    ['Address', org.address],
    ['Seats', `${org.seats.filled} / ${org.seats.purchased} filled`],
  ]
  return (
    <Card>
      <CardBody>
        <dl className="grid gap-x-8 gap-y-3 sm:grid-cols-2">
          {rows.map(([label, value]) => (
            <div key={label} className="flex flex-col">
              <dt className="text-xs uppercase tracking-wide text-muted">{label}</dt>
              <dd className="text-sm text-foreground">{value || '—'}</dd>
            </div>
          ))}
        </dl>
      </CardBody>
    </Card>
  )
}

function MembersTable({ members }: { members: AdminOrgMember[] }) {
  const columns: Column<AdminOrgMember>[] = [
    {
      key: 'name',
      header: 'Name',
      render: (m) => (
        <div>
          <p className="font-semibold text-foreground">{m.name}</p>
          <p className="text-xs text-muted">{m.email}</p>
        </div>
      ),
    },
    { key: 'role', header: 'Role', render: (m) => <span className="capitalize">{m.role}</span> },
    {
      key: 'status',
      header: 'Status',
      render: (m) => <Badge variant={m.status === 'active' ? 'success' : 'neutral'}>{m.status}</Badge>,
    },
  ]
  return <DataTable columns={columns} rows={members} getRowId={(m) => m.id} empty="No members yet." />
}

function ClassesTable({ classes }: { classes: AdminOrgClass[] }) {
  const columns: Column<AdminOrgClass>[] = [
    { key: 'name', header: 'Class', render: (c) => <span className="font-semibold text-foreground">{c.name}</span> },
    { key: 'level', header: 'Level', render: (c) => c.level ?? '—' },
    { key: 'students', header: 'Students', className: 'tabular-nums', render: (c) => c.students },
  ]
  return <DataTable columns={columns} rows={classes} getRowId={(c) => c.id} empty="No classes yet." />
}

function InvoicesTable({ invoices }: { invoices: AdminOrgInvoice[] }) {
  const columns: Column<AdminOrgInvoice>[] = [
    { key: 'type', header: 'Type', render: (i) => <span className="capitalize">{i.type}</span> },
    {
      key: 'amount',
      header: 'Amount',
      className: 'tabular-nums',
      render: (i) => formatMoney(i.amount_minor, 'NGN'),
    },
    { key: 'status', header: 'Status', render: (i) => <Badge variant={INVOICE_TONE[i.status] ?? 'neutral'}>{i.status}</Badge> },
    {
      key: 'issued',
      header: 'Issued',
      hideOnMobile: true,
      render: (i) => fmtDate(i.issued_at),
    },
  ]
  return <DataTable columns={columns} rows={invoices} getRowId={(i) => i.id} empty="No invoices." />
}

function ReferralsTable({ referrals }: { referrals: AdminOrgReferral[] }) {
  const columns: Column<AdminOrgReferral>[] = [
    { key: 'code', header: 'Code', render: (r) => <span className="font-mono text-foreground">{r.code}</span> },
    { key: 'kind', header: 'Kind', render: (r) => <span className="capitalize">{r.kind}</span> },
    {
      key: 'status',
      header: 'Status',
      render: (r) => (
        <Badge variant={r.status === 'active' ? 'success' : r.status === 'frozen' ? 'danger' : 'neutral'}>{r.status}</Badge>
      ),
    },
    { key: 'created', header: 'Created', hideOnMobile: true, render: (r) => fmtDate(r.created_at) },
  ]
  return <DataTable columns={columns} rows={referrals} getRowId={(r) => r.id} empty="No referral codes for this organization." />
}

function AuditTable({ entries }: { entries: AdminOrgAuditEntry[] }) {
  const columns: Column<AdminOrgAuditEntry>[] = [
    { key: 'when', header: 'When', render: (a) => (a.created_at ? new Date(a.created_at).toLocaleString() : '—') },
    { key: 'action', header: 'Action', render: (a) => <span className="font-mono text-xs">{a.action}</span> },
    { key: 'actor', header: 'Actor', render: (a) => a.actor ?? '—' },
    { key: 'ip', header: 'IP', hideOnMobile: true, render: (a) => a.ip ?? '—' },
  ]
  return <DataTable columns={columns} rows={entries} getRowId={(a) => a.id} empty="No recorded activity." />
}

function EditOrgModal({ org, onClose }: { org: AdminOrgDetail; onClose: () => void }) {
  const update = useUpdateOrg()
  const [form, setForm] = useState<UpdateOrgInput>({
    name: org.name,
    contact_email: org.contact_email ?? '',
    domain: org.domain ?? '',
    cac_number: org.cac_number ?? '',
    address: org.address ?? '',
  })
  const [error, setError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})

  // Reset if the underlying org changes while the modal is mounted.
  useEffect(() => {
    setForm({
      name: org.name,
      contact_email: org.contact_email ?? '',
      domain: org.domain ?? '',
      cac_number: org.cac_number ?? '',
      address: org.address ?? '',
    })
  }, [org])

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setError(null)
    setFieldErrors({})
    try {
      await update.mutateAsync({ orgId: org.id, input: form })
      onClose()
    } catch (err) {
      if (err instanceof ApiError) {
        setFieldErrors(err.fieldErrors)
        if (!Object.keys(err.fieldErrors).length) setError(err.message)
      } else {
        setError('Could not save changes.')
      }
    }
  }

  return (
    <Modal open onClose={onClose} title="Edit organization">
      <form onSubmit={onSubmit} className="flex flex-col gap-4">
        {error && <Alert variant="danger">{error}</Alert>}
        <Input label="Name" value={form.name ?? ''} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} error={fieldErrors.name} />
        <Input label="Contact email" type="email" value={form.contact_email ?? ''} onChange={(e) => setForm((f) => ({ ...f, contact_email: e.target.value }))} error={fieldErrors.contact_email} />
        <Input label="Domain" value={form.domain ?? ''} onChange={(e) => setForm((f) => ({ ...f, domain: e.target.value }))} error={fieldErrors.domain} />
        <Input label="CAC number" value={form.cac_number ?? ''} onChange={(e) => setForm((f) => ({ ...f, cac_number: e.target.value }))} error={fieldErrors.cac_number} />
        <Input label="Address" value={form.address ?? ''} onChange={(e) => setForm((f) => ({ ...f, address: e.target.value }))} error={fieldErrors.address} />
        <div className="flex justify-end gap-2 pt-1">
          <Button type="button" variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" variant="parent" loading={update.isPending}>
            Save changes
          </Button>
        </div>
      </form>
    </Modal>
  )
}

function InviteAdminModal({ org, onClose }: { org: AdminOrgDetail; onClose: () => void }) {
  const invite = useInviteOrgAdmin()
  const [form, setForm] = useState<InviteOrgAdminInput>({ first_name: '', last_name: '', email: '' })
  const [error, setError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})
  const [sentTo, setSentTo] = useState<string | null>(null)

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setError(null)
    setFieldErrors({})
    try {
      const admin = await invite.mutateAsync({ orgId: org.id, input: form })
      setSentTo(admin.email)
    } catch (err) {
      if (err instanceof ApiError) {
        setFieldErrors(err.fieldErrors)
        if (!Object.keys(err.fieldErrors).length) setError(err.message)
      } else {
        setError('Could not send the invite.')
      }
    }
  }

  return (
    <Modal open onClose={onClose} title="Invite school admin" description={`They’ll manage ${org.name}.`}>
      {sentTo ? (
        <div className="flex flex-col gap-4">
          <Alert variant="success">
            Invite sent to <strong>{sentTo}</strong>. They’ll receive a link to set their password and sign in as a
            school admin.
          </Alert>
          <div className="flex justify-end">
            <Button variant="parent" onClick={onClose}>
              Done
            </Button>
          </div>
        </div>
      ) : (
        <form onSubmit={onSubmit} className="flex flex-col gap-4">
          {error && <Alert variant="danger">{error}</Alert>}
          <div className="grid gap-4 sm:grid-cols-2">
            <Input label="First name" value={form.first_name} onChange={(e) => setForm((f) => ({ ...f, first_name: e.target.value }))} error={fieldErrors.first_name} />
            <Input label="Last name" value={form.last_name} onChange={(e) => setForm((f) => ({ ...f, last_name: e.target.value }))} error={fieldErrors.last_name} />
          </div>
          <Input label="Email" type="email" value={form.email} onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))} error={fieldErrors.email} />
          <p className="text-xs text-muted">
            Creates a <strong>school_admin</strong> account bound to this organization and emails a set-password link.
          </p>
          <div className="flex justify-end gap-2 pt-1">
            <Button type="button" variant="ghost" onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" variant="parent" loading={invite.isPending}>
              Send invite
            </Button>
          </div>
        </form>
      )}
    </Modal>
  )
}
