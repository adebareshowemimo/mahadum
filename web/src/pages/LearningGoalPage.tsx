import { useState, type ReactNode } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { Alert, Button, Icon, LinkButton, Skeleton, type IconName } from '@/components/ui'
import { AuthLayout } from '@/components/auth/AuthLayout'
import { ApiError, learningApi } from '@/lib/api'
import { useAuth } from '@/lib/auth/AuthProvider'
import { useConfig } from '@/lib/config/useConfig'
import { cn } from '@/lib/cn'

const GOAL_ICONS: Record<string, IconName> = {
  grandparents: 'users',
  travel: 'home',
  school: 'cap',
  business: 'building',
}

function GoalLayout({ title, subtitle, children }: { title: string; subtitle: string; children: ReactNode }) {
  return <AuthLayout
    onboarding
    eyebrow="One more step"
    title={title}
    subtitle={subtitle}
    image="/images/landing-v1-family-call.webp"
    imageAlt="A family sharing language together across generations"
    imagePosition="object-[68%_center]"
    visualTitle="Your reason makes every word matter."
    visualBody="Choose a goal that feels like yours. We’ll help you find lessons that bring it closer."
    phrases={['Nnọọ', 'Báwo ni?', 'Sannu']}
  >{children}</AuthLayout>
}

export function LearningGoalPage() {
  const { user, refresh } = useAuth()
  const { data: config, isLoading } = useConfig()
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const requestedId = Number(params.get('learner'))
  const learners = [...(user?.learner_profiles ?? []), ...(user?.families ?? []).flatMap(family => family.learners ?? [])]
  const learner = params.has('learner')
    ? learners.find(item => item.id === requestedId)
    : user?.learner_profiles?.[0]
  const [selected, setSelected] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const destination = learner?.is_child ? `/family/children/${learner.id}` : '/learn'

  if (isLoading || !user) return <GoalLayout title="Your learning journey" subtitle="Getting your profile ready…"><Skeleton className="h-80" /></GoalLayout>
  if (!learner) return <GoalLayout title="Your learning journey" subtitle="Choose a learner profile first."><div className="space-y-4"><Alert variant="info">{params.has('learner') ? 'This learner profile is unavailable.' : 'Create a learner profile before choosing a learning goal.'}</Alert><LinkButton to="/family">Go to family</LinkButton></div></GoalLayout>

  async function save() {
    const choice = selected ?? learner?.learning_goal
    if (!learner || !choice) return
    setSaving(true)
    setError(null)
    try {
      await learningApi.setLearningGoal(learner.id, choice)
      await refresh()
      await queryClient.invalidateQueries({ queryKey: ['learner-path'] })
      navigate(destination, { replace: true })
    } catch (err) {
      setError(err instanceof ApiError && err.status < 500 ? err.message : 'We couldn’t save your goal. Please try again.')
    } finally {
      setSaving(false)
    }
  }

  return <GoalLayout title={config?.learning_goal_question ?? 'Why are you learning?'} subtitle={`Choose what matters most to ${learner.display_name}. You can change it later.`}>
    <form onSubmit={event => { event.preventDefault(); void save() }} className="space-y-4">
      <p className="text-sm font-semibold text-navy-700">We’ll use your choice for lesson suggestions and progress messages.</p>
      {error && <Alert variant="danger">{error}</Alert>}
      <fieldset className="space-y-2">
        <legend className="mb-2 font-display text-lg font-extrabold text-navy-950">Choose one goal</legend>
        {(config?.learning_goals ?? []).map(goal => {
          const isSelected = (selected ?? learner.learning_goal) === goal.key
          return <label key={goal.key} className={cn(
            'flex min-h-16 cursor-pointer items-center gap-3 rounded-xl border-2 p-3 transition-colors focus-within:outline-none focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2',
            isSelected ? 'border-primary bg-chore-50' : 'border-border bg-white hover:border-chore-300 hover:bg-chore-50/40',
          )}>
            <input className="sr-only" type="radio" name="learning_goal" value={goal.key} checked={isSelected} onChange={() => setSelected(goal.key)} />
            <span aria-hidden="true" className={cn('flex size-10 shrink-0 items-center justify-center rounded-full', isSelected ? 'bg-primary text-white' : 'bg-surface-muted text-chore-700')}><Icon name={GOAL_ICONS[goal.key] ?? 'sparkles'} className="size-5" /></span>
            <span className="min-w-0 flex-1"><span className="block font-display text-base font-extrabold text-navy-950">{goal.label}</span><span className="mt-0.5 block text-sm font-semibold leading-snug text-navy-600">{goal.message}</span></span>
            <span aria-hidden="true" className={cn('size-5 shrink-0 rounded-full border-2', isSelected ? 'border-[6px] border-primary bg-white' : 'border-border-strong bg-white')} />
          </label>
        })}
      </fieldset>
      {!(config?.learning_goals?.length) && <Alert variant="warning">Learning goals are unavailable right now. You can choose one later.</Alert>}
      <Button type="submit" variant="parent" size="lg" fullWidth loading={saving} disabled={!(config?.learning_goals ?? []).some(goal => goal.key === (selected ?? learner.learning_goal))}>Continue with my goal</Button>
      <LinkButton to={destination} variant="ghost" className="w-full">Choose later</LinkButton>
    </form>
  </GoalLayout>
}
