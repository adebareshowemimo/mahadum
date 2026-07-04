import { Fragment } from 'react'
import { AdminPageHeader } from '@/components/admin'
import { Alert, Card, Skeleton } from '@/components/ui'
import { useRolesMatrix } from '@/lib/admin/queries'

export function RolesMatrixPage() {
  const { data, isLoading, isError } = useRolesMatrix()

  if (isLoading) return <Skeleton className="h-96" />
  if (isError || !data) return <Alert variant="danger">Couldn’t load the permission matrix.</Alert>

  const { roles, groups, matrix } = data
  const has = (role: string, permission: string) => matrix[role]?.includes(permission)

  return (
    <div className="flex flex-col gap-6">
      <AdminPageHeader
        title="Roles & permissions"
        description="Which capability each role holds. View-only — the source of truth is the roles seeder."
      />

      <Card>
        <div className="overflow-x-auto">
          <table className="w-full border-collapse text-left text-sm">
            <thead>
              <tr className="border-b border-border">
                <th className="sticky left-0 z-10 bg-surface px-4 py-3 font-semibold text-muted">Permission</th>
                {roles.map((r) => (
                  <th key={r} className="px-3 py-3 text-center text-xs font-bold text-foreground">
                    <span className="inline-block max-w-[5rem] break-words">{r}</span>
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {groups.map((group) => (
                <Fragment key={group.group}>
                  <tr className="bg-surface-muted/60">
                    <td colSpan={roles.length + 1} className="px-4 py-1.5 text-xs font-bold uppercase tracking-wide text-muted">
                      {group.group}
                    </td>
                  </tr>
                  {group.permissions.map((permission) => (
                    <tr key={permission} className="border-b border-border last:border-0">
                      <td className="sticky left-0 z-10 bg-surface px-4 py-2 font-medium text-foreground">{permission}</td>
                      {roles.map((r) => (
                        <td key={r} className="px-3 py-2 text-center">
                          {has(r, permission) ? (
                            <span className="text-leaf-600" aria-label="granted">
                              ●
                            </span>
                          ) : (
                            <span className="text-subtle" aria-hidden>
                              ·
                            </span>
                          )}
                        </td>
                      ))}
                    </tr>
                  ))}
                </Fragment>
              ))}
            </tbody>
          </table>
        </div>
      </Card>
    </div>
  )
}
