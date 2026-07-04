import { useState, type FormEvent } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { Alert, Button, Input } from '@/components/ui'
import { AuthLayout } from '@/components/auth/AuthLayout'
import { ApiError, authApi } from '@/lib/api'

export function ResetPasswordPage() {
  const [params] = useSearchParams()
  const token = params.get('token') ?? ''
  const email = params.get('email') ?? ''
  const linkValid = token.length > 0 && email.length > 0

  const [values, setValues] = useState({ password: '', password_confirmation: '' })
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [done, setDone] = useState(false)
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
      await authApi.resetPassword({ email, token, ...values })
      setDone(true)
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
      title="Set a new password"
      subtitle={linkValid ? `For ${email}` : undefined}
      footer={
        <Link to="/login" className="font-semibold text-primary hover:underline">
          Back to sign in
        </Link>
      }
    >
      {!linkValid ? (
        <Alert variant="danger" title="Invalid or expired link">
          This reset link is missing information. Please request a new one from the{' '}
          <Link to="/forgot-password" className="font-semibold underline">
            forgot password
          </Link>{' '}
          page.
        </Alert>
      ) : done ? (
        <Alert variant="success" title="Password updated">
          Your password has been reset.{' '}
          <Link to="/login" className="font-semibold underline">
            Sign in
          </Link>{' '}
          with your new password.
        </Alert>
      ) : (
        <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
          {formError && <Alert variant="danger">{formError}</Alert>}
          {fieldErrors.email && <Alert variant="danger">{fieldErrors.email}</Alert>}

          <Input
            label="New password"
            type="password"
            autoComplete="new-password"
            hint="At least 8 characters."
            value={values.password}
            onChange={update('password')}
            error={fieldErrors.password}
            autoFocus
            required
          />
          <Input
            label="Confirm new password"
            type="password"
            autoComplete="new-password"
            value={values.password_confirmation}
            onChange={update('password_confirmation')}
            error={fieldErrors.password_confirmation}
            required
          />
          <Button type="submit" fullWidth size="lg" loading={submitting}>
            Reset password
          </Button>
        </form>
      )}
    </AuthLayout>
  )
}
