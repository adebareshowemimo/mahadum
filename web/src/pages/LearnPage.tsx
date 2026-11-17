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
import { PracticeWithMeCard } from '@/components/learner/PracticeWithMeCard'

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
            {activeLearner.is_child && activeLearner.school_name && (
              <p className="text-muted">School: {activeLearner.school_name}</p>
            )}
          </div>
        </div>
        <StatsBar learnerId={activeLearner.id} coinBalance={activeLearner.coin_balance} />
      </div>

      {path.learning_goal ? <section className="rounded-2xl border border-border bg-surface p-5" aria-label="Learning goal progress">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div><p className="text-sm font-semibold text-primary">Learning for {path.learning_goal.label}</p><h2 className="mt-1 font-display text-xl font-bold">{path.completed_lessons ?? 0} lessons completed</h2><p className="mt-1 text-sm text-muted">{path.learning_goal.message}</p></div>
          {!activeLearner.is_child && <LinkButton to={`/learning-goal?learner=${activeLearner.id}`} variant="ghost" size="sm">Change goal</LinkButton>}
        </div>
        {!!path.suggested_lessons?.length && <div className="mt-4"><p className="text-sm font-semibold">Suggested for your goal</p><ul className="mt-2 space-y-2">{path.suggested_lessons.map(lesson => <li key={lesson.lesson_id}><LinkButton to={`/learn/lessons/${lesson.lesson_id}`} variant="outline" size="sm">{lesson.title}</LinkButton></li>)}</ul></div>}
        {!path.suggested_lessons?.length && <p className="mt-3 text-sm text-muted">We’ll suggest matching lessons as they become available in your learning path.</p>}
      </section> : <div className="rounded-2xl border border-border bg-surface p-5"><p className="font-semibold">Make this journey yours</p><p className="mt-1 text-sm text-muted">Choose why you are learning to see relevant lessons and progress messages.</p>{activeLearner.is_child ? <p className="mt-2 text-sm">Ask a grown-up to choose your learning goal.</p> : <LinkButton to={`/learning-goal?learner=${activeLearner.id}`} variant="secondary" size="sm" className="mt-3">Choose learning goal</LinkButton>}</div>}

      {hasPath && (
        <div className="flex flex-col gap-10">
          {path.units.map((unit, ui) => (
            <section key={`${unit.title}-${ui}`}>
              <h2 className="mb-4 font-display text-sm font-bold uppercase tracking-wide text-subtle">
                {unit.title}
                {unit.nodes.length > 0 && <span className="ml-2 text-xs normal-case">{unit.nodes.every(node => node.is_free) ? 'Free' : 'Paid'}</span>}
              </h2>
              <ol className="relative flex flex-col gap-3 before:absolute before:left-5 before:top-5 before:bottom-5 before:w-px before:bg-border">
                {unit.nodes.map((node) => (
                  <NodeRow key={node.lesson_id} node={node} learnerId={activeLearner.id} />
                ))}
              </ol>
            </section>
          ))}
          <PracticeWithMeCard learnerId={activeLearner.id} />
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
  const { activeLearner } = useActiveProfile()
  const style = NODE_STYLES[node.state]
  const interactive = (node.state !== 'locked' || !!node.access_reason) && !(activeLearner?.is_child && !!node.access_reason)

  return (
    <li className="relative z-10">
      <button
        disabled={!interactive}
        onClick={() => navigate(node.access_reason ? '/billing' : `/learn/lessons/${node.lesson_id}`)}
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
          <span className="text-xs capitalize text-muted">{node.access_reason ? activeLearner?.is_child ? 'Ask a grown-up' : 'Upgrade to unlock' : node.state}</span>
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
        ❤️ {hearts.data?.unlimited_hearts ? '∞' : hearts.data?.current ?? 0}
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
