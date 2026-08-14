import { useState, type FormEvent } from 'react'
import { Link, useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Alert, Button, Card, CardBody, Input, Skeleton } from '@/components/ui'
import { ApiError, schoolApi } from '@/lib/api'
import { useInviteClassLearner } from '@/lib/school/queries'

export function InviteClassLearnerPage() {
  const classId = Number(useParams().classId)
  const classroom = useQuery({
    queryKey: ['school-classes', 'detail', classId],
    queryFn: () => schoolApi.classDetail(classId),
    enabled: Number.isInteger(classId) && classId > 0,
  })
  const invite = useInviteClassLearner(classId)
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [result, setResult] = useState<{ email: string; deliveryStatus: 'queued' | 'not_configured' } | null>(null)

  async function submit(event: FormEvent) {
    event.preventDefault()
    setError(null)
    try {
      const result = await invite.mutateAsync({ name: name.trim(), email: email.trim() })
      setResult({ email: result.email, deliveryStatus: result.delivery_status })
      setName('')
      setEmail('')
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not send this invitation.')
    }
  }

  if (classroom.isLoading) return <Skeleton className="h-64" />
  if (classroom.isError || !classroom.data) return <Alert variant="danger">Couldn’t load this class.</Alert>

  return (
    <div className="mx-auto flex w-full max-w-2xl flex-col gap-5">
      <div>
        <Link to="/classes" className="text-sm font-semibold text-primary hover:underline">← Back to classes</Link>
        <h1 className="mt-3 font-display text-2xl font-bold text-foreground">Invite learner</h1>
        <p className="mt-1 text-muted">Send an invitation to join {classroom.data.name}. Existing users sign in; new learners register first.</p>
      </div>
      <Card>
        <CardBody>
          <form className="flex flex-col gap-4" onSubmit={submit}>
            {result?.deliveryStatus === 'queued' && <Alert variant="success">Invitation queued for {result.email}. The link expires in 7 days.</Alert>}
            {result?.deliveryStatus === 'not_configured' && (
              <Alert variant="warning">Invitation created for {result.email}, but email delivery is not configured. Ask a platform administrator to connect a mail provider before relying on inbox delivery.</Alert>
            )}
            {error && <Alert variant="danger">{error}</Alert>}
            <Input label="Learner name" value={name} onChange={(e) => setName(e.target.value)} autoComplete="name" required autoFocus />
            <Input label="Learner email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} autoComplete="email" required />
            <div className="flex justify-end">
              <Button type="submit" loading={invite.isPending} disabled={!name.trim() || !email.trim()}>Send invitation</Button>
            </div>
          </form>
        </CardBody>
      </Card>
    </div>
  )
}
