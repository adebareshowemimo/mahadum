import { useState, type FormEvent } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Alert, Button, Card, CardBody, Skeleton } from '@/components/ui'
import { ApiError, learningApi } from '@/lib/api'
import { useActiveProfile } from '@/lib/profile/ActiveProfile'

export function PlacementPage() {
  const [params] = useSearchParams()
  const courseId = Number(params.get('course'))
  const languageId = Number(params.get('language'))
  const { activeLearner } = useActiveProfile()
  const navigate = useNavigate()
  const [answers, setAnswers] = useState<Record<number, number>>({})
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const assessment = useQuery({
    queryKey: ['placement', activeLearner?.id, languageId],
    queryFn: () => learningApi.placementQuestions(activeLearner!.id, languageId),
    enabled: !!activeLearner && languageId > 0 && courseId > 0,
  })

  async function begin(event?: FormEvent) {
    event?.preventDefault()
    if (!activeLearner || !assessment.data) return
    setBusy(true)
    setError(null)
    try {
      if (assessment.data.available && !assessment.data.result_level) {
        await learningApi.submitPlacement(activeLearner.id, languageId,
          assessment.data.questions.map((question) => ({ question_id: question.id, option_id: answers[question.id] })))
      }
      await learningApi.enroll(activeLearner.id, courseId)
      navigate('/learn')
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not save your placement. Please try again.')
    } finally {
      setBusy(false)
    }
  }

  if (!activeLearner || courseId <= 0 || languageId <= 0) return <Alert variant="warning">Choose a learner and course first.</Alert>
  if (assessment.isLoading) return <Skeleton className="h-64" />
  if (assessment.isError || !assessment.data) return <Alert variant="danger">Could not load the placement check.</Alert>
  const data = assessment.data

  return <main className="mx-auto flex max-w-2xl flex-col gap-6">
    <header><p className="text-sm font-semibold text-primary">Find your starting point</p><h1 className="mt-1 font-display text-3xl font-bold">A short placement check</h1><p className="mt-2 text-muted">Listen and read, then choose an answer for each question. This chooses Beginner or Intermediate; your subscription access still applies.</p></header>
    {error && <Alert variant="danger">{error}</Alert>}
    {data.result_level && <Alert variant="success">Your starting point is {data.result_level}. Continue to your course.</Alert>}
    {!data.available && !data.result_level && <Alert variant="info">A placement check is not ready for this language yet. You can start at Beginner.</Alert>}
    {data.available && !data.result_level && <form className="space-y-5" onSubmit={(event) => void begin(event)}>
      {data.questions.map((question, index) => <Card key={question.id}><CardBody className="space-y-3"><h2 className="font-semibold">{index + 1}. {question.prompt}</h2>{question.prompt_audio && <audio controls src={question.prompt_audio} aria-label={`Audio prompt for question ${index + 1}`} className="w-full" />}
        <div className="grid gap-2">{question.options.map((option) => <label key={option.id} className="flex cursor-pointer items-center gap-3 rounded-xl border border-border p-3"><input type="radio" name={`question-${question.id}`} value={option.id} checked={answers[question.id] === option.id} onChange={() => setAnswers((current) => ({ ...current, [question.id]: option.id }))} /><span>{option.label}</span></label>)}</div>
      </CardBody></Card>)}
      <Button type="submit" loading={busy} disabled={data.questions.some((question) => !answers[question.id])}>See my starting point</Button>
    </form>}
    {(!data.available || data.result_level) && <Button loading={busy} onClick={() => void begin()}>{data.result_level ? 'Continue to course' : 'Start at Beginner'}</Button>}
  </main>
}
