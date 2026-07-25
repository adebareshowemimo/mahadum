import { useState } from 'react'
import { Link, NavLink } from 'react-router-dom'
import { Logo } from '@/components/Logo'
import { Icon, LinkButton } from '@/components/ui'
import { WORDMARK } from '@/lib/brand'
import { cn } from '@/lib/cn'

type ConceptTone = 'light' | 'blue' | 'navy'

// Shared public navigation for every marketing and account-entry surface.
const SITE_NAV = [
  { to: '/families', label: 'Families' },
  { to: '/schools', label: 'Schools' },
  { to: '/institutions', label: 'Institutions' },
  { to: '/about', label: 'About' },
  { to: '/pricing', label: 'Pricing' },
  { to: '/contact', label: 'Contact' },
]

export function ConceptHeader({ tone = 'light', overlay = false }: { tone?: ConceptTone; overlay?: boolean }) {
  const dark = tone !== 'light'
  const [menuOpen, setMenuOpen] = useState(false)
  return (
    <header
      className={cn(
        overlay ? 'absolute inset-x-0 top-0 z-30 border-transparent bg-transparent' : 'relative z-30 border-b',
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
  )
}
