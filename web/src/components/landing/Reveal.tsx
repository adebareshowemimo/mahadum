import { useEffect, useRef, useState, type CSSProperties, type ReactNode } from 'react'
import { cn } from '@/lib/cn'

/**
 * Reveals children once they scroll into view. Uses IntersectionObserver
 * rather than a scroll listener so it costs nothing on the main thread, and
 * unobserves after firing — the reveal is one-way, never a re-animation on
 * scroll-back (which reads as jitter).
 *
 * The motion itself lives in `.reveal` / `.is-in` (app.css), where the
 * prefers-reduced-motion guard also forces content visible. If the observer
 * never fires (very old browser), we fall back to visible after mount rather
 * than leaving the page blank.
 */
export function Reveal({
  children,
  className,
  delay = 0,
  as: Tag = 'div',
}: {
  children: ReactNode
  className?: string
  /** Stagger, in ms — for sibling cards revealing in sequence. */
  delay?: number
  as?: 'div' | 'section' | 'li'
}) {
  const ref = useRef<HTMLElement | null>(null)
  const [shown, setShown] = useState(false)

  useEffect(() => {
    const el = ref.current
    if (!el || typeof IntersectionObserver === 'undefined') {
      setShown(true) // no observer support → show rather than hide forever
      return
    }
    const observer = new IntersectionObserver(
      ([entry]) => {
        if (!entry.isIntersecting) return
        setShown(true)
        observer.disconnect()
      },
      { rootMargin: '0px 0px -12% 0px', threshold: 0.05 },
    )
    observer.observe(el)
    return () => observer.disconnect()
  }, [])

  return (
    <Tag
      ref={ref as never}
      className={cn('reveal', shown && 'is-in', className)}
      style={delay ? ({ '--reveal-delay': `${delay}ms` } as CSSProperties) : undefined}
    >
      {children}
    </Tag>
  )
}
