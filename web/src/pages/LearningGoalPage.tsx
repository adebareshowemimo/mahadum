import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { Alert, Button, LinkButton, Skeleton } from '@/components/ui'
import { learningApi } from '@/lib/api'
import { useAuth } from '@/lib/auth/AuthProvider'
import { useConfig } from '@/lib/config/useConfig'

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

  if (isLoading || !user) return <Skeleton className="h-80 max-w-2xl" />
  if (!learner) return <div className="mx-auto max-w-2xl space-y-4"><Alert variant="info">{params.has('learner') ? 'This learner profile is unavailable.' : 'Create a learner profile before choosing a learning goal.'}</Alert><LinkButton to="/family">Go to family</LinkButton></div>

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
    } catch {
      setError('Could not save this learning goal. Please try again.')
    } finally {
      setSaving(false)
    }
  }

  return <div className="mx-auto max-w-2xl space-y-6">
    <header><p className="text-sm font-semibold text-primary">Your learning journey</p><h1 className="mt-2 font-display text-3xl font-bold">{config?.learning_goal_question ?? 'Why are you learning?'}</h1><p className="mt-2 text-muted">Choose what matters most to {learner.display_name}. You can change it later.</p></header>
    {error && <Alert variant="danger">{error}</Alert>}
    <div className="grid gap-3 sm:grid-cols-2" role="radiogroup" aria-label="Learning goal">
      {(config?.learning_goals ?? []).map(goal => <label key={goal.key} className={`cursor-pointer rounded-2xl border p-5 ${selected === goal.key || (!selected && learner.learning_goal === goal.key) ? 'border-primary bg-primary-soft' : 'border-border bg-surface'}`}>
        <input className="mr-3" type="radio" name="learning_goal" value={goal.key} checked={(selected ?? learner.learning_goal) === goal.key} onChange={() => setSelected(goal.key)} />
        <span className="font-semibold">{goal.label}</span>
      </label>)}
    </div>
    {!(config?.learning_goals?.length) && <Alert variant="warning">Learning goals are unavailable right now. You can choose one later.</Alert>}
    <div className="flex flex-wrap gap-3"><Button onClick={() => void save()} loading={saving} disabled={!(config?.learning_goals ?? []).some(goal => goal.key === (selected ?? learner.learning_goal))}>Save goal</Button><LinkButton to={destination} variant="ghost">Choose later</LinkButton></div>
  </div>
}
