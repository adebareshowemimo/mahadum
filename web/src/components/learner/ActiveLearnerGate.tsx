import type { ReactNode } from 'react'
import { Card, CardBody } from '@/components/ui'
import type { LearnerProfile } from '@/lib/api'
import { useActiveProfile } from '@/lib/profile/ActiveProfile'

/**
 * Renders learner-scoped content for the active profile, or a prompt to pick one
 * from the top-bar switcher when none is active.
 */
export function ActiveLearnerGate({ children }: { children: (learner: LearnerProfile) => ReactNode }) {
  const { activeLearner } = useActiveProfile()

  if (!activeLearner) {
    return (
      <Card>
        <CardBody className="flex flex-col items-center gap-3 py-12 text-center">
          <span className="text-4xl" aria-hidden="true">
            🧑‍🎓
          </span>
          <h1 className="font-display text-xl font-bold text-foreground">Choose a learner</h1>
          <p className="max-w-xs text-sm text-muted">
            Pick a profile from the switcher in the top bar to see their progress.
          </p>
        </CardBody>
      </Card>
    )
  }

  return <>{children(activeLearner)}</>
}
