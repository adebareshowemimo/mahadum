import { useState, type FormEvent } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { Alert, Button, Input } from '@/components/ui'
import { AuthLayout } from '@/components/auth/AuthLayout'
import { GoogleButton, OrDivider } from '@/components/auth/GoogleButton'
import { ApiError } from '@/lib/api'
import { useAuth } from '@/lib/auth/AuthProvider'

export function LoginPage() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const from = (location.state as { from?: { pathname?: string } } | null)?.from?.pathname ?? '/home'

  const [values, setValues] = useState({ login: '', password: '' })
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

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
      await login(values)
      navigate(from, { replace: true })
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
      title="Welcome back"
      subtitle="Sign in to keep your streak going."
      footer={
        <>
          New to Mahadum.360?{' '}
          <Link to="/register" className="font-semibold text-primary hover:underline">
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
            className="self-end text-xs font-medium text-primary hover:underline"
          >
            Forgot password?
          </Link>
        </div>

        <Button type="submit" fullWidth size="lg" loading={submitting}>
          Sign in
        </Button>

        <OrDivider />

        <GoogleButton
          onSuccess={() => navigate(from, { replace: true })}
          onError={(msg) => setFormError(msg)}
        />
      </form>
    </AuthLayout>
  )
}
