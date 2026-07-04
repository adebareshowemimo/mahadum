import { cn } from '@/lib/cn'

/** Mahadum.360 wordmark lockup (served from /logo.svg). Sized by height. */
export function Logo({ className }: { className?: string }) {
  return (
    <img
      src="/logo.svg"
      alt="Mahadum.360"
      draggable={false}
      className={cn('h-9 w-auto select-none', className)}
    />
  )
}
