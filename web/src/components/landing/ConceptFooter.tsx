import { Link } from 'react-router-dom'
import { Logo } from '@/components/Logo'
import { Icon } from '@/components/ui'
import { cn } from '@/lib/cn'
import { TAGLINE } from '@/lib/brand'
import { useAuth } from '@/lib/auth/AuthProvider'

type ConceptTone = 'light' | 'blue' | 'navy'

export function ConceptFooter({ tone = 'light' }: { tone?: ConceptTone }) {
  const dark = tone !== 'light'
  const { status } = useAuth()
  // A loading auth state means a stored token is being verified. Keep the
  // account destination visible instead of flashing a misleading sign-in CTA.
  const accountLink = status === 'unauthenticated'
    ? { to: '/login', label: 'Sign in' }
    : { to: '/home', label: 'Dashboard' }

  return (
    <footer className={cn('border-t', dark ? 'border-white/15 bg-[#061a3c] text-white' : 'border-chore-100 bg-white')}>
      <div className="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <div className="grid gap-10 sm:grid-cols-2 lg:grid-cols-[1.35fr_0.8fr_0.8fr] lg:gap-16">
          <div className="max-w-sm sm:col-span-2 lg:col-span-1">
            <span className={cn('inline-flex rounded-lg p-2', dark && 'bg-white')}>
              <Logo className="h-16" />
            </span>
            <p className={cn('mt-4 max-w-[34ch] text-sm font-semibold leading-relaxed', dark ? 'text-white/75' : 'text-navy-600')}>{TAGLINE}</p>
          </div>
          <nav aria-label="Explore Mahadum">
            <h2 className={cn('font-display text-sm font-extrabold', dark ? 'text-[#ffb277]' : 'text-chore-700')}>Explore</h2>
            <div className="mt-3 grid text-sm font-bold">
              <Link className="inline-flex min-h-11 items-center hover:underline" to="/families">Families</Link>
              <Link className="inline-flex min-h-11 items-center hover:underline" to="/schools">Educators/Schools</Link>
              <Link className="inline-flex min-h-11 items-center hover:underline" to="/institutions">Institutions</Link>
              <Link className="inline-flex min-h-11 items-center hover:underline" to="/about">About us</Link>
              <Link className="inline-flex min-h-11 items-center hover:underline" to="/pricing">Pricing</Link>
              <a className="inline-flex min-h-11 items-center hover:underline" href="/guides">Language guides</a>
              <a className="inline-flex min-h-11 items-center hover:underline" href="/vote">Vote for the next language</a>
              <Link className="inline-flex min-h-11 items-center hover:underline" to={accountLink.to}>
                {accountLink.label}
              </Link>
            </div>
          </nav>
          <nav aria-label="Help and legal information">
            <h2 className={cn('font-display text-sm font-extrabold', dark ? 'text-[#ffb277]' : 'text-chore-700')}>Help &amp; trust</h2>
            <div className="mt-3 grid text-sm font-bold">
              <Link className="inline-flex min-h-11 items-center hover:underline" to="/contact">Contact us</Link>
              <a className="inline-flex min-h-11 items-center hover:underline" href="https://wa.me/2349028111111?text=Hello%20Mahadum360%2C%20I%27d%20like%20to%20learn%20more.">Chat with us on WhatsApp</a>
              <a className="inline-flex min-h-11 items-center hover:underline" href="https://www.youtube.com/@mahadum360" aria-label="Mahadum360 on YouTube"><svg aria-hidden="true" viewBox="0 0 24 24" className="mr-2 size-4" fill="currentColor"><path d="M22 7s-.3-2-2-2.3C17.5 4.4 12 4.4 12 4.4s-5.5 0-8 .3C2.3 5 2 7 2 7s-.4 2.3-.4 5 .4 5 .4 5 .3 2 2 2.3c2.5.3 8 .3 8 .3s5.5 0 8-.3c1.7-.3 2-2.3 2-2.3s.4-2.3.4-5-.4-5-.4-5ZM10 16V8l6 4Z" /></svg>YouTube</a>
              <a className="inline-flex min-h-11 items-center hover:underline" href="https://www.instagram.com/mahadum360/" aria-label="Mahadum360 on Instagram"><svg aria-hidden="true" viewBox="0 0 24 24" className="mr-2 size-4" fill="none" stroke="currentColor" strokeWidth="2"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="18" cy="6" r="1"/></svg>Instagram</a>
              <a className="inline-flex min-h-11 items-center hover:underline" href="https://www.facebook.com/Mahadum360/" aria-label="Mahadum360 on Facebook"><svg aria-hidden="true" viewBox="0 0 24 24" className="mr-2 size-4" fill="currentColor"><path d="M14 22v-9h3l.5-4H14V7c0-1 .3-2 2-2h2V1h-3c-3 0-5 2-5 5v3H7v4h3v9Z"/></svg>Facebook</a>
              <Link className="inline-flex min-h-11 items-center hover:underline" to="/child-safety">Child safety</Link>
              <Link className="inline-flex min-h-11 items-center hover:underline" to="/accessibility">Accessibility</Link>
              <Link className="inline-flex min-h-11 items-center hover:underline" to="/privacy">Privacy</Link>
              <Link className="inline-flex min-h-11 items-center hover:underline" to="/terms">Terms</Link>
            </div>
          </nav>
        </div>
        <div
          className={cn(
            'mt-8 grid gap-4 border-t pt-6 sm:grid-cols-[1fr_auto_auto] sm:items-center sm:gap-8',
            dark ? 'border-white/15' : 'border-chore-100',
          )}
        >
          <p className={cn('text-xs font-semibold', dark ? 'text-white/60' : 'text-navy-500')}>
            © Mahadum360, RC 9601595. · {new Date().getFullYear()} · Lagos, Nigeria
          </p>
          <p className={cn('text-xs font-semibold', dark ? 'text-white/60' : 'text-navy-500')}>
            <a href="/learn-yoruba" className="hover:underline">Yorùbá</a> · <a href="/learn-igbo" className="hover:underline">Igbo</a> · <a href="/learn-hausa" className="hover:underline">Hausa</a> · English
          </p>
          <button
            type="button"
            onClick={() => {
              const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches
              window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' })
            }}
            className={cn(
              'inline-flex min-h-11 items-center gap-2 justify-self-start font-display text-sm font-extrabold hover:underline sm:justify-self-end',
              dark ? 'text-white' : 'text-chore-700',
            )}
          >
            Back to top
            <span className={cn('flex size-9 items-center justify-center rounded-full', dark ? 'bg-white/10' : 'bg-chore-50')}>
              <Icon name="arrow-left" className="size-4 rotate-90" />
            </span>
          </button>
        </div>
      </div>
    </footer>
  )
}
