import type { CSSProperties, ReactNode } from 'react'
import { ConceptHeader } from '@/components/landing/ConceptHeader'
import { Logo } from '@/components/Logo'
import { TAGLINE, WORDMARK } from '@/lib/brand'
import { cn } from '@/lib/cn'

/**
 * Authentication shell.
 *
 * Login and registration use the image-led split layout so the first account
 * touchpoint feels continuous with the public site. Password recovery keeps
 * the compact fallback layout by omitting the visual props.
 */
export function AuthLayout({
  title,
  subtitle,
  children,
  footer,
  eyebrow,
  image,
  imageAlt,
  imagePosition = 'object-center',
  visualTitle,
  visualBody,
  phrases = [],
}: {
  title: string
  subtitle?: string
  children: ReactNode
  footer?: ReactNode
  eyebrow?: string
  image?: string
  imageAlt?: string
  imagePosition?: string
  visualTitle?: string
  visualBody?: string
  phrases?: string[]
}) {
  if (image && imageAlt && visualTitle) {
    return (
      <div className="variant-page min-h-screen bg-[#f7fbff] text-navy-950">
        <a href="#auth-main" className="variant-skip-link">Skip to account form</a>

        <ConceptHeader />

        <main id="auth-main" className="mx-auto grid min-h-[calc(100vh-4.75rem)] max-w-[100rem] lg:grid-cols-[1.05fr_0.95fr]">
          <section className="order-2 p-4 pt-0 sm:p-6 sm:pt-0 lg:order-1 lg:p-4 lg:pr-0">
            <div className="relative min-h-[25rem] overflow-hidden rounded-[1rem] lg:sticky lg:top-4 lg:min-h-[calc(100vh-6.75rem)]">
              <img
                src={image}
                alt={imageAlt}
                loading="eager"
                decoding="async"
                draggable={false}
                className={cn('motion-hero-media absolute inset-0 size-full object-cover', imagePosition)}
              />
              <div className="absolute inset-0 bg-[linear-gradient(180deg,rgba(6,26,60,0.04)_22%,rgba(6,26,60,0.92)_100%)]" />

              {phrases.slice(0, 3).map((phrase, index) => (
                <span
                  key={phrase}
                  className={cn(
                    'motion-float absolute rounded-full bg-white px-4 py-2 font-display text-sm font-extrabold text-chore-700 shadow-md',
                    index === 0 && 'left-5 top-5 sm:left-8 sm:top-8',
                    index === 1 && 'right-5 top-24 sm:right-8 sm:top-28',
                    index === 2 && 'right-6 top-[44%] sm:right-10',
                  )}
                  style={{ '--float-i': index } as CSSProperties}
                >
                  {phrase}
                </span>
              ))}

              <div className="motion-hero-copy absolute inset-x-0 bottom-0 z-10 max-w-2xl p-6 text-white sm:p-10 lg:p-12">
                <p className="font-display text-3xl font-extrabold leading-tight sm:text-5xl">{visualTitle}</p>
                {visualBody && (
                  <p className="mt-4 max-w-xl text-base font-semibold leading-relaxed text-navy-100 sm:text-lg">
                    {visualBody}
                  </p>
                )}
              </div>
            </div>
          </section>

          <section className="order-1 flex items-start justify-center px-4 py-12 sm:px-8 sm:py-16 lg:order-2 lg:px-12 lg:py-20">
            <div className="auth-form-enter w-full max-w-[31rem]">
              {eyebrow && (
                <p className="inline-flex items-center gap-2 rounded-full bg-chore-50 px-3 py-1.5 text-sm font-bold text-chore-700">
                  <span aria-hidden="true" className="size-2 rounded-full bg-rainbow-orange" />
                  {eyebrow}
                </p>
              )}
              <h1 className="mt-5 font-display text-[clamp(2.5rem,5vw,4rem)] font-extrabold leading-[1] tracking-[-0.035em] text-navy-950">
                {title}
              </h1>
              {subtitle && <p className="mt-4 max-w-md text-base font-semibold leading-relaxed text-navy-600 sm:text-lg">{subtitle}</p>}

              <div className="mt-8 rounded-[1rem] bg-white p-6 shadow-md sm:p-8">{children}</div>

              {footer && <div className="mt-6 text-center text-sm font-semibold text-navy-600">{footer}</div>}
              <p className="mt-8 text-center text-xs font-semibold text-navy-500">
                {TAGLINE}
              </p>
              <p className="mt-2 text-center text-xs text-navy-400">
                © {WORDMARK} · {new Date().getFullYear()} · Lagos, Nigeria
              </p>
            </div>
          </section>
        </main>
      </div>
    )
  }

  return (
    <div className="flex min-h-screen flex-col items-center justify-center bg-background px-4 py-10">
      <div className="w-full max-w-md">
        <div className="mb-8 flex flex-col items-center gap-3 text-center">
          <Logo className="h-10" />
          <p className="max-w-xs text-sm font-medium text-muted">{TAGLINE}</p>
          <div className="mt-2">
            <h1 className="font-display text-2xl font-bold text-foreground">{title}</h1>
            {subtitle && <p className="mt-1 text-sm text-muted">{subtitle}</p>}
          </div>
        </div>

        <div className="rounded-2xl border border-border bg-surface p-6 shadow-sm sm:p-8">{children}</div>

        {footer && <div className="mt-6 text-center text-sm text-muted">{footer}</div>}
        <p className="mt-6 text-center text-xs text-muted">
          © {WORDMARK} · {new Date().getFullYear()} · Lagos, Nigeria
        </p>
      </div>
    </div>
  )
}
