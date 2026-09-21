import { useState } from 'react'
import { Navigate, useLocation } from 'react-router-dom'
import { AuthLayout } from '@/components/auth/AuthLayout'
import { Alert, Button } from '@/components/ui'
import { ApiError, authApi } from '@/lib/api'
import { useAuth } from '@/lib/auth/AuthProvider'

export function VerifyEmailPage() {
  const { user, refresh, logout } = useAuth()
  const location = useLocation()
  const [busy, setBusy] = useState<'resend' | 'check' | null>(null)
  const [message, setMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(() => new URLSearchParams(location.search).has('expired')
    ? 'This verification link has expired or is invalid. Request a new email below.' : null)
  const from = (location.state as { from?: { pathname?: string; search?: string; hash?: string } } | null)?.from
  const destination = from?.pathname?.startsWith('/') && !from.pathname.startsWith('//')
    && !['/verify-email', '/login', '/register'].includes(from.pathname)
    ? `${from.pathname}${from.search ?? ''}${from.hash ?? ''}` : '/home'

  if (user?.user.email_verified) return <Navigate to={destination} replace />

  async function perform(action: 'resend' | 'check') {
    setBusy(action)
    setError(null)
    setMessage(null)
    try {
      if (action === 'resend') {
        await authApi.resendVerificationEmail()
        setMessage('Verification email sent. Check your inbox and spam folder.')
      } else {
        const session = await authApi.me()
        if (session.user.email_verified) await refresh()
        else setMessage('Your email is not verified yet. Open the link in your email, then check again.')
      }
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Unable to complete this request. Please try again.')
    } finally {
      setBusy(null)
    }
  }

  return (
    <AuthLayout title="Verify your email" subtitle="Open the link in your email to finish setting up your account.">
      <div className="flex flex-col gap-4">
        <p className="text-sm text-muted-foreground">
          Check the inbox and spam folder for <strong>{user?.user.email}</strong>.
          You need to verify this address before accessing your account.
        </p>
        {error && <Alert variant="danger">{error}</Alert>}
        {message && <Alert role="status">{message}</Alert>}
        <Button fullWidth loading={busy === 'check'} disabled={busy !== null} onClick={() => void perform('check')}>
          I’ve verified my email
        </Button>
        <Button fullWidth variant="outline" loading={busy === 'resend'} disabled={busy !== null} onClick={() => void perform('resend')}>
          Resend verification email
        </Button>
        <Button variant="ghost" onClick={() => void logout()}>Sign out</Button>
      </div>
    </AuthLayout>
  )
}
