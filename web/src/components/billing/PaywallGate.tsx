import type { ReactNode } from 'react'
import { Card, CardBody, LinkButton } from '@/components/ui'
import type { EntitlementFeature } from '@/lib/api'
import { FEATURE_META, useEntitlements } from '@/lib/billing/entitlements'

/**
 * Soft paywall: renders children when the active plan grants `feature`, else an
 * upgrade prompt. Never blocks learning — only premium tooling is gated here.
 */
export function PaywallGate({ feature, children }: { feature: EntitlementFeature; children: ReactNode }) {
  const entitlements = useEntitlements()
  if (entitlements[feature]) return <>{children}</>

  const meta = FEATURE_META[feature]

  return (
    <div className="mx-auto max-w-md">
      <Card>
        <CardBody className="flex flex-col items-center gap-3 py-12 text-center">
          <span className="flex size-14 items-center justify-center rounded-2xl bg-gold-100 text-2xl">🔒</span>
          <h1 className="font-display text-xl font-bold text-foreground">{meta.label}</h1>
          <p className="max-w-xs text-sm text-muted">
            This is part of <strong>{meta.unlockedBy}</strong>. Upgrade to unlock it for your family.
          </p>
          <LinkButton to="/billing" variant="premium">
            See plans
          </LinkButton>
        </CardBody>
      </Card>
    </div>
  )
}
