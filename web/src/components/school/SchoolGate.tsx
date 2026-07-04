import type { ReactNode } from 'react'
import { Card, CardBody } from '@/components/ui'
import { useSchoolOrgId } from '@/lib/school/queries'

/** Renders school content scoped to the admin's organization, or a prompt if none. */
export function SchoolGate({ children }: { children: (orgId: number) => ReactNode }) {
  const orgId = useSchoolOrgId()

  if (!orgId) {
    return (
      <Card>
        <CardBody className="flex flex-col items-center gap-3 py-12 text-center">
          <span className="text-4xl" aria-hidden="true">
            🏫
          </span>
          <h1 className="font-display text-xl font-bold text-foreground">No school selected</h1>
          <p className="max-w-xs text-sm text-muted">
            Pick an organization from the top-bar switcher to manage it.
          </p>
        </CardBody>
      </Card>
    )
  }

  return <>{children(orgId)}</>
}
