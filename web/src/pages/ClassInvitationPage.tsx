import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { useMutation, useQuery } from '@tanstack/react-query'
import { Alert, Button, Card, CardBody, LinkButton, Skeleton } from '@/components/ui'
import { ApiError, classInvitationApi } from '@/lib/api'
import { useAuth } from '@/lib/auth/AuthProvider'

export function ClassInvitationPage() {
  const token = useParams().token ?? ''
  const { status, refresh } = useAuth()
  const [accepted, setAccepted] = useState(false)
  const invitation = useQuery({
    queryKey: ['class-invitation', token],
    queryFn: () => classInvitationApi.show(token),
    enabled: token.length === 64,
    retry: false,
  })
  const accept = useMutation({
    mutationFn: () => classInvitationApi.accept(token),
    onSuccess: async () => { setAccepted(true); await refresh() },
  })

  return (
    <main className="grid min-h-dvh place-items-center bg-surface-muted px-4 py-10">
      <Card className="w-full max-w-xl">
        <CardBody className="flex flex-col gap-5 p-6 sm:p-8">
          <Link to="/" className="font-display text-xl font-bold text-foreground">MAHADUM.360</Link>
          {invitation.isLoading || status === 'loading' ? <Skeleton className="h-48" /> : invitation.isError || !invitation.data ? (
            <Alert variant="danger">This invitation link is invalid or no longer available.</Alert>
          ) : (
            <>
              <div>
                <p className="text-sm font-semibold text-primary">Class invitation</p>
                <h1 className="mt-1 font-display text-2xl font-bold text-foreground">Join {invitation.data.class_name}</h1>
                <p className="mt-2 text-muted">{invitation.data.name}, you’ve been invited to learn with {invitation.data.organization_name}.</p>
              </div>
              {(accepted || invitation.data.status === 'accepted') ? (
                <Alert variant="success">You have joined this class. Assigned courses are now available in Learn.</Alert>
              ) : invitation.data.status === 'expired' ? (
                <Alert variant="danger">This invitation has expired. Ask your teacher to send a new one.</Alert>
              ) : status === 'authenticated' ? (
                <>
                  {accept.error && <Alert variant="danger">{accept.error instanceof ApiError ? accept.error.message : 'Could not join this class.'}</Alert>}
                  <Button onClick={() => accept.mutate()} loading={accept.isPending}>Join class</Button>
                </>
              ) : (
                <div className="flex flex-col gap-3 sm:flex-row">
                  <LinkButton to={`/login?class_invitation=${token}`}>Sign in to join</LinkButton>
                  <LinkButton variant="outline" to={`/register?class_invitation=${token}`}>Register to join</LinkButton>
                </div>
              )}
              {(accepted || invitation.data.status === 'accepted') && <LinkButton to="/learn">Go to Learn</LinkButton>}
            </>
          )}
        </CardBody>
      </Card>
    </main>
  )
}
