import { useState } from 'react'
import { useParams } from 'react-router-dom'
import { AdminPageHeader } from '@/components/admin'
import { Alert, Badge, Button, Card, Spinner } from '@/components/ui'
import { ApiError, type Role, type UserStatus } from '@/lib/api'
import { formatMoney } from '@/lib/format'
import { useAdminUser, useAdminUserReferrals, useAssignUserRole, useSetUserStatus } from '@/lib/admin/queries'

const ROLES: Role[] = ['super_admin', 'content_owner', 'school_admin', 'teacher', 'supervisor', 'parent', 'student']

export function UserDetailPage() {
  const userId = Number(useParams().userId)
  const { data: user, isLoading, isError } = useAdminUser(userId)
  const referrals = useAdminUserReferrals(userId)
  const assign = useAssignUserRole()
  const setStatus = useSetUserStatus()
  const [error, setError] = useState<string | null>(null)

  if (!Number.isInteger(userId) || userId < 1) return <Alert variant="danger">This user link is invalid.</Alert>
  if (isLoading) return <div className="flex justify-center py-16" aria-label="Loading user"><Spinner /></div>
  if (isError || !user) return <Alert variant="danger">Couldn’t load this user.</Alert>

  async function toggleRole(role: Role) {
    if (!user) return
    setError(null)
    try {
      await assign.mutateAsync({ userId: user.id, input: { role, action: user.roles.includes(role) ? 'revoke' : 'assign' } })
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not update role.')
    }
  }

  async function changeStatus(next: UserStatus) {
    if (!user) return
    setError(null)
    try {
      await setStatus.mutateAsync({ userId: user.id, status: next })
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Could not update status.')
    }
  }

  return (
    <div className="flex flex-col gap-6">
      <AdminPageHeader title={user.name} description={user.email} backTo="/admin/users" backLabel="Back to users" />
      {error && <Alert variant="danger">{error}</Alert>}

      <Card className="flex flex-col gap-6 p-5 sm:p-6">
        <div className="flex flex-wrap items-center gap-3 text-sm">
          <Badge variant={user.status === 'active' ? 'success' : 'danger'}>{user.status}</Badge>
          {user.phone && <span className="text-muted">{user.phone}</span>}
          <span className="text-muted">{user.email_verified ? 'Email verified' : 'Email not verified'}</span>
          {user.created_at && <span className="text-muted">Joined {new Date(user.created_at).toLocaleDateString()}</span>}
          <span className="text-muted">
            {user.last_login_at ? `Last seen ${new Date(user.last_login_at).toLocaleDateString()}` : 'Never signed in'}
          </span>
        </div>

        <section aria-labelledby="organizations-heading">
          <h2 id="organizations-heading" className="mb-2 text-sm font-semibold text-foreground">Organizations</h2>
          {user.organizations.length ? (
            <ul className="flex flex-col gap-2">
              {user.organizations.map((organization) => (
                <li key={organization.id} className="flex flex-wrap items-center gap-2 rounded-xl border border-border p-3 text-sm">
                  <span className="font-medium text-foreground">{organization.name ?? `Organization #${organization.id}`}</span>
                  <Badge variant="neutral">{organization.role}</Badge>
                  <Badge variant={organization.status === 'active' ? 'success' : 'neutral'}>{organization.status}</Badge>
                </li>
              ))}
            </ul>
          ) : (
            <p className="text-sm text-muted">Direct consumer with no organization membership.</p>
          )}
        </section>

        <section aria-labelledby="roles-heading">
          <h2 id="roles-heading" className="mb-2 text-sm font-semibold text-foreground">Platform roles</h2>
          <div className="flex flex-wrap gap-2">
            {ROLES.map((role) => {
              const assigned = user.roles.includes(role)
              return (
                <button
                  key={role}
                  type="button"
                  disabled={assign.isPending}
                  aria-pressed={assigned}
                  onClick={() => toggleRole(role)}
                  className={assigned
                    ? 'rounded-full border border-primary bg-primary/10 px-3 py-2 text-xs font-bold text-primary disabled:opacity-50'
                    : 'rounded-full border border-border-strong px-3 py-2 text-xs font-semibold text-muted hover:bg-surface-muted disabled:opacity-50'}
                >
                  {assigned ? '✓ ' : '+ '}{role}
                </button>
              )
            })}
          </div>
          <p className="mt-2 text-xs text-muted">Select a role to grant or revoke it. Every change is audited.</p>
        </section>

        <section aria-labelledby="referrals-heading">
          <h2 id="referrals-heading" className="mb-2 text-sm font-semibold text-foreground">Referral activity</h2>
          {referrals.isLoading ? (
            <p className="text-sm text-muted">Loading…</p>
          ) : referrals.isError || !referrals.data ? (
            <p className="text-sm text-muted">Couldn’t load referral activity.</p>
          ) : (
            <div className="flex flex-col gap-3 text-sm">
              {referrals.data.as_referred && (
                <p className="rounded-xl border border-border p-3 text-muted">
                  Joined via <span className="font-medium text-foreground">{referrals.data.as_referred.referrer_name ?? 'a referral'}</span>
                  {referrals.data.as_referred.code ? ` (code ${referrals.data.as_referred.code})` : ''}
                  {' · '}{referrals.data.as_referred.channel ?? 'link'}
                  {' · '}status {referrals.data.as_referred.status}
                </p>
              )}

              <div className="flex flex-wrap gap-2 text-xs">
                <Badge variant="neutral">Code: {referrals.data.as_referrer.code ?? 'none issued'}</Badge>
                <Badge variant="neutral">{referrals.data.as_referrer.total_referred} referred</Badge>
                <Badge variant="neutral">{referrals.data.as_referrer.total_qualified} activated</Badge>
                <Badge variant="success">
                  {formatMoney(referrals.data.as_referrer.commission_cleared_minor, 'NGN')} cleared
                </Badge>
              </div>

              {referrals.data.as_referrer.activations.length > 0 ? (
                <div className="overflow-x-auto rounded-xl border border-border">
                  <table className="w-full text-left text-xs">
                    <thead className="bg-surface-muted text-muted">
                      <tr>
                        <th className="px-3 py-2 font-semibold">Referred</th>
                        <th className="px-3 py-2 font-semibold">Email</th>
                        <th className="px-3 py-2 font-semibold">Phone</th>
                        <th className="px-3 py-2 font-semibold">Activated</th>
                        <th className="px-3 py-2 font-semibold">Status</th>
                        <th className="px-3 py-2 font-semibold">Commission</th>
                      </tr>
                    </thead>
                    <tbody>
                      {referrals.data.as_referrer.activations.map((a, i) => (
                        <tr key={i} className="border-t border-border">
                          <td className="px-3 py-2 text-foreground">{a.referred_name ?? '—'}</td>
                          <td className="px-3 py-2">{a.email ?? '—'}</td>
                          <td className="px-3 py-2">{a.phone ?? '—'}</td>
                          <td className="px-3 py-2">{a.activated_at ?? '—'}</td>
                          <td className="px-3 py-2">{a.status}</td>
                          <td className="px-3 py-2">{a.commission_minor ? formatMoney(a.commission_minor, 'NGN') : '—'}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : (
                <p className="text-muted">No one has activated through this user’s code.</p>
              )}
            </div>
          )}
        </section>

        <div className="flex flex-wrap justify-end gap-2 border-t border-border pt-4">
          {user.status === 'active' ? (
            <Button variant="ghost" loading={setStatus.isPending} onClick={() => changeStatus('suspended')}>Suspend account</Button>
          ) : (
            <Button variant="parent" loading={setStatus.isPending} onClick={() => changeStatus('active')}>Reactivate account</Button>
          )}
        </div>
      </Card>
    </div>
  )
}
