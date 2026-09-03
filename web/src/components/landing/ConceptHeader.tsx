import { useEffect, useRef, useState } from 'react'
import { Link, NavLink } from 'react-router-dom'
import { Logo } from '@/components/Logo'
import { Avatar, Icon, LinkButton } from '@/components/ui'
import { WORDMARK } from '@/lib/brand'
import { cn } from '@/lib/cn'
import { useAuth } from '@/lib/auth/AuthProvider'
import { AdvertLeaderboard } from '@/components/adverts/AdvertLeaderboard'

type ConceptTone = 'light' | 'blue' | 'navy'

// Shared public navigation for every marketing and account-entry surface.
const SITE_NAV = [
  { to: '/families', label: 'Families' },
  { to: '/schools', label: 'Educators/Schools' },
  { to: '/institutions', label: 'Institutions' },
  { to: '/about', label: 'About' },
  { to: '/pricing', label: 'Pricing' },
  { to: '/contact', label: 'Contact' },
]

export function ConceptHeader({ tone = 'light', overlay = false }: { tone?: ConceptTone; overlay?: boolean }) {
  const dark = tone !== 'light'
  const [menuOpen, setMenuOpen] = useState(false)
  return (
    <>
      {/* Adverts don't render on overlay-style headers (transparent, sitting on a hero image). */}
      {!overlay && <AdvertLeaderboard />}
      <header
        className={cn(
          overlay ? 'absolute inset-x-0 top-0 z-30 border-transparent bg-transparent' : 'sticky top-0 z-30 border-b',
          !overlay && tone === 'light' && 'border-chore-100 bg-white',
          !overlay && tone === 'blue' && 'border-white/20 bg-[#0757bd] text-white',
          !overlay && tone === 'navy' && 'border-white/15 bg-[#061a3c] text-white',
        )}
      >
        <div className="mx-auto flex min-h-[4.75rem] max-w-[90rem] flex-wrap items-center justify-between gap-x-3 px-4 py-2 sm:h-[4.75rem] sm:flex-nowrap sm:px-6 sm:py-0 lg:px-10">
        <Link
          to="/"
          aria-label={`${WORDMARK} home`}
          className={cn(
            'inline-flex min-h-11 shrink-0 items-center rounded-lg px-1',
            dark && !overlay && 'bg-white/95 px-2',
          )}
        >
          <Logo className="h-8 sm:h-9" />
        </Link>

        <nav
          id="public-navigation"
          aria-label="Primary"
          className={cn(
            'order-4 w-full border-t pt-2 sm:order-none sm:flex sm:w-auto sm:items-center sm:justify-center sm:gap-x-6 sm:border-0 sm:pt-0',
            dark ? 'border-white/15' : 'border-chore-100',
            menuOpen ? 'grid grid-cols-2' : 'hidden',
          )}
        >
          {SITE_NAV.map((item) => (
            <NavLink
              key={item.label}
              to={item.to}
              onClick={() => setMenuOpen(false)}
              className={({ isActive }) =>
                cn(
                  'inline-flex min-h-11 items-center border-b-2 px-2 font-display text-sm font-bold transition-colors sm:px-1',
                  isActive
                    ? dark
                      ? 'border-[#ffb277] text-white'
                      : 'border-chore-600 text-chore-800'
                    : dark
                      ? 'border-transparent text-white/80 hover:text-white'
                      : 'border-transparent text-navy-700 hover:text-chore-700',
                )
              }
            >
              {item.label}
            </NavLink>
          ))}
        </nav>

        <div className="flex items-center gap-2">
          <AccountControl dark={dark} />
          <button
            type="button"
            aria-label={menuOpen ? 'Close navigation menu' : 'Open navigation menu'}
            aria-expanded={menuOpen}
            aria-controls="public-navigation"
            onClick={() => setMenuOpen((open) => !open)}
            className={cn(
              'inline-flex size-11 items-center justify-center rounded-full transition-colors sm:hidden',
              dark ? 'bg-white/10 text-white hover:bg-white/20' : 'bg-chore-50 text-navy-800 hover:bg-chore-100',
            )}
          >
            <Icon name={menuOpen ? 'close' : 'menu'} />
          </button>
        </div>
        </div>
      </header>
    </>
  )
}

/** Per-role quick link into the app, shown in the signed-in account menu below. */
const ROLE_QUICK_LINKS: Partial<Record<string, { to: string; label: string; icon: 'shield' | 'layers' | 'building' | 'cap' | 'users' | 'book' }>> = {
  super_admin: { to: '/admin', label: 'Admin overview', icon: 'shield' },
  content_owner: { to: '/courses', label: 'Courses', icon: 'layers' },
  school_admin: { to: '/school', label: 'Educator/School dashboard', icon: 'building' },
  teacher: { to: '/classes', label: 'My classes', icon: 'cap' },
  parent: { to: '/family', label: 'Family hub', icon: 'users' },
  supervisor: { to: '/family', label: 'Family hub', icon: 'users' },
  student: { to: '/learn', label: 'Continue learning', icon: 'book' },
}

/** Sign in / Start free for guests; an account menu (dashboard + role quick links + sign out) once authenticated. */
function AccountControl({ dark }: { dark: boolean }) {
  const { status, user, logout } = useAuth()
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

  if (status === 'loading') {
    return <div className="h-11 w-24" aria-hidden="true" />
  }

  if (status !== 'authenticated') {
    return (
      <>
        <Link
          to="/login"
          className={cn(
            'hidden min-h-11 items-center px-3 font-display text-sm font-bold sm:inline-flex',
            dark ? 'text-white hover:text-white/75' : 'text-navy-700 hover:text-chore-700',
          )}
        >
          Sign in
        </Link>
        <LinkButton to="/register" variant={dark ? 'accent' : 'parent'} size="md">
          Start free
        </LinkButton>
      </>
    )
  }

  const name = user?.user.name ?? 'Account'
  const roles = user?.user.roles ?? []
  const quickLinks = [...new Map(roles.map((r) => ROLE_QUICK_LINKS[r]).filter(Boolean).map((l) => [l!.to, l!])).values()]

  return (
    <div className="relative" ref={ref}>
      <button
        onClick={() => setOpen((v) => !v)}
        aria-haspopup="menu"
        aria-expanded={open}
        className={cn(
          'flex min-h-11 items-center gap-2 rounded-full px-1.5 py-1 pr-2.5 transition-colors',
          dark ? 'hover:bg-white/10' : 'hover:bg-chore-50',
        )}
      >
        <Avatar name={name} size="sm" />
        <span className={cn('hidden max-w-[8rem] truncate text-sm font-bold sm:inline', dark ? 'text-white' : 'text-navy-800')}>
          {user?.user.first_name ?? name}
        </span>
        <Icon name="chevron" className={cn('size-4', dark ? 'text-white/70' : 'text-muted')} />
      </button>

      {open && (
        <div
          role="menu"
          className="absolute right-0 z-40 mt-2 w-60 overflow-hidden rounded-xl border border-border bg-surface text-left shadow-lg animate-step-in"
        >
          <div className="border-b border-border px-4 py-3">
            <p className="truncate text-sm font-semibold text-foreground">{name}</p>
            <p className="truncate text-xs text-muted">{user?.user.email}</p>
          </div>
          <Link
            role="menuitem"
            to="/home"
            onClick={() => setOpen(false)}
            className="flex min-h-11 w-full items-center gap-2.5 px-4 py-2.5 text-sm font-semibold text-foreground hover:bg-surface-muted"
          >
            <Icon name="home" className="size-[18px] text-muted" />
            Go to dashboard
          </Link>
          {quickLinks.map((link) => (
            <Link
              key={link.to}
              role="menuitem"
              to={link.to}
              onClick={() => setOpen(false)}
              className="flex min-h-11 w-full items-center gap-2.5 px-4 py-2.5 text-sm text-foreground hover:bg-surface-muted"
            >
              <Icon name={link.icon} className="size-[18px] text-muted" />
              {link.label}
            </Link>
          ))}
          <button
            role="menuitem"
            onClick={() => {
              setOpen(false)
              void logout()
            }}
            className="flex min-h-11 w-full items-center gap-2.5 border-t border-border px-4 py-2.5 text-sm text-foreground hover:bg-surface-muted"
          >
            <Icon name="logout" className="size-[18px] text-muted" />
            Sign out
          </button>
        </div>
      )}
    </div>
  )
}
