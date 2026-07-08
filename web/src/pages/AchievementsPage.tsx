import { useState } from 'react'
import {
  Alert,
  Badge,
  Button,
  Card,
  CardBody,
  CardHeader,
  CardTitle,
  Skeleton,
} from '@/components/ui'
import { cn } from '@/lib/cn'
import type { LearnerProfile } from '@/lib/api'
import { ActiveLearnerGate } from '@/components/learner/ActiveLearnerGate'
import { AdModal } from '@/components/gamification/AdModal'
import {
  useArmShield,
  useBadges,
  useHearts,
  useLeagueCurrent,
  useRefillHearts,
  useStreak,
} from '@/lib/gamification/queries'

const MAX_HEARTS = 5

export function AchievementsPage() {
  return <ActiveLearnerGate>{(learner) => <Achievements learner={learner} />}</ActiveLearnerGate>
}

function Achievements({ learner }: { learner: LearnerProfile }) {
  const streak = useStreak(learner.id)
  const hearts = useHearts(learner.id)
  const badges = useBadges(learner.id)
  const league = useLeagueCurrent(learner.id)
  const armShield = useArmShield(learner.id)
  const refill = useRefillHearts(learner.id)
  const [adOpen, setAdOpen] = useState(false)

  return (
    <div className="flex flex-col gap-8">
      <h1 className="font-display text-2xl font-bold text-foreground">
        {learner.display_name}’s achievements
      </h1>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {/* Streak — framed as protected, never punitive */}
        <Card>
          <CardHeader>
            <CardTitle>Streak</CardTitle>
          </CardHeader>
          <CardBody className="flex flex-col gap-3">
            {streak.isLoading ? (
              <Skeleton className="h-16" />
            ) : (
              <>
                <div className="flex items-baseline gap-2">
                  <span className="text-4xl">🔥</span>
                  <span className="font-display text-3xl font-extrabold text-foreground">
                    {streak.data?.count ?? 0}
                  </span>
                  <span className="text-muted">day{(streak.data?.count ?? 0) === 1 ? '' : 's'}</span>
                </div>
                <p className="text-sm text-muted">
                  Longest: {streak.data?.longest ?? 0} days ·{' '}
                  <span className="font-medium capitalize text-foreground">{streak.data?.state}</span>
                </p>
                <Button
                  variant="reward"
                  size="sm"
                  leftIcon={<span aria-hidden="true">🛡️</span>}
                  loading={armShield.isPending}
                  onClick={() => armShield.mutate()}
                >
                  Protect streak
                </Button>
                {armShield.isSuccess && <p className="text-xs text-success">Streak shield armed 🛡️</p>}
              </>
            )}
          </CardBody>
        </Card>

        {/* Hearts */}
        <Card>
          <CardHeader>
            <CardTitle>Hearts</CardTitle>
          </CardHeader>
          <CardBody className="flex flex-col gap-3">
            {hearts.isLoading ? (
              <Skeleton className="h-16" />
            ) : (
              <>
                <p className="text-3xl" aria-label={`${hearts.data?.current ?? 0} of ${MAX_HEARTS} hearts`}>
                  {'❤️'.repeat(hearts.data?.current ?? 0)}
                  {'🤍'.repeat(Math.max(0, MAX_HEARTS - (hearts.data?.current ?? 0)))}
                </p>
                <p className="text-sm text-muted">
                  Hearts make practice playful — they never block learning.
                </p>
                {(hearts.data?.current ?? 0) < MAX_HEARTS && (
                  <div className="flex gap-2">
                    <Button size="sm" variant="outline" onClick={() => setAdOpen(true)}>
                      Watch ad
                    </Button>
                    <Button
                      size="sm"
                      variant="billing"
                      loading={refill.isPending}
                      onClick={() => refill.mutate({ method: 'coins' })}
                    >
                      Use coins
                    </Button>
                  </div>
                )}
              </>
            )}
          </CardBody>
        </Card>

        {/* Weekly XP / league */}
        <Card>
          <CardHeader>
            <CardTitle>This week</CardTitle>
          </CardHeader>
          <CardBody className="flex flex-col gap-3">
            {league.isLoading ? (
              <Skeleton className="h-16" />
            ) : (
              <>
                <div className="flex items-baseline gap-2">
                  <span className="text-3xl">⚡</span>
                  <span className="font-display text-3xl font-extrabold text-foreground">
                    {league.data?.weekly_xp ?? 0}
                  </span>
                  <span className="text-muted">XP</span>
                </div>
                <p className="text-sm text-muted">
                  {league.data?.league?.name ?? 'League'} · rank{' '}
                  <span className="font-medium text-foreground">{league.data?.rank ?? '—'}</span>
                </p>
              </>
            )}
          </CardBody>
        </Card>
      </div>

      {/* Badges */}
      <section>
        <h2 className="mb-3 font-display text-lg font-bold text-foreground">Badges</h2>
        {badges.isLoading ? (
          <Skeleton className="h-24" />
        ) : badges.isError ? (
          <Alert variant="danger">Couldn’t load badges.</Alert>
        ) : (
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            {badges.data?.earned.map((b) => (
              <BadgeTile key={b.code} name={b.name} earned />
            ))}
            {badges.data?.locked.map((b) => (
              <BadgeTile key={b.code} name={b.name} description={b.description} />
            ))}
            {(badges.data?.earned.length ?? 0) + (badges.data?.locked.length ?? 0) === 0 && (
              <p className="text-sm text-muted">No badges yet — keep learning to earn your first!</p>
            )}
          </div>
        )}
      </section>

      <AdModal
        open={adOpen}
        learnerId={learner.id}
        placement="rewarded_heart"
        onClose={() => setAdOpen(false)}
        onRewarded={(impressionId) => refill.mutate({ method: 'ad', adImpressionId: impressionId })}
      />
    </div>
  )
}

function BadgeTile({
  name,
  description,
  earned = false,
}: {
  name: string
  description?: string | null
  earned?: boolean
}) {
  return (
    <div
      className={cn(
        'flex flex-col items-center gap-2 rounded-2xl border p-4 text-center',
        earned ? 'border-gold-300 bg-gold-50' : 'border-border bg-surface opacity-70',
      )}
      title={description ?? undefined}
    >
      <span className={cn('text-3xl', !earned && 'grayscale')} aria-hidden="true">
        {earned ? '🏅' : '🔒'}
      </span>
      <span className="text-sm font-semibold text-foreground">{name}</span>
      {earned ? (
        <Badge variant="gold">Earned</Badge>
      ) : (
        description && <span className="line-clamp-2 text-xs text-muted">{description}</span>
      )}
    </div>
  )
}
