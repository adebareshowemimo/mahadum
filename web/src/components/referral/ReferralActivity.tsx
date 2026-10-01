import { useId, useState } from 'react'
import { Alert, Badge, Button, Card, CardBody, Icon, Input, Skeleton } from '@/components/ui'
import { useReferralActivations } from '@/lib/referral/queries'

function humanize(status: string): string {
  return status.replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase())
}

export function ReferralActivity({ organizationId, hideWhenEmpty = false }: { organizationId?: number; hideWhenEmpty?: boolean }) {
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const { data, isLoading, isError } = useReferralActivations(search, page, organizationId)
  const rows = data?.data ?? []
  const headingId = useId()

  if (hideWhenEmpty && data?.meta.total === 0 && !search) return null

  return (
    <section aria-labelledby={headingId} className="flex flex-col gap-3">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h2 id={headingId} className="font-display text-lg font-bold text-foreground">{organizationId ? 'School referral activity' : 'Referral activity'}</h2>
        <div className="min-w-[14rem]">
          <Input
            value={search}
            onChange={(e) => { setSearch(e.target.value); setPage(1) }}
            placeholder="Search by email or phone"
            leftIcon={<Icon name="search" />}
            aria-label="Search referral activity"
          />
        </div>
      </div>

      {isError ? (
        <Alert variant="danger">Couldn’t load your referral activity.</Alert>
      ) : isLoading ? (
        <Skeleton className="h-32" />
      ) : rows.length === 0 ? (
        <Card>
          <CardBody className="py-8 text-center text-sm text-muted">
            {search ? 'No referrals match that search.' : 'No one has signed up through your referral code yet.'}
          </CardBody>
        </Card>
      ) : (
        <Card>
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead>
                <tr className="border-b border-border text-xs uppercase tracking-wide text-muted">
                  <th className="px-4 py-2.5 font-semibold">SN</th>
                  <th className="px-4 py-2.5 font-semibold">Activation date</th>
                  <th className="px-4 py-2.5 font-semibold">Code</th>
                  <th className="px-4 py-2.5 font-semibold">Email</th>
                  <th className="px-4 py-2.5 font-semibold">Phone number</th>
                  <th className="px-4 py-2.5 font-semibold">Activation status</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((r) => (
                  <tr key={r.sn} className="border-b border-border last:border-0">
                    <td className="px-4 py-3 tabular-nums text-muted">{r.sn}</td>
                    <td className="px-4 py-3 text-foreground">
                      {r.activated_at ? new Date(`${r.activated_at}T00:00:00`).toLocaleDateString() : '—'}
                    </td>
                    <td className="px-4 py-3 font-mono text-foreground">{r.code}</td>
                    <td className="px-4 py-3 text-foreground">{r.via_email ?? '—'}</td>
                    <td className="px-4 py-3 text-foreground">{r.via_phone ?? '—'}</td>
                    <td className="px-4 py-3">
                      <Badge variant={r.status === 'active' ? 'success' : r.status === 'pending' ? 'gold' : 'neutral'}>
                        {humanize(r.status)}
                      </Badge>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Card>
      )}
      {data && data.meta.last_page > 1 && (
        <nav aria-label="Referral activity pages" className="flex items-center justify-between gap-3">
          <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage(page - 1)}>Previous</Button>
          <span className="text-sm text-muted">Page {data.meta.current_page} of {data.meta.last_page} · {data.meta.total} referrals</span>
          <Button variant="outline" size="sm" disabled={page >= data.meta.last_page} onClick={() => setPage(page + 1)}>Next</Button>
        </nav>
      )}
    </section>
  )
}
