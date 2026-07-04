import { Alert, Avatar, Card, CardBody, Skeleton } from '@/components/ui'
import { cn } from '@/lib/cn'
import type { LearnerProfile } from '@/lib/api'
import { ActiveLearnerGate } from '@/components/learner/ActiveLearnerGate'
import { useLeaderboard, useLeagueCurrent } from '@/lib/gamification/queries'

export function LeaderboardPage() {
  return <ActiveLearnerGate>{(learner) => <Leaderboard learner={learner} />}</ActiveLearnerGate>
}

const MEDALS: Record<number, string> = { 1: '🥇', 2: '🥈', 3: '🥉' }

function Leaderboard({ learner }: { learner: LearnerProfile }) {
  const league = useLeagueCurrent(learner.id)
  const board = useLeaderboard(learner.id)

  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="font-display text-2xl font-bold text-foreground">Leaderboard</h1>
        <p className="mt-1 text-muted">Cheer each other on — everyone’s here to grow. 🌱</p>
      </div>

      <Card className="overflow-hidden">
        <CardBody className="flex flex-wrap items-center justify-between gap-3 bg-primary-soft">
          {league.isLoading ? (
            <Skeleton className="h-10 w-40" />
          ) : (
            <>
              <div>
                <p className="text-sm font-medium text-primary">{league.data?.league?.name ?? 'Your league'}</p>
                <p className="font-display text-lg font-bold text-foreground">
                  Tier {String(league.data?.league?.tier ?? '—')}
                </p>
              </div>
              <div className="text-right">
                <p className="text-sm text-muted">Your spot</p>
                <p className="font-display text-lg font-bold text-foreground">
                  #{league.data?.rank ?? '—'} · ⚡ {league.data?.weekly_xp ?? 0}
                </p>
              </div>
            </>
          )}
        </CardBody>
      </Card>

      {board.isLoading ? (
        <Skeleton className="h-64" />
      ) : board.isError ? (
        <Alert variant="danger">Couldn’t load the leaderboard.</Alert>
      ) : (board.data?.length ?? 0) === 0 ? (
        <Card>
          <CardBody className="py-10 text-center text-sm text-muted">
            No rankings yet this week — complete a lesson to get on the board!
          </CardBody>
        </Card>
      ) : (
        <ol className="flex flex-col gap-2">
          {board.data?.map((row) => {
            const isMe = row.learner_id === learner.id
            return (
              <li
                key={row.learner_id}
                className={cn(
                  'flex items-center gap-3 rounded-xl border p-3',
                  isMe ? 'border-primary bg-primary-soft' : 'border-border bg-surface',
                )}
              >
                <span className="w-8 text-center font-display text-lg font-bold text-foreground">
                  {MEDALS[row.rank] ?? row.rank}
                </span>
                <Avatar name={row.display_name ?? 'Learner'} size="sm" />
                <span className="min-w-0 flex-1 truncate font-semibold text-foreground">
                  {row.display_name ?? 'Learner'}
                  {isMe && <span className="ml-2 text-xs font-medium text-primary">You</span>}
                </span>
                <span className="font-display font-bold text-foreground">⚡ {row.weekly_xp}</span>
              </li>
            )
          })}
        </ol>
      )}
    </div>
  )
}
