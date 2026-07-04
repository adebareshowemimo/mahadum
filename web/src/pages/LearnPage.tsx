import { useNavigate } from 'react-router-dom'
import {
  Alert,
  Avatar,
  Button,
  Card,
  CardBody,
  Icon,
  Skeleton,
} from '@/components/ui'
import { cn } from '@/lib/cn'
import { ApiError, type CourseSummary, type NodeState, type PathNode } from '@/lib/api'
import { useActiveProfile } from '@/lib/profile/ActiveProfile'
import { useCourses, useEnroll, usePath } from '@/lib/learning/queries'
import { useHearts, useStreak } from '@/lib/gamification/queries'

export function LearnPage() {
  const { activeLearner } = useActiveProfile()
  const { data: path, isLoading, isError } = usePath(activeLearner?.id)

  if (!activeLearner) {
    return (
      <Card>
        <CardBody className="flex flex-col items-center gap-3 py-12 text-center">
          <span className="text-4xl" aria-hidden="true">
            🧑‍🎓
          </span>
          <h1 className="font-display text-xl font-bold text-foreground">Choose a learner</h1>
          <p className="max-w-xs text-sm text-muted">
            Pick a profile from the switcher in the top bar to start learning.
          </p>
        </CardBody>
      </Card>
    )
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
          <Avatar name={activeLearner.display_name} size="lg" />
          <div>
            <h1 className="font-display text-2xl font-bold text-foreground">
              {activeLearner.display_name}’s journey
            </h1>
            {activeLearner.target_language && (
              <p className="text-muted">Learning {activeLearner.target_language.toUpperCase()}</p>
            )}
          </div>
        </div>
        <StatsBar learnerId={activeLearner.id} />
      </div>

      {hasPath ? (
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
        </div>
      ) : (
        <EnrollCard learnerId={activeLearner.id} />
      )}
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

function EnrollCard({ learnerId }: { learnerId: number }) {
  const { data: courses, isLoading } = useCourses()
  const enroll = useEnroll(learnerId)

  if (isLoading) return <Skeleton className="h-40" />

  const available = (courses ?? []).filter((c: CourseSummary) => c.is_published)

  return (
    <Card>
      <CardBody className="flex flex-col gap-4">
        <div className="text-center">
          <span className="text-4xl" aria-hidden="true">
            🚀
          </span>
          <h2 className="mt-2 font-display text-xl font-bold text-foreground">Start a course</h2>
          <p className="mx-auto mt-1 max-w-sm text-sm text-muted">
            Choose a course to begin the learning journey.
          </p>
        </div>

        {enroll.isError && (
          <Alert variant="danger">
            {enroll.error instanceof ApiError ? enroll.error.message : 'Could not enroll. Please try again.'}
          </Alert>
        )}

        {available.length === 0 ? (
          <p className="text-center text-sm text-muted">No courses are available yet.</p>
        ) : (
          <div className="grid gap-3 sm:grid-cols-2">
            {available.map((course) => (
              <div key={course.id} className="flex flex-col gap-2 rounded-xl border border-border p-4">
                <div>
                  <p className="font-semibold text-foreground">{course.title}</p>
                  {course.description && (
                    <p className="mt-0.5 line-clamp-2 text-sm text-muted">{course.description}</p>
                  )}
                </div>
                <Button
                  size="sm"
                  className="mt-auto"
                  loading={enroll.isPending && enroll.variables === course.id}
                  onClick={() => enroll.mutate(course.id)}
                >
                  Start course
                </Button>
              </div>
            ))}
          </div>
        )}
      </CardBody>
    </Card>
  )
}

function StatsBar({ learnerId }: { learnerId: number }) {
  const streak = useStreak(learnerId)
  const hearts = useHearts(learnerId)
  return (
    <div className="flex items-center gap-3">
      <span className="flex items-center gap-1.5 rounded-full bg-surface-muted px-3 py-1.5 text-sm font-bold text-foreground">
        🔥 {streak.data?.count ?? 0}
      </span>
      <span className="flex items-center gap-1.5 rounded-full bg-surface-muted px-3 py-1.5 text-sm font-bold text-foreground">
        ❤️ {hearts.data?.current ?? 0}
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
