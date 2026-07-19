import { useState } from 'react'
import { cn } from '@/lib/cn'

/**
 * An image slot that degrades gracefully.
 *
 * If an image cannot load, the slot falls back to a branded daylight panel of
 * the same aspect ratio so the page keeps its structure and accessible label.
 *
 * `label` is only shown in the placeholder state; `alt` is the real
 * accessibility text and is always applied to the <img>.
 */
export function Figure({
  src,
  alt,
  label,
  className,
  imgClassName,
  priority = false,
}: {
  src: string
  alt: string
  /** Short caption shown only while the asset is missing. */
  label?: string
  className?: string
  imgClassName?: string
  /** Hero imagery — skip lazy loading so it isn't deferred below the fold. */
  priority?: boolean
}) {
  const [failed, setFailed] = useState(false)

  return (
    <div className={cn('relative overflow-hidden', className)}>
      {!failed ? (
        <img
          src={src}
          alt={alt}
          loading={priority ? 'eager' : 'lazy'}
          decoding="async"
          draggable={false}
          onError={() => setFailed(true)}
          className={cn('size-full object-cover', imgClassName)}
        />
      ) : (
        <div
          role="img"
          aria-label={alt}
          className="landing-image-fallback flex size-full flex-col items-center justify-center gap-2 p-6 text-center"
        >
          <span aria-hidden="true" className="text-3xl opacity-70">
            ◈
          </span>
          {label && (
            <span className="max-w-xs text-xs font-bold uppercase tracking-wide text-chore-700">
              {label}
            </span>
          )}
        </div>
      )}
    </div>
  )
}
