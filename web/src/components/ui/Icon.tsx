import type { SVGProps } from 'react'
import { cn } from '@/lib/cn'

// Minimal in-house line-icon set (currentColor stroke) so the app ships without
// an icon-library dependency. Add paths here as new nav/UI needs arise.
const PATHS = {
  home: <path d="M3 9.8 12 3l9 6.8M5 9.5V20h5v-6h4v6h5V9.5" />,
  book: (
    <>
      <path d="M12 6c-1.6-1-4-1.5-6.2-1.5V18c2.2 0 4.6.5 6.2 1.5 1.6-1 4-1.5 6.2-1.5V4.5C16 4.5 13.6 5 12 6z" />
      <path d="M12 6v13.5" />
    </>
  ),
  trophy: (
    <>
      <path d="M7 4h10v5a5 5 0 0 1-10 0z" />
      <path d="M7 6H4v1.5a3 3 0 0 0 3 3" />
      <path d="M17 6h3v1.5a3 3 0 0 1-3 3" />
      <path d="M9.5 20h5M12 14v6" />
    </>
  ),
  users: (
    <>
      <circle cx="9" cy="8" r="3.2" />
      <path d="M3.5 20a5.5 5.5 0 0 1 11 0" />
      <path d="M16 5.4a3 3 0 0 1 0 5.6M17.2 14.4a5.5 5.5 0 0 1 3.3 5.6" />
    </>
  ),
  wallet: (
    <>
      <path d="M3 8a2.5 2.5 0 0 1 2.5-2.5H17V8" />
      <rect x="3" y="8" width="18" height="11.5" rx="2.5" />
      <circle cx="16.5" cy="13.7" r="1.2" />
    </>
  ),
  card: (
    <>
      <rect x="3" y="5.5" width="18" height="13" rx="2.5" />
      <path d="M3 9.8h18M7 14.5h4" />
    </>
  ),
  gift: (
    <>
      <rect x="3.5" y="9" width="17" height="11" rx="1.5" />
      <path d="M3.5 13h17M12 9v11" />
      <path d="M12 9C12 6.2 10.6 4.5 9 4.5S6.6 6 6.6 7 7.6 9 12 9zm0 0c0-2.8 1.4-4.5 3-4.5S17.4 6 17.4 7 16.4 9 12 9z" />
    </>
  ),
  cap: (
    <>
      <path d="M2.5 9 12 5l9.5 4-9.5 4z" />
      <path d="M6 11v4.2c0 1.2 2.7 2.3 6 2.3s6-1.1 6-2.3V11M21.5 9.2v4.3" />
    </>
  ),
  clipboard: (
    <>
      <rect x="5" y="5" width="14" height="16" rx="2" />
      <path d="M9 5V4a1.5 1.5 0 0 1 1.5-1.5h3A1.5 1.5 0 0 1 15 4v1z" />
      <path d="M9 11h6M9 15h4" />
    </>
  ),
  layers: (
    <>
      <path d="M12 3 3 8l9 5 9-5z" />
      <path d="M3 13l9 5 9-5" />
    </>
  ),
  shield: <path d="M12 3 5 6v5c0 4.6 3 7.6 7 9 4-1.4 7-4.4 7-9V6z" />,
  building: (
    <>
      <rect x="5" y="3.5" width="14" height="17" rx="1.5" />
      <path d="M9 7.5h2M13 7.5h2M9 11h2M13 11h2M10 20.5v-3h4v3" />
    </>
  ),
  sparkles: (
    <>
      <path d="M12 4l1.6 4.4L18 10l-4.4 1.6L12 16l-1.6-4.4L6 10l4.4-1.6z" />
      <path d="M18.5 15l.7 1.8 1.8.7-1.8.7-.7 1.8-.7-1.8-1.8-.7 1.8-.7z" />
    </>
  ),
  menu: <path d="M4 7h16M4 12h16M4 17h16" />,
  close: <path d="M6 6l12 12M18 6 6 18" />,
  sun: (
    <>
      <circle cx="12" cy="12" r="4" />
      <path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5 19 19M19 5l-1.5 1.5M6.5 17.5 5 19" />
    </>
  ),
  moon: <path d="M20 13.5A8 8 0 1 1 10.5 4 6.5 6.5 0 0 0 20 13.5z" />,
  chevron: <path d="m6 9 6 6 6-6" />,
  logout: (
    <>
      <path d="M9 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h3" />
      <path d="M16 17l5-5-5-5M21 12H9" />
    </>
  ),
  bell: (
    <>
      <path d="M6 9.5a6 6 0 0 1 12 0c0 4.5 2 5.5 2 5.5H4s2-1 2-5.5z" />
      <path d="M10 19a2 2 0 0 0 4 0" />
    </>
  ),
  search: (
    <>
      <circle cx="11" cy="11" r="6.5" />
      <path d="m16 16 4.5 4.5" />
    </>
  ),
  plus: <path d="M12 5v14M5 12h14" />,
  'arrow-left': <path d="M19 12H5M11 6l-6 6 6 6" />,
} as const

export type IconName = keyof typeof PATHS

export interface IconProps extends Omit<SVGProps<SVGSVGElement>, 'name'> {
  name: IconName
}

export function Icon({ name, className, ...props }: IconProps) {
  return (
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth={1.8}
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      className={cn('size-5 shrink-0', className)}
      {...props}
    >
      {PATHS[name]}
    </svg>
  )
}
