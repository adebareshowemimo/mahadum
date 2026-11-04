import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link, useParams } from 'react-router-dom'
import { Alert, Button, Card, CardBody, Skeleton } from '@/components/ui'
import { familyApi, schoolApi } from '@/lib/api'
import { useAuth } from '@/lib/auth/AuthProvider'

export function SchoolJoinPage() {
  const { token = '' } = useParams()
  const { status, hasRole, user } = useAuth()
  const info = useQuery({ queryKey: ['school-join-preview', token], queryFn: () => schoolApi.schoolJoinPreview(token), enabled: token.length === 48, retry: false })
  const parent = status === 'authenticated' && hasRole('parent')
  const family = useQuery({ queryKey: ['school-join-family', user?.user.id], queryFn: familyApi.overview, enabled: parent })
  const [learnerId, setLearnerId] = useState<number | null>(null)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [joined, setJoined] = useState(false)

  async function join() {
    setBusy(true)
    setError('')
    try {
      await schoolApi.joinSchool(token, parent ? learnerId ?? undefined : undefined)
      setJoined(true)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not join this school.')
    } finally {
      setBusy(false)
    }
  }

  return <main className="mx-auto flex min-h-screen max-w-xl items-center px-4 py-10"><Card className="w-full"><CardBody className="space-y-5">
    <h1 className="font-display text-2xl font-bold">Join your school</h1>
    {token.length !== 48 && <Alert variant="danger">This school link is invalid. Ask your school for a new link.</Alert>}
    {info.isLoading && <Skeleton className="h-20" />}
    {info.isError && <Alert variant="danger">This school link is invalid, expired or disabled. Ask your school for a new link.</Alert>}
    {info.data && <>
      <p className="text-muted">Join <strong>{info.data.school}</strong> to learn with your school.</p>
      {joined ? <Alert variant="success">You joined {info.data.school}. Your school can now add you to a class.</Alert> : status === 'loading' ? <Skeleton className="h-12" /> : status === 'authenticated' ? <>
        {!user?.user.email_verified && <Alert variant="warning">Verify your email before joining. <Link to="/verify-email" className="underline">Verify email</Link></Alert>}
        {parent && <div><label htmlFor="join-child" className="mb-1 block text-sm font-semibold">Choose a child</label><select id="join-child" className="w-full rounded-lg border border-border bg-surface p-3" value={learnerId ?? ''} onChange={(event) => setLearnerId(Number(event.target.value) || null)}><option value="">Select a child</option>{family.data?.learners.filter((learner) => learner.is_child).map((learner) => <option key={learner.id} value={learner.id}>{learner.display_name}</option>)}</select>{family.isError && <Alert variant="warning">Switch back to your parent profile to choose a child.</Alert>}{family.data && !family.data.learners.some((learner) => learner.is_child) && <p className="mt-2 text-sm text-muted">Add a child on your Family page first.</p>}</div>}
        {error && <Alert variant="danger">{error}</Alert>}
        <Button onClick={join} loading={busy} disabled={!user?.user.email_verified || (parent && !learnerId) || (!parent && !hasRole('student'))}>Join {info.data.school}</Button>
      </> : <div className="flex gap-3"><Link className="rounded-lg bg-primary px-4 py-3 font-semibold text-white" to="/login" state={{ from: { pathname: `/school-join/${token}` } }}>Sign in</Link><Link className="rounded-lg border border-border px-4 py-3 font-semibold" to={`/register?school_join=${encodeURIComponent(token)}`}>Create account</Link></div>}
      {joined && <p><Link className="text-primary underline" to={parent ? '/family' : '/learn'}>Continue learning</Link></p>}
    </>}
  </CardBody></Card></main>
}
