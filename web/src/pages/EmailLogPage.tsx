import { useEffect, useMemo, useState } from 'react'
import { AdminPageHeader, AdminToolbar, DataTable, FilterSelect, type Column } from '@/components/admin'
import { Alert, Badge, Button, Modal } from '@/components/ui'
import type { EmailLogQuery, EmailLogRow } from '@/lib/api'
import { useEmailLog } from '@/lib/admin/queries'

const STATUS_TONE: Record<string, 'success' | 'gold' | 'danger' | 'neutral'> = {
  sent: 'success',
  delivered: 'success',
  queued: 'gold',
  bounced: 'danger',
  complained: 'danger',
  failed: 'danger',
}

function useDebounced<T>(value: T, ms = 300): T {
  const [v, setV] = useState(value)
  useEffect(() => {
    const id = setTimeout(() => setV(value), ms)
    return () => clearTimeout(id)
  }, [value, ms])
  return v
}

export function EmailLogPage() {
  const [search, setSearch] = useState('')
  const [type, setType] = useState('')
  const [status, setStatus] = useState('')
  const [source, setSource] = useState('')
  const [page, setPage] = useState(1)
  const [selected, setSelected] = useState<EmailLogRow | null>(null)

  const q = useDebounced(search)
  const params: EmailLogQuery = useMemo(
    () => ({ q: q || undefined, type: type || undefined, status: status || undefined, source: source || undefined, page }),
    [q, type, status, source, page],
  )
  const { data, isLoading, isError, isFetching } = useEmailLog(params)

  const columns: Column<EmailLogRow>[] = [
    {
      key: 'to',
      header: 'Recipient',
      render: (l) => (
        <div>
          <p className="font-semibold text-foreground">{l.to_email}</p>
          <p className="text-xs text-muted">{l.subject ?? '—'}</p>
        </div>
      ),
    },
    { key: 'type', header: 'Type', hideOnMobile: true, render: (l) => <Badge variant={l.type === 'marketing' ? 'gold' : 'neutral'}>{l.type}</Badge> },
    { key: 'source', header: 'Source', hideOnMobile: true, render: (l) => <span className="font-mono text-xs">{l.source ?? '—'}</span> },
    { key: 'status', header: 'Status', render: (l) => <Badge variant={STATUS_TONE[l.status] ?? 'neutral'}>{l.status}</Badge> },
    { key: 'when', header: 'Sent', hideOnMobile: true, render: (l) => (l.created_at ? new Date(l.created_at).toLocaleString() : '—') },
  ]

  if (isError) return <Alert variant="danger">Couldn’t load the email log.</Alert>
  const meta = data?.meta

  return (
    <div className="flex flex-col gap-6">
      <AdminPageHeader title="Email log" description="Every outbound message — transactional and campaign." />

      <DataTable
        columns={columns}
        rows={data?.data ?? []}
        getRowId={(l) => l.id}
        isLoading={isLoading}
        onRowClick={setSelected}
        empty="No emails match your filters."
        toolbar={
          <AdminToolbar search={search} onSearch={(v) => { setSearch(v); setPage(1) }} searchPlaceholder="Search recipient…">
            <FilterSelect label="Type" value={type} onChange={(v) => { setType(v); setPage(1) }} allLabel="All"
              options={[{ label: 'Transactional', value: 'transactional' }, { label: 'Marketing', value: 'marketing' }]} />
            <FilterSelect label="Status" value={status} onChange={(v) => { setStatus(v); setPage(1) }} allLabel="All"
              options={[{ label: 'Sent', value: 'sent' }, { label: 'Bounced', value: 'bounced' }, { label: 'Failed', value: 'failed' }]} />
            <FilterSelect label="Source" value={source} onChange={(v) => { setSource(v); setPage(1) }} allLabel="All sources"
              options={(data?.sources ?? []).map((s) => ({ label: s, value: s }))} />
          </AdminToolbar>
        }
      />

      {meta && meta.total > 0 && (
        <div className="flex items-center justify-between text-sm text-muted">
          <span>Page {meta.current_page} of {meta.last_page} · {meta.total.toLocaleString()} emails</span>
          <div className="flex gap-2">
            <Button size="sm" variant="ghost" disabled={meta.current_page <= 1 || isFetching} onClick={() => setPage((p) => p - 1)}>Previous</Button>
            <Button size="sm" variant="ghost" disabled={meta.current_page >= meta.last_page || isFetching} onClick={() => setPage((p) => p + 1)}>Next</Button>
          </div>
        </div>
      )}

      {selected && (
        <Modal open onClose={() => setSelected(null)} title={selected.subject ?? 'Email'} description={selected.to_email}>
          <dl className="grid grid-cols-[7rem_1fr] gap-x-4 gap-y-2 text-sm">
            {[
              ['Recipient', selected.to_email],
              ['Subject', selected.subject ?? '—'],
              ['Type', selected.type],
              ['Source', selected.source ?? '—'],
              ['Status', selected.status],
              ['Sent', selected.sent_at ? new Date(selected.sent_at).toLocaleString() : '—'],
              ['Recorded', selected.created_at ? new Date(selected.created_at).toLocaleString() : '—'],
            ].map(([label, value]) => (
              <div key={label} className="contents">
                <dt className="text-muted">{label}</dt>
                <dd className="break-all text-foreground">{value}</dd>
              </div>
            ))}
          </dl>
        </Modal>
      )}
    </div>
  )
}
