import { cn } from '@/lib/cn'

/** Keep the original artwork on light surfaces and use its cutout on dark ones. */
export function Logo({ className }: { className?: string }) {
  return (
    <>
      <img
        src="/images/m360_logo_official.png"
        alt="Mahadum.360"
        draggable={false}
        className={cn('h-9 w-auto select-none dark:hidden', className)}
      />
      <img
        src="/images/m360_logo_dark_transparent.png"
        alt="Mahadum.360"
        draggable={false}
        className={cn('hidden h-9 w-auto select-none dark:block', className)}
      />
    </>
  )
}
