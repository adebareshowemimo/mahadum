import { cn } from '@/lib/cn'

/** Unmodified official Mahadum360 PNG, sized by height. */
export function Logo({ className }: { className?: string }) {
  return (
    <img
      src="/images/m360_logo_official.png"
      alt="Mahadum.360"
      draggable={false}
      className={cn('h-9 w-auto select-none', className)}
    />
  )
}
