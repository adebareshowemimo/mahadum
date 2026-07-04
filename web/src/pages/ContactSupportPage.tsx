import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Alert, Badge, Button, Card, CardBody, Input, Skeleton } from '@/components/ui'
import { ApiError, supportApi, type CreateTicketInput, type SupportTicket } from '@/lib/api'

const STATUS_TONE: Record<string, 'gold' | 'info' | 'success' | 'neutral'> = {
  open: 'gold',
  in_progress: 'info',
  resolved: 'success',
}

const CATEGORIES = ['billing', 'account', 'technical', 'content', 'other']

export function ContactSupportPage() {
  const qc = useQueryClient()
  const tickets = useQuery({ queryKey: ['support', 'my-tickets'], queryFn: supportApi.tickets })
  const create = useMutation({
    mutationFn: (input: CreateTicketInput) => supportApi.createTicket(input),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ['support', 'my-tickets'] }),
  })

  const [subject, setSubject] = useState('')
  const [category, setCategory] = useState('')
  const [message, setMessage] = useState('')
  const [sent, setSent] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setError(null)
    setFieldErrors({})
    setSent(false)
    try {
      await create.mutateAsync({ subject: subject.trim(), category: category || undefined, message: message.trim() })
      setSent(true)
      setSubject('')
      setCategory('')
      setMessage('')
    } catch (err) {
      if (err instanceof ApiError) {
        setFieldErrors(err.fieldErrors)
        if (!Object.keys(err.fieldErrors).length) setError(err.message)
      } else {
        setError('Could not send your message.')
      }
    }
  }

  return (
    <div className="flex max-w-2xl flex-col gap-6">
      <div>
        <h1 className="font-display text-2xl font-bold text-foreground">Help &amp; support</h1>
        <p className="mt-1 text-muted">Tell us what’s going on — we’ll get back to you here.</p>
      </div>

      <Card>
        <CardBody>
          <form onSubmit={onSubmit} className="flex flex-col gap-4">
            {sent && <Alert variant="success">Thanks — your message has been sent. We’ll reply here.</Alert>}
            {error && <Alert variant="danger">{error}</Alert>}
            <Input label="Subject" value={subject} onChange={(e) => setSubject(e.target.value)} error={fieldErrors.subject} required />
            <label className="flex flex-col gap-1.5 text-sm">
              <span className="font-semibold text-foreground">Category</span>
              <select value={category} onChange={(e) => setCategory(e.target.value)} className="h-11 rounded-xl border border-border-strong bg-surface px-3 text-sm capitalize text-foreground focus:outline-none focus:ring-2 focus:ring-ring">
                <option value="">Choose…</option>
                {CATEGORIES.map((c) => (
                  <option key={c} value={c}>{c}</option>
                ))}
              </select>
            </label>
            <label className="flex flex-col gap-1.5 text-sm">
              <span className="font-semibold text-foreground">Message</span>
              <textarea value={message} onChange={(e) => setMessage(e.target.value)} rows={5} className="w-full rounded-xl border border-border-strong bg-surface px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring" />
              {fieldErrors.message && <span className="text-xs font-medium text-danger">{fieldErrors.message}</span>}
            </label>
            <div className="flex justify-end">
              <Button type="submit" variant="parent" loading={create.isPending} disabled={!subject.trim() || !message.trim()}>
                Send message
              </Button>
            </div>
          </form>
        </CardBody>
      </Card>

      <section className="flex flex-col gap-2">
        <h2 className="font-display text-lg font-bold text-foreground">Your requests</h2>
        {tickets.isLoading ? (
          <Skeleton className="h-24" />
        ) : (tickets.data ?? []).length === 0 ? (
          <Card><CardBody className="py-8 text-center text-sm text-muted">No requests yet.</CardBody></Card>
        ) : (
          (tickets.data ?? []).map((t) => <MyTicketCard key={t.id} ticket={t} />)
        )}
      </section>
    </div>
  )
}

function MyTicketCard({ ticket }: { ticket: SupportTicket }) {
  const qc = useQueryClient()
  const reply = useMutation({
    mutationFn: (body: string) => supportApi.replyTicket(ticket.id, body),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ['support', 'my-tickets'] }),
  })
  const [body, setBody] = useState('')
  const [error, setError] = useState<string | null>(null)

  async function onReply() {
    if (!body.trim()) return
    setError(null)
    try {
      await reply.mutateAsync(body.trim())
      setBody('')
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not send your reply.')
    }
  }

  return (
    <Card>
      <CardBody className="flex flex-col gap-3">
        <div className="flex items-center justify-between gap-3">
          <p className="font-semibold text-foreground">{ticket.subject}</p>
          <Badge variant={STATUS_TONE[ticket.status] ?? 'neutral'}>{ticket.status.replace('_', ' ')}</Badge>
        </div>

        {/* Conversation: opening message then the back-and-forth thread. */}
        <div className="flex flex-col gap-2">
          <div className="flex justify-start">
            <div className="max-w-[85%] rounded-2xl rounded-tl-sm bg-surface-muted p-3 text-sm text-foreground">
              <p className="whitespace-pre-wrap">{ticket.message}</p>
            </div>
          </div>
          {(ticket.messages ?? []).map((m) => (
            <div key={m.id} className={m.is_staff ? 'flex justify-end' : 'flex justify-start'}>
              <div
                className={
                  m.is_staff
                    ? 'max-w-[85%] rounded-2xl rounded-tr-sm bg-primary/10 p-3 text-sm text-foreground'
                    : 'max-w-[85%] rounded-2xl rounded-tl-sm bg-surface-muted p-3 text-sm text-foreground'
                }
              >
                <p className="mb-1 text-xs font-semibold text-muted">
                  {m.is_staff ? m.author ?? 'Support' : 'You'}
                  {m.created_at ? ` · ${new Date(m.created_at).toLocaleDateString()}` : ''}
                </p>
                <p className="whitespace-pre-wrap">{m.body}</p>
              </div>
            </div>
          ))}
        </div>

        {error && <Alert variant="danger">{error}</Alert>}
        {ticket.status !== 'resolved' || (ticket.messages?.length ?? 0) > 0 ? (
          <div className="flex flex-col gap-2">
            <textarea
              value={body}
              onChange={(e) => setBody(e.target.value)}
              rows={2}
              placeholder="Add a reply…"
              aria-label={`Reply to ${ticket.subject}`}
              className="w-full rounded-xl border border-border-strong bg-surface px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
            />
            <div className="flex justify-end">
              <Button size="sm" variant="ghost" loading={reply.isPending} disabled={!body.trim()} onClick={onReply}>
                Send reply
              </Button>
            </div>
          </div>
        ) : null}
      </CardBody>
    </Card>
  )
}
