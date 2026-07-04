import { useState, type FormEvent } from 'react'
import { AdminPageHeader, DataTable, type Column } from '@/components/admin'
import { Alert, Badge, Button, Input, Modal } from '@/components/ui'
import { ApiError, type CreateCampaignInput, type EmailCampaignRow } from '@/lib/api'
import {
  useContactLists,
  useCreateEmailCampaign,
  useEmailCampaigns,
  useSendEmailCampaign,
  useTestEmailCampaign,
} from '@/lib/admin/queries'

const STATUS_TONE: Record<string, 'success' | 'gold' | 'info' | 'neutral' | 'danger'> = {
  draft: 'neutral',
  scheduled: 'gold',
  sending: 'info',
  sent: 'success',
  failed: 'danger',
}

export function EmailCampaignsPage() {
  const { data, isLoading, isError } = useEmailCampaigns()
  const test = useTestEmailCampaign()
  const [composing, setComposing] = useState(false)
  const [sending, setSending] = useState<EmailCampaignRow | null>(null)
  const [flash, setFlash] = useState<string | null>(null)

  async function onTest(c: EmailCampaignRow) {
    setFlash(null)
    try {
      const res = await test.mutateAsync(c.id)
      setFlash(`Test email sent to ${res.sent_to}.`)
    } catch {
      setFlash('Could not send the test email.')
    }
  }

  const columns: Column<EmailCampaignRow>[] = [
    {
      key: 'subject',
      header: 'Campaign',
      render: (c) => (
        <div>
          <p className="font-semibold text-foreground">{c.subject}</p>
          <p className="text-xs text-muted">{c.audience_type === 'contact_list' ? 'Contact list' : 'User segment'}</p>
        </div>
      ),
    },
    { key: 'status', header: 'Status', render: (c) => <Badge variant={STATUS_TONE[c.status] ?? 'neutral'}>{c.status}</Badge> },
    {
      key: 'sent',
      header: 'Sent',
      hideOnMobile: true,
      render: (c) => (c.status === 'sent' ? `${c.sent_count}/${c.recipients_count}` : c.scheduled_at ? new Date(c.scheduled_at).toLocaleString() : '—'),
    },
    {
      key: 'actions',
      header: '',
      render: (c) =>
        c.status === 'draft' || c.status === 'scheduled' ? (
          <div className="flex justify-end gap-2">
            <Button size="sm" variant="ghost" loading={test.isPending && test.variables === c.id} onClick={() => onTest(c)}>Test</Button>
            <Button size="sm" variant="parent" onClick={() => setSending(c)}>Send</Button>
          </div>
        ) : null,
    },
  ]

  if (isError) return <Alert variant="danger">Couldn’t load campaigns.</Alert>

  return (
    <div className="flex flex-col gap-6">
      <AdminPageHeader
        title="Email campaigns"
        description="Compose an email, target a user segment or a contact list, then send or schedule it."
        actions={<Button variant="parent" onClick={() => setComposing(true)}>New campaign</Button>}
      />

      {flash && <Alert variant="info">{flash}</Alert>}

      <DataTable columns={columns} rows={data ?? []} getRowId={(c) => c.id} isLoading={isLoading} empty="No campaigns yet." />

      {composing && <ComposeModal onClose={() => setComposing(false)} />}
      {sending && <SendModal campaign={sending} onClose={() => setSending(null)} />}
    </div>
  )
}

function ComposeModal({ onClose }: { onClose: () => void }) {
  const create = useCreateEmailCampaign()
  const { data: lists } = useContactLists()
  const [subject, setSubject] = useState('')
  const [body, setBody] = useState('')
  const [audienceType, setAudienceType] = useState<'user_segment' | 'contact_list'>('contact_list')
  const [listId, setListId] = useState('')
  const [role, setRole] = useState('')
  const [error, setError] = useState<string | null>(null)

  const selectClass = 'h-11 rounded-xl border border-border-strong bg-surface px-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring'

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setError(null)
    const audience =
      audienceType === 'contact_list' ? { contact_list_id: Number(listId) } : role ? { role } : {}
    const input: CreateCampaignInput = { subject: subject.trim(), body, audience_type: audienceType, audience }
    try {
      await create.mutateAsync(input)
      onClose()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not save the campaign.')
    }
  }

  const canSubmit = subject.trim() && body.trim() && (audienceType === 'user_segment' || listId)

  return (
    <Modal open onClose={onClose} title="New campaign" description="Saved as a draft — you can test and send it next.">
      <form onSubmit={onSubmit} className="flex flex-col gap-4">
        {error && <Alert variant="danger">{error}</Alert>}
        <Input label="Subject" value={subject} onChange={(e) => setSubject(e.target.value)} required />

        <label className="flex flex-col gap-1.5 text-sm">
          <span className="font-semibold text-foreground">Body (Markdown)</span>
          <textarea
            value={body}
            onChange={(e) => setBody(e.target.value)}
            rows={6}
            placeholder="Write your email… **bold**, [links](https://…), etc. It renders in the branded template."
            className="w-full rounded-xl border border-border-strong bg-surface px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
          />
        </label>

        <div className="grid gap-3 sm:grid-cols-2">
          <label className="flex flex-col gap-1.5 text-sm">
            <span className="font-semibold text-foreground">Audience</span>
            <select value={audienceType} onChange={(e) => setAudienceType(e.target.value as 'user_segment' | 'contact_list')} className={selectClass}>
              <option value="contact_list">A contact list</option>
              <option value="user_segment">A user segment</option>
            </select>
          </label>

          {audienceType === 'contact_list' ? (
            <label className="flex flex-col gap-1.5 text-sm">
              <span className="font-semibold text-foreground">List</span>
              <select value={listId} onChange={(e) => setListId(e.target.value)} className={selectClass}>
                <option value="">Choose a list…</option>
                {(lists ?? []).map((l) => (
                  <option key={l.id} value={l.id}>{l.name} ({l.subscribed})</option>
                ))}
              </select>
            </label>
          ) : (
            <label className="flex flex-col gap-1.5 text-sm">
              <span className="font-semibold text-foreground">Role (optional)</span>
              <select value={role} onChange={(e) => setRole(e.target.value)} className={selectClass}>
                <option value="">All users</option>
                {['parent', 'student', 'teacher', 'school_admin', 'content_owner'].map((r) => (
                  <option key={r} value={r}>{r}</option>
                ))}
              </select>
            </label>
          )}
        </div>

        <div className="flex justify-end gap-2">
          <Button type="button" variant="ghost" onClick={onClose}>Cancel</Button>
          <Button type="submit" variant="parent" loading={create.isPending} disabled={!canSubmit}>Save draft</Button>
        </div>
      </form>
    </Modal>
  )
}

function SendModal({ campaign, onClose }: { campaign: EmailCampaignRow; onClose: () => void }) {
  const send = useSendEmailCampaign()
  const [when, setWhen] = useState('')
  const [error, setError] = useState<string | null>(null)

  async function go(scheduled: boolean) {
    setError(null)
    try {
      await send.mutateAsync({ id: campaign.id, scheduledAt: scheduled && when ? new Date(when).toISOString() : undefined })
      onClose()
    } catch (err) {
      if (err instanceof ApiError && err.status === 409) setError('This campaign has already been sent.')
      else setError(err instanceof ApiError ? err.message : 'Could not send.')
    }
  }

  return (
    <Modal open onClose={onClose} title="Send campaign" description={campaign.subject}>
      <div className="flex flex-col gap-4">
        {error && <Alert variant="danger">{error}</Alert>}
        <p className="text-sm text-muted">
          Sends the branded email to everyone in the target audience, skipping unsubscribed and suppressed addresses.
          Every send is recorded in the email log.
        </p>

        <label className="flex flex-col gap-1.5 text-sm">
          <span className="font-semibold text-foreground">Schedule for later (optional)</span>
          <input
            type="datetime-local"
            value={when}
            onChange={(e) => setWhen(e.target.value)}
            className="h-11 rounded-xl border border-border-strong bg-surface px-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
          />
        </label>

        <div className="flex justify-end gap-2">
          <Button variant="ghost" onClick={onClose}>Cancel</Button>
          {when ? (
            <Button variant="parent" loading={send.isPending} onClick={() => go(true)}>Schedule</Button>
          ) : (
            <Button variant="parent" loading={send.isPending} onClick={() => go(false)}>Send now</Button>
          )}
        </div>
      </div>
    </Modal>
  )
}
