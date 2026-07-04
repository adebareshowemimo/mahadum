import { Link } from 'react-router-dom'
import { Alert, Badge, Card, CardBody, Skeleton } from '@/components/ui'
import type { CompetitionStatus } from '@/lib/api'
import { useCompetitions } from '@/lib/competition/queries'

const STATUS_META: Record<CompetitionStatus, { label: string; variant: 'success' | 'info' | 'neutral' }> = {
  draft: { label: 'Draft', variant: 'neutral' },
  open: { label: 'Entries open', variant: 'success' },
  voting: { label: 'Voting open', variant: 'info' },
  closed: { label: 'Closed', variant: 'neutral' },
}

export function CompetitionsPage() {
  const { data, isLoading, isError } = useCompetitions()

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
            <Link key={c.id} to={`/competitions/${c.id}`} className="block">
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
    </div>
  )
}
