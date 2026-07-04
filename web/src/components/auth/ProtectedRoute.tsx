import { Navigate, Outlet, useLocation } from 'react-router-dom'
import { Spinner } from '@/components/ui'
import { useAuth } from '@/lib/auth/AuthProvider'
import type { Role } from '@/lib/api'

/** Full-screen loader shown while the session is being resolved. */
function SessionLoading() {
  return (
    <div className="flex min-h-screen items-center justify-center bg-background">
      <Spinner className="size-8 text-primary" />
    </div>
  )
}

/**
 * Gate for authenticated areas. While /me resolves we show a loader; if there is
 * no session we bounce to /login, preserving the attempted location so we can
 * return there after sign-in.
 */
export function ProtectedRoute() {
  const { status } = useAuth()
  const location = useLocation()

  if (status === 'loading') return <SessionLoading />
  if (status === 'unauthenticated') {
    return <Navigate to="/login" replace state={{ from: location }} />
  }
  return <Outlet />
}

/**
 * Role gate for a subtree of already-authenticated routes. Renders only if the
 * signed-in user holds at least one of `roles`; otherwise bounces to /home.
 * Assumes it is nested inside <ProtectedRoute> (session already resolved), but
 * guards the loading/unauthenticated cases defensively too.
 */
export function RoleRoute({ roles }: { roles: Role[] }) {
  const { status, hasRole } = useAuth()
  const location = useLocation()

  if (status === 'loading') return <SessionLoading />
  if (status === 'unauthenticated') {
    return <Navigate to="/login" replace state={{ from: location }} />
  }
  if (!hasRole(...roles)) return <Navigate to="/home" replace />
  return <Outlet />
}

/** Convenience guard for the global-admin portal (super_admin only). */
export function AdminRoute() {
  return <RoleRoute roles={['super_admin']} />
}

/**
 * Inverse gate for auth pages (login/register): an already-authenticated user is
 * sent on to the app instead of seeing the sign-in form again.
 */
export function GuestRoute() {
  const { status } = useAuth()
  const location = useLocation()

  if (status === 'loading') return <SessionLoading />
  if (status === 'authenticated') {
    const from = (location.state as { from?: { pathname?: string } } | null)?.from?.pathname
    return <Navigate to={from ?? '/home'} replace />
  }
  return <Outlet />
}
