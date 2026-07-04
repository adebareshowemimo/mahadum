import { useEffect, useMemo, useState } from 'react'
import { AdminPageHeader, AdminToolbar, DataTable, FilterSelect, type Column } from '@/components/admin'
import { Alert, Badge, Button, Modal } from '@/components/ui'
import type { AuditLogQuery, AuditLogRow } from '@/lib/api'
import { useAuditLogs } from '@/lib/admin/queries'

function useDebounced<T>(value: T, ms = 300): T {
  const [v, setV] = useState(value)
  useEffect(() => {
    const id = setTimeout(() => setV(value), ms)
    return () => clearTimeout(id)
  }, [value, ms])
  return v
}

export function AuditLogPage() {
  const [search, setSearch] = useState('')
  const [action, setAction] = useState('')
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [page, setPage] = useState(1)
  const [selected, setSelected] = useState<AuditLogRow | null>(null)

  const q = useDebounced(search)
  const params: AuditLogQuery = useMemo(
    () => ({ q: q || undefined, action: action || undefined, from: from || undefined, to: to || undefined, page }),
    [q, action, from, to, page],
  )
  const { data, isLoading, isError, isFetching } = useAuditLogs(params)

  const columns: Column<AuditLogRow>[] = [
    {
      key: 'time',
      header: 'When',
      hideOnMobile: true,
      render: (l) => (l.created_at ? new Date(l.created_at).toLocaleString() : '—'),
    },
    { key: 'action', header: 'Action', render: (l) => <Badge variant="neutral">{l.action}</Badge> },
    { key: 'actor', header: 'Actor', render: (l) => l.actor?.name ?? <span className="text-muted">system</span> },
    {
      key: 'subject',
      header: 'Subject',
      hideOnMobile: true,
      render: (l) => (l.subject ? `${l.subject.type} #${l.subject.id}` : '—'),
    },
    { key: 'ip', header: 'IP', hideOnMobile: true, className: 'text-muted', render: (l) => l.ip ?? '—' },
  ]

  if (isError) return <Alert variant="danger">Couldn’t load the audit log.</Alert>

  const meta = data?.meta

  return (
    <div className="flex flex-col gap-6">
      <AdminPageHeader title="Audit log" description="Every sensitive action, who did it, and what changed." />

      <DataTable
        columns={columns}
        rows={data?.data ?? []}
        getRowId={(l) => l.id}
        isLoading={isLoading}
        onRowClick={setSelected}
        empty="No audit entries match your filters."
        toolbar={
          <div className="flex flex-col gap-3">
            <AdminToolbar search={search} onSearch={(v) => { setSearch(v); setPage(1) }} searchPlaceholder="Search actor, action, IP…">
              <FilterSelect
                label="Action"
                value={action}
                onChange={(v) => { setAction(v); setPage(1) }}
                options={(data?.actions ?? []).map((a) => ({ label: a, value: a }))}
                allLabel="All actions"
              />
            </AdminToolbar>
            <div className="flex flex-wrap items-end gap-3 text-sm">
              <label className="flex flex-col gap-1">
                <span className="font-semibold text-muted">From</span>
                <input type="date" value={from} onChange={(e) => { setFrom(e.target.value); setPage(1) }} className="h-11 rounded-xl border border-border-strong bg-surface px-3 text-foreground focus:outline-none focus:ring-2 focus:ring-ring" />
              </label>
              <label className="flex flex-col gap-1">
                <span className="font-semibold text-muted">To</span>
                <input type="date" value={to} onChange={(e) => { setTo(e.target.value); setPage(1) }} className="h-11 rounded-xl border border-border-strong bg-surface px-3 text-foreground focus:outline-none focus:ring-2 focus:ring-ring" />
              </label>
            </div>
          </div>
        }
      />

      {meta && meta.total > 0 && (
        <div className="flex items-center justify-between text-sm text-muted">
          <span>
            Page {meta.current_page} of {meta.last_page} · {meta.total} entries
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

      {selected && <AuditDetailModal log={selected} onClose={() => setSelected(null)} />}
    </div>
  )
}

function AuditDetailModal({ log, onClose }: { log: AuditLogRow; onClose: () => void }) {
  return (
    <Modal open onClose={onClose} title={log.action} description={log.created_at ? new Date(log.created_at).toLocaleString() : undefined}>
      <div className="flex flex-col gap-4 text-sm">
        <div className="grid grid-cols-2 gap-3">
          <Field label="Actor" value={log.actor ? `${log.actor.name} (${log.actor.email})` : 'system'} />
          <Field label="IP" value={log.ip ?? '—'} />
          <Field label="Subject" value={log.subject ? `${log.subject.type} #${log.subject.id}` : '—'} />
        </div>
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <JsonBlock label="Before" value={log.before} />
          <JsonBlock label="After" value={log.after} />
        </div>
      </div>
    </Modal>
  )
}

function Field({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <p className="text-xs uppercase tracking-wide text-muted">{label}</p>
      <p className="break-words text-foreground">{value}</p>
    </div>
  )
}

function JsonBlock({ label, value }: { label: string; value: Record<string, unknown> | null }) {
  return (
    <div>
      <p className="mb-1 text-xs uppercase tracking-wide text-muted">{label}</p>
      <pre className="max-h-48 overflow-auto rounded-lg bg-surface-muted p-3 text-xs text-foreground">
        {value ? JSON.stringify(value, null, 2) : '—'}
      </pre>
    </div>
  )
}
