import { useEffect, useMemo, useState } from 'react'
import { AdminPageHeader, AdminToolbar, DataTable, FilterSelect, type Column } from '@/components/admin'
import { Alert, Badge, Button, Modal } from '@/components/ui'
import { ApiError, type AdminTicketsQuery, type SupportAssignee, type SupportMessage, type SupportTicket, type UpdateTicketInput } from '@/lib/api'
import { useReplyTicket, useSupportTickets, useUpdateTicket } from '@/lib/admin/queries'

const STATUS_TONE: Record<string, 'gold' | 'info' | 'success' | 'neutral'> = {
  open: 'gold',
  in_progress: 'info',
  resolved: 'success',
}
const PRIORITY_TONE: Record<string, 'danger' | 'neutral' | 'gold'> = {
  high: 'danger',
  normal: 'neutral',
  low: 'neutral',
}

function useDebounced<T>(value: T, ms = 300): T {
  const [v, setV] = useState(value)
  useEffect(() => {
    const id = setTimeout(() => setV(value), ms)
    return () => clearTimeout(id)
  }, [value, ms])
  return v
}

export function SupportPage() {
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('open')
  const [page, setPage] = useState(1)
  const [selected, setSelected] = useState<SupportTicket | null>(null)

  const q = useDebounced(search)
  const params: AdminTicketsQuery = useMemo(
    () => ({ q: q || undefined, status: status || undefined, page }),
    [q, status, page],
  )
  const { data, isLoading, isError, isFetching } = useSupportTickets(params)

  const columns: Column<SupportTicket>[] = [
    {
      key: 'subject',
      header: 'Ticket',
      render: (t) => (
        <div>
          <p className="font-semibold text-foreground">{t.subject}</p>
          <p className="text-xs text-muted">
            {t.requester ?? t.email} · {t.category ?? 'general'}
          </p>
        </div>
      ),
    },
    { key: 'priority', header: 'Priority', hideOnMobile: true, render: (t) => <Badge variant={PRIORITY_TONE[t.priority] ?? 'neutral'}>{t.priority}</Badge> },
    { key: 'status', header: 'Status', render: (t) => <Badge variant={STATUS_TONE[t.status] ?? 'neutral'}>{t.status.replace('_', ' ')}</Badge> },
    {
      key: 'created',
      header: 'Raised',
      hideOnMobile: true,
      render: (t) => (t.created_at ? new Date(t.created_at).toLocaleDateString() : '—'),
    },
  ]

  if (isError) return <Alert variant="danger">Couldn’t load support tickets.</Alert>

  const meta = data?.meta

  return (
    <div className="flex flex-col gap-6">
      <AdminPageHeader
        title="Support"
        description="Inbound support requests. Triage: set status, priority, and reply."
        actions={data ? <Badge variant="gold">{data.open_count} open</Badge> : undefined}
      />

      <DataTable
        columns={columns}
        rows={data?.data ?? []}
        getRowId={(t) => t.id}
        isLoading={isLoading}
        onRowClick={setSelected}
        empty={status === 'open' ? 'No open tickets. 🎉' : 'No tickets with this status.'}
        toolbar={
          <AdminToolbar search={search} onSearch={(v) => { setSearch(v); setPage(1) }} searchPlaceholder="Search subject or email…">
            <FilterSelect
              label="Status"
              value={status}
              onChange={(v) => { setStatus(v); setPage(1) }}
              allLabel="All"
              options={[
                { label: 'Open', value: 'open' },
                { label: 'In progress', value: 'in_progress' },
                { label: 'Resolved', value: 'resolved' },
              ]}
            />
          </AdminToolbar>
        }
      />

      {meta && meta.total > 0 && (
        <div className="flex items-center justify-between text-sm text-muted">
          <span>Page {meta.current_page} of {meta.last_page} · {meta.total} tickets</span>
          <div className="flex gap-2">
            <Button size="sm" variant="ghost" disabled={meta.current_page <= 1 || isFetching} onClick={() => setPage((p) => p - 1)}>Previous</Button>
            <Button size="sm" variant="ghost" disabled={meta.current_page >= meta.last_page || isFetching} onClick={() => setPage((p) => p + 1)}>Next</Button>
          </div>
        </div>
      )}

      {selected && (
        <TicketModal ticket={selected} assignees={data?.assignees ?? []} onClose={() => setSelected(null)} />
      )}
    </div>
  )
}

function ThreadMessage({ m }: { m: SupportMessage }) {
  return (
    <div className={m.is_staff ? 'flex justify-end' : 'flex justify-start'}>
      <div
        className={
          m.is_staff
            ? 'max-w-[85%] rounded-2xl rounded-tr-sm bg-primary/10 p-3 text-sm text-foreground'
            : 'max-w-[85%] rounded-2xl rounded-tl-sm bg-surface-muted p-3 text-sm text-foreground'
        }
      >
        <p className="mb-1 text-xs font-semibold text-muted">
          {m.is_staff ? m.author ?? 'Support' : m.author ?? 'Requester'}
          {m.created_at ? ` · ${new Date(m.created_at).toLocaleString()}` : ''}
        </p>
        <p className="whitespace-pre-wrap">{m.body}</p>
      </div>
    </div>
  )
}

function TicketModal({
  ticket,
  assignees,
  onClose,
}: {
  ticket: SupportTicket
  assignees: SupportAssignee[]
  onClose: () => void
}) {
  const update = useUpdateTicket()
  const reply = useReplyTicket()
  const [status, setStatus] = useState(ticket.status)
  const [priority, setPriority] = useState(ticket.priority)
  const [assignedTo, setAssignedTo] = useState<number | null>(ticket.assigned_to ?? null)
  const [messages, setMessages] = useState<SupportMessage[]>(ticket.messages ?? [])
  const [body, setBody] = useState('')
  const [error, setError] = useState<string | null>(null)

  const selectClass =
    'h-11 rounded-xl border border-border-strong bg-surface px-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring'

  async function onSave() {
    setError(null)
    const input: UpdateTicketInput = { status, priority, assigned_to: assignedTo }
    try {
      await update.mutateAsync({ ticketId: ticket.id, input })
      onClose()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not update the ticket.')
    }
  }

  async function onReply() {
    if (!body.trim()) return
    setError(null)
    try {
      const updated = await reply.mutateAsync({ ticketId: ticket.id, body: body.trim() })
      setMessages(updated.messages ?? [])
      setStatus(updated.status)
      setBody('')
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not send the reply.')
    }
  }

  return (
    <Modal
      open
      onClose={onClose}
      title={ticket.subject}
      description={`${ticket.requester ?? ticket.email} · ${ticket.category ?? 'general'}`}
    >
      <div className="flex flex-col gap-4">
        {error && <Alert variant="danger">{error}</Alert>}

        {/* Conversation: opening message, then the staff/requester thread. */}
        <div className="flex max-h-72 flex-col gap-2 overflow-y-auto">
          <ThreadMessage
            m={{ id: 0, body: ticket.message ?? '', is_staff: false, author: ticket.requester ?? null, created_at: ticket.created_at }}
          />
          {messages.map((m) => (
            <ThreadMessage key={m.id} m={m} />
          ))}
        </div>

        {/* Reply composer. */}
        <div className="flex flex-col gap-2">
          <textarea
            value={body}
            onChange={(e) => setBody(e.target.value)}
            rows={3}
            placeholder="Write a reply to the requester…"
            aria-label="Reply to the requester"
            className="w-full rounded-xl border border-border-strong bg-surface px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
          />
          <div className="flex justify-end">
            <Button variant="ghost" loading={reply.isPending} disabled={!body.trim()} onClick={onReply}>
              Send reply
            </Button>
          </div>
        </div>

        <div className="grid grid-cols-1 gap-3 border-t border-border pt-4 sm:grid-cols-3">
          <label className="flex flex-col gap-1.5 text-sm">
            <span className="font-semibold text-foreground">Status</span>
            <select value={status} onChange={(e) => setStatus(e.target.value)} className={selectClass}>
              <option value="open">Open</option>
              <option value="in_progress">In progress</option>
              <option value="resolved">Resolved</option>
            </select>
          </label>
          <label className="flex flex-col gap-1.5 text-sm">
            <span className="font-semibold text-foreground">Priority</span>
            <select value={priority} onChange={(e) => setPriority(e.target.value)} className={selectClass}>
              <option value="low">Low</option>
              <option value="normal">Normal</option>
              <option value="high">High</option>
            </select>
          </label>
          <label className="flex flex-col gap-1.5 text-sm">
            <span className="font-semibold text-foreground">Assignee</span>
            <select
              value={assignedTo ?? ''}
              onChange={(e) => setAssignedTo(e.target.value ? Number(e.target.value) : null)}
              className={selectClass}
            >
              <option value="">Unassigned</option>
              {assignees.map((a) => (
                <option key={a.id} value={a.id}>
                  {a.name}
                </option>
              ))}
            </select>
          </label>
        </div>

        <div className="flex justify-end gap-2">
          <Button variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button variant="parent" loading={update.isPending} onClick={onSave}>
            Save
          </Button>
        </div>
      </div>
    </Modal>
  )
}
