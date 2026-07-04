import type { IconName } from '@/components/ui'
import type { Role } from '@/lib/api'

export interface NavItem {
  label: string
  to: string
  icon: IconName
  /** Visible only to these roles. Omit = visible to every signed-in user. */
  roles?: Role[]
  /** Exact-match active state (used for the index route). */
  end?: boolean
}

export interface NavSection {
  /** Section heading; omit for the top (unlabeled) group. */
  title?: string
  items: NavItem[]
}

// Single adaptive shell: a section appears only if the user has a role that owns
// at least one of its items. Most destinations are placeholders today and will
// be filled in as each milestone's screens land.
export const NAV_SECTIONS: NavSection[] = [
  {
    items: [{ label: 'Home', to: '/home', icon: 'home', end: true }],
  },
  {
    // Learner surface — visible to students, and to parents/supervisors who
    // drive a child's learning via the active-profile switcher.
    title: 'Learn',
    items: [
      { label: 'My learning', to: '/learn', icon: 'book', roles: ['student', 'parent', 'supervisor'] },
      { label: 'Achievements', to: '/achievements', icon: 'sparkles', roles: ['student', 'parent', 'supervisor'] },
      { label: 'Leaderboard', to: '/leaderboard', icon: 'trophy', roles: ['student', 'parent', 'supervisor'] },
      { label: 'Competition', to: '/competitions', icon: 'trophy', roles: ['student', 'parent', 'supervisor', 'teacher', 'school_admin'] },
    ],
  },
  {
    title: 'Family',
    items: [
      { label: 'Family', to: '/family', icon: 'users', roles: ['parent', 'supervisor'] },
      { label: 'Wallet', to: '/wallet', icon: 'wallet', roles: ['parent', 'supervisor'] },
      { label: 'Reviews', to: '/reviews', icon: 'clipboard', roles: ['parent', 'supervisor'] },
      { label: 'Referrals', to: '/referrals', icon: 'gift', roles: ['parent', 'teacher'] },
      { label: 'Billing', to: '/billing', icon: 'card', roles: ['parent'] },
    ],
  },
  {
    title: 'Teaching',
    items: [
      { label: 'Classes', to: '/classes', icon: 'cap', roles: ['teacher'] },
      { label: 'Assignments', to: '/assignments', icon: 'clipboard', roles: ['teacher'] },
      { label: 'Earnings', to: '/earnings', icon: 'wallet', roles: ['teacher'] },
    ],
  },
  {
    title: 'School',
    items: [
      { label: 'Dashboard', to: '/school', icon: 'building', roles: ['school_admin'] },
      { label: 'Roster', to: '/roster', icon: 'users', roles: ['school_admin'] },
      { label: 'Seats', to: '/seats', icon: 'layers', roles: ['school_admin'] },
      { label: 'Invoices', to: '/invoices', icon: 'card', roles: ['school_admin'] },
    ],
  },
  {
    // Courses visible to global admin + content team (+ school admins, read-only);
    // media management stays with the content team + global admin.
    title: 'Content',
    items: [
      { label: 'Courses', to: '/courses', icon: 'layers', roles: ['super_admin', 'content_owner', 'school_admin'] },
      { label: 'Media', to: '/media', icon: 'book', roles: ['super_admin', 'content_owner'] },
      { label: 'Competitions', to: '/competitions/manage', icon: 'trophy', roles: ['super_admin', 'content_owner'] },
    ],
  },
  {
    title: 'Admin',
    items: [
      { label: 'Overview', to: '/admin', icon: 'shield', roles: ['super_admin'], end: true },
      { label: 'Organizations', to: '/admin/orgs', icon: 'building', roles: ['super_admin'] },
      { label: 'Users', to: '/admin/users', icon: 'users', roles: ['super_admin'] },
      { label: 'Roles', to: '/admin/roles', icon: 'shield', roles: ['super_admin'] },
      { label: 'Courses', to: '/admin/courses', icon: 'layers', roles: ['super_admin'] },
      { label: 'Languages', to: '/admin/languages', icon: 'book', roles: ['super_admin'] },
      { label: 'Payouts', to: '/admin/payouts', icon: 'wallet', roles: ['super_admin'] },
      { label: 'Settlements', to: '/admin/settlements', icon: 'card', roles: ['super_admin'] },
      { label: 'Plans', to: '/admin/plans', icon: 'layers', roles: ['super_admin'] },
      { label: 'Reports', to: '/admin/reports', icon: 'trophy', roles: ['super_admin'] },
      { label: 'Promo codes', to: '/admin/promos', icon: 'gift', roles: ['super_admin'] },
      { label: 'Fraud review', to: '/admin/fraud', icon: 'shield', roles: ['super_admin'] },
      { label: 'Gateways', to: '/admin/settings/gateways', icon: 'card', roles: ['super_admin'] },
      { label: 'Audit log', to: '/admin/audit', icon: 'clipboard', roles: ['super_admin'] },
      { label: 'Support', to: '/admin/support', icon: 'bell', roles: ['super_admin'] },
      { label: 'Settings', to: '/admin/settings', icon: 'shield', roles: ['super_admin'] },
    ],
  },
  {
    title: 'System',
    items: [
      { label: 'Help & support', to: '/support', icon: 'bell' },
      { label: 'Design system', to: '/components', icon: 'sparkles' },
    ],
  },
]

function itemVisible(item: NavItem, roles: Role[]): boolean {
  return !item.roles || item.roles.some((r) => roles.includes(r))
}

/** Sections (with their items pre-filtered) the given roles are allowed to see. */
export function visibleSections(roles: Role[]): NavSection[] {
  return NAV_SECTIONS.map((section) => ({
    ...section,
    items: section.items.filter((item) => itemVisible(item, roles)),
  })).filter((section) => section.items.length > 0)
}

/** Every nav destination flattened — used to register placeholder routes. */
export function allNavItems(): NavItem[] {
  return NAV_SECTIONS.flatMap((s) => s.items)
}
