import { LinkButton } from '@/components/ui'
import { useAuth } from '@/lib/auth/AuthProvider'

export function NotFoundPage() {
  const { status } = useAuth()
  const destination = status === 'authenticated' ? '/home' : '/'

  return (
    <main className="grid min-h-dvh place-items-center bg-background px-4 py-12">
      <div className="max-w-md text-center">
        <p className="text-sm font-semibold text-primary">Page not found</p>
        <h1 className="mt-2 font-display text-3xl font-bold text-foreground">That page isn’t here.</h1>
        <p className="mt-3 text-muted">The link may be out of date, or the page may have moved.</p>
        <LinkButton to={destination} className="mt-6">{status === 'authenticated' ? 'Return to dashboard' : 'Return to home'}</LinkButton>
      </div>
    </main>
  )
}
