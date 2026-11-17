import { Link } from 'react-router-dom'
import { useState } from 'react'
import { Alert, Badge, Button, Card, CardBody, Skeleton } from '@/components/ui'
import { ApiError, type CompetitionStatus, type MyCompetitionEntry } from '@/lib/api'
import { useAuth } from '@/lib/auth/AuthProvider'
import { useApproveEntryAsGuardian, useCompetitions, useMyEntries } from '@/lib/competition/queries'

const STATUS_META: Record<CompetitionStatus, { label: string; variant: 'success' | 'info' | 'neutral' }> = {
  draft: { label: 'Draft', variant: 'neutral' },
  open: { label: 'Entries open', variant: 'success' },
  voting: { label: 'Voting open', variant: 'info' },
  closed: { label: 'Closed', variant: 'neutral' },
}

export function CompetitionsPage() {
  const { data, isLoading, isError } = useCompetitions()
  const { status } = useAuth()
  const { data: myEntries } = useMyEntries(status === 'authenticated')

  return (
    <div className="flex flex-col gap-8">
      <header>
        <h1 className="font-display text-2xl font-bold text-foreground">Language &amp; Culture competition</h1>
        <p className="mt-1 max-w-2xl text-muted">
          The annual national celebration of Nigerian languages. Schools stage a culturally relevant play; diaspora
          children present folktales in their heritage language. Watch the entries and vote for your favourites.
        </p>
      </header>

      {isLoading && <Skeleton className="h-40" />}
      {isError && <Alert variant="danger">Couldn’t load competitions.</Alert>}

      {data && data.length === 0 && (
        <Card>
          <CardBody className="py-10 text-center text-sm text-muted">
            No competitions are running yet. Check back soon.
          </CardBody>
        </Card>
      )}

      <div className="grid gap-4 sm:grid-cols-2">
        {data?.map((c) => {
          const meta = STATUS_META[c.status]
          return (
            <Link key={c.id} to={`/competitions/${c.slug}`} className="block">
              <Card className="h-full transition hover:border-border-strong">
                <CardBody className="flex flex-col gap-3">
                  <div className="flex items-start justify-between gap-3">
                    <div>
                      <h2 className="font-display text-lg font-bold text-foreground">{c.title}</h2>
                      <p className="text-xs text-muted">Season {c.season}</p>
                    </div>
                    <Badge variant={meta.variant}>{meta.label}</Badge>
                  </div>
                  <p className="text-sm text-muted">
                    {c.entries_count} {c.entries_count === 1 ? 'entry' : 'entries'}
                  </p>
                </CardBody>
              </Card>
            </Link>
          )
        })}
      </div>
      {myEntries && myEntries.length > 0 && <section className="space-y-3"><h2 className="font-display text-xl font-bold text-foreground">My entries</h2>{myEntries.map((entry) => <MyEntryRow key={entry.id} entry={entry} />)}</section>}
    </div>
  )
}

function MyEntryRow({ entry }: { entry: MyCompetitionEntry }) {
  const approve = useApproveEntryAsGuardian()
  const [error, setError] = useState<string | null>(null)
  async function onApprove() {
    setError(null)
    try {
      await approve.mutateAsync({ competitionId: entry.competition_id, entryId: entry.id })
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not record parent approval.')
    }
  }
  return <Card><CardBody className="flex flex-wrap items-center justify-between gap-3">
    <div><p className="font-semibold text-foreground">{entry.title}</p><p className="text-xs text-muted">{entry.competition} · {entry.status}{entry.guardian_approved_at ? ' · Parent approved' : ''}</p></div>
    {entry.category === 'diaspora_folklore' && !entry.guardian_approved_at && <div className="text-right"><p className="mb-1 text-xs text-muted">Approve this child’s entry as their parent or guardian.</p><Button size="sm" variant="parent" loading={approve.isPending} onClick={onApprove}>Approve child entry</Button>{error && <p role="alert" className="text-xs text-danger">{error}</p>}</div>}
  </CardBody></Card>
}
