import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  Alert,
  Avatar,
  Icon,
  learnerAvatarPresetId,
  LinkButton,
  Skeleton,
} from '@/components/ui'
import { cn } from '@/lib/cn'
import { type NodeState, type PathNode } from '@/lib/api'
import { useActiveProfile } from '@/lib/profile/ActiveProfile'
import { usePath } from '@/lib/learning/queries'
import { useHearts, useStreak } from '@/lib/gamification/queries'
import { formatDayStreak } from '@/lib/gamification/format'
import { CourseCatalogPage } from '@/pages/CourseCatalogPage'
import { ProfilePictureModal } from '@/components/learner/ProfilePictureModal'

export function LearnPage() {
  const [pictureOpen, setPictureOpen] = useState(false)
  const { activeLearner } = useActiveProfile()
  const { data: path, isLoading, isError } = usePath(activeLearner?.id)

  if (!activeLearner) {
    return <CourseCatalogPage />
  }

  if (isLoading) return <TreeSkeleton />
  if (isError || !path) {
    return <Alert variant="danger">We couldn’t load the learning path. Please refresh and try again.</Alert>
  }

  const hasPath = path.units.some((u) => u.nodes.length > 0)

  return (
    <div className="flex flex-col gap-8">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <button
            type="button"
            onClick={() => setPictureOpen(true)}
            className="rounded-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
            aria-label={`Change ${activeLearner.display_name}’s profile picture`}
          >
            <Avatar
              name={activeLearner.display_name}
              src={activeLearner.avatar_url ?? undefined}
              avatarId={activeLearner.avatar_id ?? learnerAvatarPresetId(activeLearner.id)}
              size="lg"
            />
          </button>
          <div>
            <h1 className="font-display text-2xl font-bold text-foreground">
              {activeLearner.display_name}’s journey
            </h1>
            {activeLearner.target_language && (
              <p className="text-muted">Learning {activeLearner.target_language.toUpperCase()}</p>
            )}
          </div>
        </div>
        <StatsBar learnerId={activeLearner.id} coinBalance={activeLearner.coin_balance} />
      </div>

      {hasPath && (
        <div className="flex flex-col gap-10">
          {path.units.map((unit, ui) => (
            <section key={`${unit.title}-${ui}`}>
              <h2 className="mb-4 font-display text-sm font-bold uppercase tracking-wide text-subtle">
                {unit.title}
              </h2>
              <ol className="relative flex flex-col gap-3 before:absolute before:left-5 before:top-5 before:bottom-5 before:w-px before:bg-border">
                {unit.nodes.map((node) => (
                  <NodeRow key={node.lesson_id} node={node} learnerId={activeLearner.id} />
                ))}
              </ol>
            </section>
          ))}
          <div className="flex flex-col items-start gap-3 rounded-2xl border border-border bg-surface-muted p-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h2 className="font-display font-bold text-foreground">Ready for something new?</h2>
              <p className="mt-1 text-sm text-muted">Browse the course library without leaving this learning journey behind.</p>
            </div>
            <LinkButton to="/learn/courses" variant="secondary">Browse more courses</LinkButton>
          </div>
        </div>
      )}

      {!hasPath && (
        <div className="rounded-2xl border border-dashed border-border-strong bg-surface px-6 py-14 text-center">
          <span className="mx-auto flex size-14 items-center justify-center rounded-2xl bg-primary-soft text-primary">
            <Icon name="layers" className="size-7" />
          </span>
          <h2 className="mt-4 font-display text-xl font-bold text-foreground">Choose a first course</h2>
          <p className="mx-auto mt-2 max-w-md text-sm text-muted">
            Search the learning library by language and level, then add a course to {activeLearner.display_name}’s journey.
          </p>
          <LinkButton to="/learn/courses" className="mt-5">Explore courses</LinkButton>
        </div>
      )}
      <ProfilePictureModal learner={activeLearner} open={pictureOpen} onClose={() => setPictureOpen(false)} />
    </div>
  )
}

const NODE_STYLES: Record<NodeState, { ring: string; icon: 'cap' | 'book' | 'shield' }> = {
  completed: { ring: 'bg-leaf-100 text-leaf-700 ring-2 ring-leaf-400', icon: 'cap' },
  active: { ring: 'bg-primary text-primary-fg ring-4 ring-primary-soft', icon: 'book' },
  locked: { ring: 'bg-surface-muted text-subtle', icon: 'shield' },
}

function NodeRow({ node, learnerId: _learnerId }: { node: PathNode; learnerId: number }) {
  const navigate = useNavigate()
  const style = NODE_STYLES[node.state]
  const interactive = node.state !== 'locked'

  return (
    <li className="relative z-10">
      <button
        disabled={!interactive}
        onClick={() => navigate(`/learn/lessons/${node.lesson_id}`)}
        className={cn(
          'flex w-full items-center gap-4 rounded-2xl border p-3 text-left transition-colors',
          interactive ? 'border-border bg-surface hover:bg-surface-muted' : 'border-transparent opacity-70',
        )}
      >
        <span className={cn('flex size-10 shrink-0 items-center justify-center rounded-full', style.ring)}>
          {node.state === 'completed' ? '✓' : <Icon name={style.icon} className="size-5" />}
        </span>
        <span className="min-w-0 flex-1">
          <span className="block truncate font-semibold text-foreground">{node.title}</span>
          <span className="text-xs capitalize text-muted">{node.state}</span>
        </span>
        {node.state === 'active' && <span className="text-sm font-semibold text-primary">Start →</span>}
        {node.state === 'completed' && <span className="text-sm text-muted">Replay</span>}
        {node.state === 'locked' && <Icon name="shield" className="size-4 text-subtle" />}
      </button>
    </li>
  )
}

function StatsBar({ learnerId, coinBalance }: { learnerId: number; coinBalance: number }) {
  const streak = useStreak(learnerId)
  const hearts = useHearts(learnerId)
  return (
    <div className="flex items-center gap-3">
      <span className="flex items-center gap-1.5 rounded-full bg-surface-muted px-3 py-1.5 text-sm font-bold text-foreground">
        🔥 {formatDayStreak(streak.data?.count ?? 0)}
      </span>
      <span className="flex items-center gap-1.5 rounded-full bg-surface-muted px-3 py-1.5 text-sm font-bold text-foreground">
        ❤️ {hearts.data?.current ?? 0}
      </span>
      <span className="flex items-center gap-1.5 rounded-full bg-surface-muted px-3 py-1.5 text-sm font-bold text-foreground">
        <Icon name="coin" className="size-4 text-gold-600" />
        {coinBalance.toLocaleString()}
      </span>
    </div>
  )
}

function TreeSkeleton() {
  return (
    <div className="flex flex-col gap-4">
      <Skeleton className="h-10 w-56" />
      <Skeleton className="h-16" />
      <Skeleton className="h-16" />
      <Skeleton className="h-16" />
    </div>
  )
}
