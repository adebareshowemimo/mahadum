import { Badge, Card, CardBody, CardHeader, CardTitle, LinkButton } from '@/components/ui'
import { useAuth } from '@/lib/auth/AuthProvider'

/** Authenticated landing. Rendered inside the app shell (AppLayout). */
export function DashboardPage() {
  const { user, hasRole } = useAuth()

  return (
    <div className="flex flex-col gap-8">
      <div>
        <h1 className="font-display text-2xl font-bold text-foreground">
          Welcome back{user ? `, ${user.user.first_name}` : ''} 👋
        </h1>
        <p className="mt-1 text-muted">Here’s your home base. Pick up where you left off.</p>
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Your account</CardTitle>
          </CardHeader>
          <CardBody className="flex flex-col gap-2 text-sm">
            <Row label="Email" value={user?.user.email} />
            <Row label="Verified" value={user?.user.email_verified ? 'Yes' : 'No'} />
            <Row
              label="Institution"
              value={user?.organizations.length ? user.organizations.map((o) => o.name).filter(Boolean).join(', ') : undefined}
            />
            <div className="flex flex-wrap items-center gap-1.5">
              <span className="text-muted">Roles:</span>
              {user?.user.roles.map((r) => (
                <Badge key={r} variant="neutral">
                  {r}
                </Badge>
              ))}
            </div>
          </CardBody>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Families</CardTitle>
          </CardHeader>
          <CardBody className="flex flex-col gap-2 text-sm">
            {user?.families.length ? (
              user.families.map((f) => (
                <div key={f.id} className="flex items-center justify-between">
                  <span className="font-medium text-foreground">{f.name}</span>
                  <span className="text-muted">{f.learners?.length ?? 0} learners</span>
                </div>
              ))
            ) : (
              <p className="text-muted">No families yet.</p>
            )}
          </CardBody>
        </Card>
      </div>

      <div className="flex flex-wrap gap-3">
        {hasRole('parent', 'supervisor') && (
          <LinkButton to="/family" variant="parent">
            Go to family
          </LinkButton>
        )}
        {hasRole('student') && <LinkButton to="/learn">Continue learning</LinkButton>}
        <LinkButton to="/components" variant="secondary">
          Browse the design system
        </LinkButton>
      </div>
    </div>
  )
}

function Row({ label, value }: { label: string; value?: string }) {
  return (
    <div className="flex items-center justify-between">
      <span className="text-muted">{label}</span>
      <span className="font-medium text-foreground">{value ?? '—'}</span>
    </div>
  )
}
