import { useEffect, useRef, useState } from 'react'
import { Navigate, NavLink, Outlet, useLocation } from 'react-router-dom'
import { Avatar, Icon, IconButton } from '@/components/ui'
import { Logo } from '@/components/Logo'
import { cn } from '@/lib/cn'
import { useAuth } from '@/lib/auth/AuthProvider'
import { useTheme } from '@/lib/theme'
import { visibleSections } from '@/lib/nav/navigation'
import { ProfileSwitcher } from '@/components/layout/ProfileSwitcher'
import { useActiveProfile } from '@/lib/profile/ActiveProfile'
import { ConsumerPageAdvert } from '@/components/adverts/ConsumerPageAdvert'

/** Persistent authenticated frame: sidebar + topbar with a routed content area. */
export function AppLayout() {
  const { user } = useAuth()
  const { activeLearner } = useActiveProfile()
  const location = useLocation()
  const [drawerOpen, setDrawerOpen] = useState(false)

  const roles = user?.user.roles ?? []
  const child = user?.active_child_profile_id != null || !!activeLearner?.is_child
  const sections = visibleSections(roles, user?.user.capabilities, child)
  const childPaths = ['/learn', '/tasks', '/achievements', '/leaderboard', '/competitions']
  // Close the mobile drawer on navigation.
  useEffect(() => {
    setDrawerOpen(false)
  }, [location.pathname])

  if (child && (location.pathname.startsWith('/competitions/manage')
    || !childPaths.some((path) => location.pathname === path || location.pathname.startsWith(path + '/')))) {
    return <Navigate to="/learn" replace />
  }

  return (
    <div className="min-h-screen bg-background">
      {/* Desktop sidebar */}
      <aside className="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col border-r border-border bg-surface lg:flex">
        <SidebarContent sections={sections} child={child} />
      </aside>

      {/* Mobile drawer */}
      {drawerOpen && (
        <div className="fixed inset-0 z-40 lg:hidden">
          <div
            className="absolute inset-0 bg-charcoal-900/50"
            onClick={() => setDrawerOpen(false)}
            aria-hidden="true"
          />
          <aside className="absolute inset-y-0 left-0 flex w-72 max-w-[80%] flex-col border-r border-border bg-surface animate-step-in">
            <SidebarContent sections={sections} child={child} onClose={() => setDrawerOpen(false)} />
          </aside>
        </div>
      )}

      {/* Main column */}
      <div className="lg:pl-64">
        <Topbar onOpenDrawer={() => setDrawerOpen(true)} />
        <main className="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6 lg:px-8">
          <Outlet />
          <ConsumerPageAdvert />
        </main>
      </div>
    </div>
  )
}

function SidebarContent({
  sections,
  child,
  onClose,
}: {
  sections: ReturnType<typeof visibleSections>
  child: boolean
  onClose?: () => void
}) {
  return (
    <>
      <div className="flex h-16 items-center justify-between border-b border-border px-5">
        <NavLink to={child ? '/learn' : '/'} aria-label={child ? 'Go to learning' : 'Go to the Mahadum.360 homepage'} end>
          <Logo className="h-8" />
        </NavLink>
        {onClose && (
          <IconButton onClick={onClose} size="sm" className="lg:hidden" aria-label="Close menu">
            <Icon name="close" />
          </IconButton>
        )}
      </div>

      <nav className="flex-1 space-y-6 overflow-y-auto px-3 py-5">
        {sections.map((section, i) => (
          <div key={section.title ?? i}>
            {section.title && (
              <p className="px-3 pb-1.5 text-[11px] font-semibold uppercase tracking-wide text-subtle">
                {section.title}
              </p>
            )}
            <ul className="space-y-0.5">
              {section.items.map((item) => (
                <li key={item.to}>
                  <NavLink
                    to={item.to}
                    end={item.end}
                    className={({ isActive }) =>
                      cn(
                        'flex min-h-11 items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition-colors',
                        isActive
                          ? 'bg-primary-soft text-primary'
                          : 'text-muted hover:bg-surface-muted hover:text-foreground',
                      )
                    }
                  >
                    <Icon name={item.icon} className="size-[18px]" />
                    {item.label}
                  </NavLink>
                </li>
              ))}
            </ul>
          </div>
        ))}
      </nav>
      <div className="border-t border-border p-3">
        <p className="mb-1 px-3 text-[11px] font-semibold uppercase tracking-wide text-subtle">Current learner</p>
        <ProfileSwitcher placement="sidebar" />
      </div>
    </>
  )
}

function Topbar({ onOpenDrawer }: { onOpenDrawer: () => void }) {
  const { theme, toggle } = useTheme()
  const { user } = useAuth()
  const { activeLearner } = useActiveProfile()
  const child = user?.active_child_profile_id != null || !!activeLearner?.is_child

  return (
    <header className="sticky top-0 z-20 border-b border-border bg-surface/80 backdrop-blur">
      <div className="flex h-16 items-center gap-3 px-4 sm:px-6 lg:px-8">
        <IconButton onClick={onOpenDrawer} className="lg:hidden" aria-label="Open menu">
          <Icon name="menu" />
        </IconButton>

        <NavLink to={child ? '/learn' : '/'} aria-label={child ? 'Go to learning' : 'Go to the Mahadum.360 homepage'} end className="lg:hidden">
          <Logo className="h-7" />
        </NavLink>

        <div className="flex-1" />

        {!child && <OrgSwitcher />}

        <IconButton
          onClick={toggle}
          aria-label={theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'}
        >
          <Icon name={theme === 'dark' ? 'sun' : 'moon'} />
        </IconButton>

        <UserMenu />
      </div>
    </header>
  )
}

function OrgSwitcher() {
  const { user, activeOrgId, setActiveOrg } = useAuth()
  const orgs = user?.organizations ?? []
  if (orgs.length === 0) return null

  return (
    <label className="hidden items-center gap-2 sm:flex">
      <span className="sr-only">Active organization</span>
      <select
        value={activeOrgId ?? ''}
        onChange={(e) => setActiveOrg(e.target.value ? Number(e.target.value) : null)}
        className="h-9 rounded-lg border border-border-strong bg-surface px-2.5 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
      >
        {orgs.map((o) => (
          <option key={o.id} value={o.id}>
            {o.name ?? `Org #${o.id}`}
          </option>
        ))}
      </select>
    </label>
  )
}

function UserMenu() {
  const { user, logout } = useAuth()
  const { activeLearner } = useActiveProfile()
  const [open, setOpen] = useState(false)
  const ref = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (!open) return
    function onDown(e: MouseEvent) {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false)
    }
    document.addEventListener('mousedown', onDown)
    return () => document.removeEventListener('mousedown', onDown)
  }, [open])

  const child = user?.active_child_profile_id != null || !!activeLearner?.is_child
  const name = child ? activeLearner!.display_name : user?.user.name ?? 'Account'

  return (
    <div className="relative" ref={ref}>
      <button
        onClick={() => setOpen((v) => !v)}
        className="relative flex items-center gap-2 rounded-full p-1 pr-2 hover:bg-surface-muted after:absolute after:inset-x-0 after:-inset-y-0.5 after:content-['']"
        aria-haspopup="menu"
        aria-expanded={open}
      >
        <Avatar name={name} src={child ? activeLearner?.avatar_url ?? undefined : undefined} size="sm" />
        <span className="hidden max-w-[10rem] truncate text-sm font-medium text-foreground sm:inline">
          {child ? name : user?.user.first_name ?? name}
        </span>
        <Icon name="chevron" className="size-4 text-muted" />
      </button>

      {open && (
        <div
          role="menu"
          className="absolute right-0 mt-2 w-56 overflow-hidden rounded-xl border border-border bg-surface shadow-lg animate-step-in"
        >
          <div className="border-b border-border px-4 py-3">
            <p className="truncate text-sm font-semibold text-foreground">{name}</p>
            {!child && <p className="truncate text-xs text-muted">{user?.user.email}</p>}
          </div>
          <button
            role="menuitem"
            onClick={() => {
              setOpen(false)
              void logout()
            }}
            className="flex min-h-11 w-full items-center gap-2.5 px-4 py-2.5 text-sm text-foreground hover:bg-surface-muted"
          >
            <Icon name="logout" className="size-[18px] text-muted" />
            Sign out
          </button>
        </div>
      )}
    </div>
  )
}
