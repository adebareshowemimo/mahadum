import { useParams } from 'react-router-dom'
import { AdminPageHeader } from '@/components/admin'
import { Alert, Badge, Card, Spinner } from '@/components/ui'
import { useEmailLogEntry } from '@/lib/admin/queries'

const STATUS_TONE: Record<string, 'success' | 'gold' | 'danger' | 'neutral'> = {
  sent: 'success', delivered: 'success', queued: 'gold', bounced: 'danger', complained: 'danger', failed: 'danger',
}

export function EmailLogDetailPage() {
  const logId = Number(useParams().logId)
  const { data: entry, isLoading, isError } = useEmailLogEntry(logId)

  if (!Number.isInteger(logId) || logId < 1) return <Alert variant="danger">This email log link is invalid.</Alert>
  if (isLoading) return <div className="flex justify-center py-16" aria-label="Loading email"><Spinner /></div>
  if (isError || !entry) return <Alert variant="danger">Couldn't load this email record.</Alert>

  const fields = [
    ['Recipient', entry.to_email],
    ['Subject', entry.subject ?? '—'],
    ['Type', entry.type],
    ['Source', entry.source ?? '—'],
    ['Sent', entry.sent_at ? new Date(entry.sent_at).toLocaleString() : '—'],
    ['Recorded', entry.created_at ? new Date(entry.created_at).toLocaleString() : '—'],
  ]

  return (
    <div className="flex flex-col gap-6">
      <AdminPageHeader title={entry.subject ?? 'Email record'} description={entry.to_email} backTo="/admin/emails/log" backLabel="Back to email log" actions={<Badge variant={STATUS_TONE[entry.status] ?? 'neutral'}>{entry.status}</Badge>} />
      <Card className="p-5 sm:p-6">
        <dl className="grid gap-4 sm:grid-cols-[9rem_minmax(0,1fr)]">
          {fields.map(([label, value]) => (
            <div key={label} className="contents">
              <dt className="text-sm font-medium text-muted">{label}</dt>
              <dd className="break-words text-sm text-foreground">{value}</dd>
            </div>
          ))}
        </dl>
      </Card>
    </div>
  )
}
