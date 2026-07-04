import { useState } from 'react'
import { useParams } from 'react-router-dom'
import { AdminPageHeader, DataTable, FilterSelect, type Column } from '@/components/admin'
import { Alert, Badge, Button, Card, CardBody, Skeleton } from '@/components/ui'
import type { CampaignRecipientRow } from '@/lib/api'
import { useCampaignRecipients, useCancelEmailCampaign, useEmailCampaign } from '@/lib/admin/queries'

const STATUS_TONE: Record<string, 'success' | 'gold' | 'info' | 'neutral' | 'danger'> = {
  draft: 'neutral',
  scheduled: 'gold',
  sending: 'info',
  sent: 'success',
  failed: 'danger',
}

const RECIPIENT_TONE: Record<string, 'success' | 'gold' | 'danger' | 'neutral'> = {
  sent: 'success',
  queued: 'gold',
  suppressed: 'neutral',
  failed: 'danger',
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

export function CampaignDetailPage() {
  const { campaignId } = useParams()
  const id = Number(campaignId)
  const { data, isLoading, isError } = useEmailCampaign(id)
  const cancel = useCancelEmailCampaign()

  if (isLoading) return <Skeleton className="h-96" />
  if (isError || !data) return <Alert variant="danger">Couldn’t load this campaign.</Alert>

  const audienceLabel =
    data.audience_type === 'contact_list'
      ? `Contact list #${(data.audience?.contact_list_id as number) ?? '—'}`
      : `User segment${data.audience?.role ? ` · ${data.audience.role}` : ''}`

  return (
    <div className="flex flex-col gap-6">
      <AdminPageHeader
        title={data.subject}
        description={audienceLabel}
        backTo="/admin/emails"
        backLabel="Campaigns"
        actions={
          <div className="flex items-center gap-2">
            <Badge variant={STATUS_TONE[data.status] ?? 'neutral'}>{data.status}</Badge>
            {data.status === 'scheduled' && (
              <Button size="sm" variant="ghost" loading={cancel.isPending} onClick={() => cancel.mutate(id)}>
                Cancel schedule
              </Button>
            )}
          </div>
        }
      />

      <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <Stat label="Recipients" value={data.recipients_count} />
        <Stat label="Sent" value={data.sent_count} />
        <Stat label="Failed" value={data.failed_count} />
        <Stat
          label={data.status === 'scheduled' ? 'Scheduled' : 'Sent at'}
          value={
            data.status === 'scheduled'
              ? data.scheduled_at
                ? new Date(data.scheduled_at).toLocaleString()
                : '—'
              : data.sent_at
                ? new Date(data.sent_at).toLocaleString()
                : '—'
          }
        />
      </div>

      {Object.keys(data.recipients_by_status).length > 0 && (
        <Card>
          <CardBody className="flex flex-col gap-2">
            <p className="text-sm font-semibold text-foreground">Recipients by status</p>
            <div className="flex flex-wrap gap-2">
              {Object.entries(data.recipients_by_status).map(([status, count]) => (
                <Badge key={status} variant={RECIPIENT_TONE[status] ?? 'neutral'}>
                  {status}: {count}
                </Badge>
              ))}
            </div>
          </CardBody>
        </Card>
      )}

      <section className="flex flex-col gap-2">
        <h2 className="font-display text-lg font-bold text-foreground">Body</h2>
        <Card>
          <CardBody>
            <pre className="whitespace-pre-wrap font-sans text-sm text-foreground">{data.body}</pre>
          </CardBody>
        </Card>
      </section>

      {data.recipients_count > 0 && <RecipientsTable campaignId={id} />}
    </div>
  )
}

const RECIPIENT_STATUS_TONE: Record<string, 'success' | 'gold' | 'danger' | 'neutral'> = {
  sent: 'success',
  queued: 'gold',
  suppressed: 'neutral',
  failed: 'danger',
}

function RecipientsTable({ campaignId }: { campaignId: number }) {
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const { data, isLoading, isFetching } = useCampaignRecipients(campaignId, { status: status || undefined, page })

  const columns: Column<CampaignRecipientRow>[] = [
    { key: 'email', header: 'Recipient', render: (r) => <span className="text-foreground">{r.email}</span> },
    {
      key: 'status',
      header: 'Status',
      render: (r) => <Badge variant={RECIPIENT_STATUS_TONE[r.status] ?? 'neutral'}>{r.status}</Badge>,
    },
  ]

  const meta = data?.meta

  return (
    <section className="flex flex-col gap-2">
      <div className="flex items-center justify-between">
        <h2 className="font-display text-lg font-bold text-foreground">Recipients</h2>
        <FilterSelect
          label="Status"
          value={status}
          onChange={(v) => { setStatus(v); setPage(1) }}
          allLabel="All"
          options={[
            { label: 'Sent', value: 'sent' },
            { label: 'Suppressed', value: 'suppressed' },
            { label: 'Failed', value: 'failed' },
            { label: 'Queued', value: 'queued' },
          ]}
        />
      </div>
      <DataTable columns={columns} rows={data?.data ?? []} getRowId={(r) => r.id} isLoading={isLoading} empty="No recipients match." />
      {meta && meta.total > 0 && (
        <div className="flex items-center justify-between text-sm text-muted">
          <span>Page {meta.current_page} of {meta.last_page} · {meta.total} recipients</span>
          <div className="flex gap-2">
            <Button size="sm" variant="ghost" disabled={meta.current_page <= 1 || isFetching} onClick={() => setPage((p) => p - 1)}>Previous</Button>
            <Button size="sm" variant="ghost" disabled={meta.current_page >= meta.last_page || isFetching} onClick={() => setPage((p) => p + 1)}>Next</Button>
          </div>
        </div>
      )}
    </section>
  )
}
