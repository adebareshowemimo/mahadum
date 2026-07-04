import { Link, useLocation } from 'react-router-dom'
import { cn } from '@/lib/cn'

interface AdminGroup {
  label: string
  /** Primary destination for the group. */
  to: string
  /** Path prefixes that count as "inside" this group (for active state). */
  match: string[]
  /** Exact-match only (used for the Overview root). */
  exact?: boolean
}

// Groups the flat Admin nav into a compact secondary bar. Each group links to its
// primary page; the group is highlighted whenever the current route falls under
// any of its `match` prefixes (longest match wins, so /admin/settings/gateways
// resolves to Finance rather than System).
const GROUPS: AdminGroup[] = [
  { label: 'Overview', to: '/admin', match: ['/admin'], exact: true },
  { label: 'Orgs', to: '/admin/orgs', match: ['/admin/orgs'] },
  { label: 'Users', to: '/admin/users', match: ['/admin/users', '/admin/roles'] },
  { label: 'Content', to: '/admin/courses', match: ['/admin/courses', '/admin/languages'] },
  {
    label: 'Finance',
    to: '/admin/payouts',
    match: ['/admin/payouts', '/admin/settlements', '/admin/plans', '/admin/promos', '/admin/settings/gateways'],
  },
  { label: 'Reports', to: '/admin/reports', match: ['/admin/reports'] },
  { label: 'Email', to: '/admin/emails', match: ['/admin/emails'] },
  { label: 'System', to: '/admin/audit', match: ['/admin/audit', '/admin/fraud', '/admin/support', '/admin/settings'] },
]

function matchLength(path: string, group: AdminGroup): number {
  if (group.exact) return path === group.to ? group.to.length : -1
  let best = -1
  for (const prefix of group.match) {
    if (path === prefix || path.startsWith(prefix + '/')) best = Math.max(best, prefix.length)
  }
  return best
}

/** Secondary tab bar shown atop every admin page, grouping the portal sections. */
export function AdminSubNav() {
  const { pathname } = useLocation()

  // Resolve the single active group by the longest matching prefix.
  let activeLabel = ''
  let bestLen = -1
  for (const g of GROUPS) {
    const len = matchLength(pathname, g)
    if (len > bestLen) {
      bestLen = len
      activeLabel = g.label
    }
  }

  return (
    <nav aria-label="Admin sections" className="-mx-1 mb-6 overflow-x-auto border-b border-border">
      <ul className="flex min-w-max gap-1 px-1">
        {GROUPS.map((g) => {
          const active = g.label === activeLabel
          return (
            <li key={g.label}>
              {/* Plain Link (not NavLink): active state is computed by longest-prefix
                  match above, so we must not let NavLink apply its own aria-current. */}
              <Link
                to={g.to}
                aria-current={active ? 'page' : undefined}
                className={cn(
                  'relative -mb-px inline-block rounded-t-lg px-3.5 py-2.5 text-sm font-semibold transition-colors',
                  active
                    ? 'border-b-2 border-primary text-foreground'
                    : 'text-muted hover:text-foreground',
                )}
              >
                {g.label}
              </Link>
            </li>
          )
        })}
      </ul>
    </nav>
  )
}
