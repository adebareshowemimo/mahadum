import { useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import { Alert, Button3D, Card, CardBody, Skeleton } from '@/components/ui'
import { ApiError, learningApi, type TonePracticeInvitation } from '@/lib/api'

export function TonePracticeInvitationPage() {
  const { token = '' } = useParams()
  const [invitation, setInvitation] = useState<TonePracticeInvitation | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    learningApi.tonePracticeInvitation(token)
      .then(setInvitation)
      .catch((err) => setError(err instanceof ApiError ? err.message : 'This invitation is unavailable.'))
  }, [token])

  async function accept() {
    setBusy(true)
    try {
      setInvitation(await learningApi.acceptTonePracticeInvitation(token))
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not accept this invitation.')
    } finally {
      setBusy(false)
    }
  }

  if (error) return <div className="mx-auto max-w-lg p-6"><Alert variant="danger">{error}</Alert></div>
  if (!invitation) return <Skeleton className="m-6 h-64" />

  return (
    <div className="mx-auto flex min-h-screen max-w-xl items-center p-6">
      <Card className="w-full">
        <CardBody className="flex flex-col gap-5 p-6 text-center">
          <span className="text-5xl" aria-hidden="true">🗣️</span>
          <div>
            <h1 className="font-display text-2xl font-bold text-foreground">Tone-practice invitation</h1>
            <p className="mt-2 text-muted">{invitation.inviter_name} invited you to help with “{invitation.lesson_title}”.</p>
          </div>
          <div className="rounded-2xl bg-surface-muted p-5 font-display text-2xl font-bold text-foreground">
            {invitation.practice_text}
          </div>
          <p className="text-xs text-muted">No child contact details or internal learner identifiers are shared.</p>
          {invitation.accepted
            ? <Alert variant="success">Accepted — you can now practice this phrase together.</Alert>
            : <Button3D variant="reward" fullWidth disabled={busy} onClick={accept}>{busy ? 'Accepting…' : 'Accept invitation'}</Button3D>}
        </CardBody>
      </Card>
    </div>
  )
}
