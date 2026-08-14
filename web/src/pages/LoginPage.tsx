import { useEffect, useState, type FormEvent } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { Alert, Button, Input } from '@/components/ui'
import { AuthLayout } from '@/components/auth/AuthLayout'
import { GoogleButton, OrDivider } from '@/components/auth/GoogleButton'
import { ApiError, classInvitationApi } from '@/lib/api'
import { useAuth } from '@/lib/auth/AuthProvider'

export function LoginPage() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const invitationToken = new URLSearchParams(location.search).get('class_invitation') ?? ''
  const invitation = useQuery({
    queryKey: ['class-invitation', invitationToken],
    queryFn: () => classInvitationApi.show(invitationToken),
    enabled: invitationToken.length === 64,
    retry: false,
  })
  const from = (location.state as { from?: { pathname?: string } } | null)?.from?.pathname ?? '/home'

  const [values, setValues] = useState({ login: '', password: '' })
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  useEffect(() => {
    if (invitation.data?.email) setValues((value) => ({ ...value, login: invitation.data.email }))
  }, [invitation.data?.email])

  function update(field: keyof typeof values) {
    return (e: React.ChangeEvent<HTMLInputElement>) =>
      setValues((v) => ({ ...v, [field]: e.target.value }))
  }

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setSubmitting(true)
    setFormError(null)
    setFieldErrors({})
    try {
      await login({ ...values, ...(invitationToken ? { class_invitation_token: invitationToken } : {}) })
      navigate(invitationToken ? '/learn' : from, { replace: true })
    } catch (err) {
      if (err instanceof ApiError) {
        setFieldErrors(err.fieldErrors)
        if (!Object.keys(err.fieldErrors).length) setFormError(err.message)
      } else {
        setFormError('Something went wrong. Please try again.')
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <AuthLayout
      eyebrow="Continue your language journey"
      title="Welcome back to your learning circle."
      subtitle="Your lessons, family profiles and progress are ready when you are."
      image="/images/hero-grandmother-child.webp"
      imageAlt="Iya and her granddaughter Amara sharing a joyful language lesson on a tablet"
      imagePosition="object-center"
      visualTitle="The next family conversation starts with one familiar word."
      visualBody="Return to short lessons, native voices and the progress your household is building together."
      phrases={['Ẹ káàrọ̀', 'Ụtụtụ ọma', 'Ina kwana']}
      footer={
        <>
          New to Mahadum.360?{' '}
          <Link to={invitationToken ? `/register?class_invitation=${invitationToken}` : '/register'} className="inline-flex min-h-11 items-center font-bold text-chore-700 hover:underline">
            Create an account
          </Link>
        </>
      }
    >
      <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
        {formError && <Alert variant="danger">{formError}</Alert>}

        <Input
          label="Email or username"
          type="text"
          autoComplete="username"
          autoFocus
          value={values.login}
          onChange={update('login')}
          error={fieldErrors.login}
          required
        />

        <div className="flex flex-col gap-1.5">
          <Input
            label="Password"
            type="password"
            autoComplete="current-password"
            value={values.password}
            onChange={update('password')}
            error={fieldErrors.password}
            required
          />
          <Link
            to="/forgot-password"
            className="inline-flex min-h-11 items-center self-end text-sm font-bold text-chore-700 hover:underline"
          >
            Forgot password?
          </Link>
        </div>

        <Button type="submit" fullWidth size="lg" variant="parent" loading={submitting}>
          Sign in
        </Button>

        {!invitationToken && <><OrDivider /><GoogleButton onSuccess={() => navigate(from, { replace: true })} onError={(msg) => setFormError(msg)} /></>}
      </form>
    </AuthLayout>
  )
}
