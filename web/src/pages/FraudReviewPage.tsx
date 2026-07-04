import { useState } from 'react'
import { AdminPageHeader, DataTable, type Column } from '@/components/admin'
import { Alert, Badge, Button } from '@/components/ui'
import { ApiError, type FlaggedReferral } from '@/lib/api'
import { useFlaggedReferrals, useReviewReferral } from '@/lib/admin/queries'

const STATUS_TONE: Record<string, 'gold' | 'danger' | 'neutral'> = {
  flagged: 'gold',
  frozen: 'danger',
}

export function FraudReviewPage() {
  const { data, isLoading, isError } = useFlaggedReferrals()
  const review = useReviewReferral()
  const [error, setError] = useState<string | null>(null)
  const [actingId, setActingId] = useState<number | null>(null)

  async function act(code: FlaggedReferral, action: 'clear' | 'freeze') {
    setError(null)
    setActingId(code.id)
    try {
      await review.mutateAsync({ codeId: code.id, action })
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not update the referral code.')
    } finally {
      setActingId(null)
    }
  }

  const columns: Column<FlaggedReferral>[] = [
    {
      key: 'code',
      header: 'Referral code',
      render: (c) => (
        <div>
          <p className="font-mono font-semibold text-foreground">{c.code}</p>
          <p className="text-xs capitalize text-muted">{c.kind} code</p>
        </div>
      ),
    },
    {
      key: 'owner',
      header: 'Owner',
      render: (c) => (
        <div>
          <p className="text-foreground">{c.owner.name ?? `#${c.owner.id}`}</p>
          <p className="text-xs capitalize text-muted">{c.owner.type}</p>
        </div>
      ),
    },
    {
      key: 'velocity',
      header: 'Sign-ups (24h / total)',
      className: 'tabular-nums',
      hideOnMobile: true,
      render: (c) => (
        <span>
          <span className={c.referrals_24h > 0 ? 'font-bold text-danger' : 'text-foreground'}>{c.referrals_24h}</span>
          <span className="text-muted"> / {c.referrals_total}</span>
        </span>
      ),
    },
    { key: 'status', header: 'Status', render: (c) => <Badge variant={STATUS_TONE[c.status] ?? 'neutral'}>{c.status}</Badge> },
    {
      key: 'actions',
      header: '',
      className: 'text-right',
      render: (c) => (
        <div className="flex justify-end gap-2">
          {c.status === 'flagged' && (
            <Button size="sm" variant="ghost" loading={actingId === c.id} onClick={() => act(c, 'freeze')}>
              Confirm fraud
            </Button>
          )}
          <Button size="sm" variant="parent" loading={actingId === c.id} onClick={() => act(c, 'clear')}>
            Clear
          </Button>
        </div>
      ),
    },
  ]

  if (isError) return <Alert variant="danger">Couldn’t load the fraud queue.</Alert>

  return (
    <div className="flex flex-col gap-6">
      <AdminPageHeader
        title="Fraud review"
        description="Referral codes flagged by the velocity guard (FR-7.5). Clear a false positive, or confirm fraud to freeze the code."
      />

      {error && <Alert variant="danger">{error}</Alert>}

      <DataTable
        columns={columns}
        rows={data ?? []}
        getRowId={(c) => c.id}
        isLoading={isLoading}
        empty="No referral codes under review. 🎉"
      />
    </div>
  )
}
