import { useState, type FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { Alert, Button, Input } from '@/components/ui'
import { AuthLayout } from '@/components/auth/AuthLayout'
import { ApiError, authApi } from '@/lib/api'

export function ForgotPasswordPage() {
  const [email, setEmail] = useState('')
  const [fieldError, setFieldError] = useState<string | undefined>()
  const [formError, setFormError] = useState<string | null>(null)
  const [sent, setSent] = useState(false)
  const [submitting, setSubmitting] = useState(false)

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setSubmitting(true)
    setFormError(null)
    setFieldError(undefined)
    try {
      await authApi.forgotPassword(email)
      setSent(true)
    } catch (err) {
      if (err instanceof ApiError) {
        setFieldError(err.fieldError('email'))
        if (!err.fieldError('email')) setFormError(err.message)
      } else {
        setFormError('Something went wrong. Please try again.')
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <AuthLayout
      title="Reset your password"
      subtitle="We'll email you a link to set a new one."
      footer={
        <Link to="/login" className="font-semibold text-primary hover:underline">
          Back to sign in
        </Link>
      }
    >
      {sent ? (
        <Alert variant="success" title="Check your inbox">
          If an account exists for {email}, a reset link is on its way.
        </Alert>
      ) : (
        <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
          {formError && <Alert variant="danger">{formError}</Alert>}
          <Input
            label="Email"
            type="email"
            autoComplete="email"
            autoFocus
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            error={fieldError}
            required
          />
          <Button type="submit" fullWidth size="lg" loading={submitting}>
            Send reset link
          </Button>
        </form>
      )}
    </AuthLayout>
  )
}
