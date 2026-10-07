import { useQuery, useMutation } from '@tanstack/react-query'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { AuthLayout } from '@/components/auth/AuthLayout'
import { Alert, Button, Skeleton } from '@/components/ui'
import { batch4Api } from '@/lib/batch4/api'
import { useAuth } from '@/lib/auth/AuthProvider'

export function TeacherInvitationPage() {
  const { token = '' } = useParams()
  const { user, status, refresh, setActiveOrg } = useAuth()
  const navigate = useNavigate()
  const query = useQuery({
    queryKey: ['teacher-invitation', token],
    queryFn: () => batch4Api.invitation(token),
    retry: false,
  })
  const accept = useMutation({
    mutationFn: () => batch4Api.accept(token),
    onSuccess: async (data) => {
      setActiveOrg(data.organization_id)
      await refresh()
      navigate('/classes')
    },
  })
  const matches =
    user?.user.email.toLowerCase() === query.data?.email.toLowerCase()
  return (
    <AuthLayout
      title="Teaching invitation"
      subtitle="Join your school’s teaching team."
    >
      {query.isLoading ? (
        <Skeleton className="h-40" />
      ) : query.isError || !query.data ? (
        <Alert variant="danger">This invitation could not be found.</Alert>
      ) : (
        <div className="space-y-5">
          <p>
            <strong>{query.data.organization_name}</strong> has invited{' '}
            {query.data.name} to teach.
          </p>
          <p className="break-all text-sm text-muted">
            For {query.data.email} · expires{' '}
            {new Date(query.data.expires_at).toLocaleDateString()}
          </p>
          {query.data.status !== 'pending' ? (
            <Alert>
              This invitation is {query.data.status}. Ask your school for a new
              invitation if needed.
            </Alert>
          ) : status !== 'authenticated' ? (
            <div className="space-y-4">
              <Link
                className="block text-primary underline"
                to="/login"
                state={{
                  from: {
                    pathname: `/teacher-invitations/${token}`,
                  },
                }}
              >
                Sign in to accept
              </Link>
              <Link
                className="block text-primary underline"
                to={`/register?teacher_invitation=${token}`}
              >
                Create a teacher account
              </Link>
            </div>
          ) : (
            <>
              {!matches ? (
                <Alert variant="danger">
                  Sign in with the email address that received this invitation.
                </Alert>
              ) : !user?.user.email_verified ? (
                <Alert>
                  Verify your email before accepting.{' '}
                  <Link
                    className="underline"
                    to="/verify-email"
                    state={{
                      from: `/teacher-invitations/${token}`,
                    }}
                  >
                    Verify email
                  </Link>
                  , then return to this invitation.
                </Alert>
              ) : null}
              <p className="text-sm text-muted">
                Accepting adds school teaching access to your account. Your
                family remains available.
              </p>
              <Button
                disabled={!matches || !user?.user.email_verified}
                loading={accept.isPending}
                onClick={() => accept.mutate()}
              >
                Accept teaching invitation
              </Button>
            </>
          )}
          {accept.error && (
            <Alert variant="danger">{accept.error.message}</Alert>
          )}
        </div>
      )}
    </AuthLayout>
  )
}
