import { cn } from '@/lib/cn'

const SIZES = { sm: 'size-8 text-xs', md: 'size-10 text-sm', lg: 'size-12 text-base' } as const

const RING = [
  'bg-rainbow-blue', 'bg-rainbow-teal', 'bg-rainbow-green', 'bg-rainbow-orange',
  'bg-rainbow-purple', 'bg-rainbow-pink',
]

export const AVATAR_PRESETS = [
  { id: 1, label: 'Amara', background: '#dbeafe', skin: '#8d5524', hair: '#2b1b12', shirt: '#2563eb' },
  { id: 2, label: 'Kemi', background: '#fce7f3', skin: '#c68642', hair: '#3b2418', shirt: '#db2777' },
  { id: 3, label: 'Chidi', background: '#dcfce7', skin: '#6f3b1f', hair: '#17120f', shirt: '#16845b' },
  { id: 4, label: 'Zainab', background: '#fef3c7', skin: '#b87345', hair: '#29201d', shirt: '#d97706' },
  { id: 5, label: 'Tunde', background: '#ede9fe', skin: '#4f2e1d', hair: '#17120f', shirt: '#7c3aed' },
  { id: 6, label: 'Ada', background: '#cffafe', skin: '#d69a6a', hair: '#4a2c20', shirt: '#0891b2' },
  { id: 7, label: 'Bello', background: '#ffedd5', skin: '#9c5f38', hair: '#251a16', shirt: '#ea580c' },
  { id: 8, label: 'Nneka', background: '#e0e7ff', skin: '#5c331d', hair: '#17120f', shirt: '#4f46e5' },
] as const

/** Give every learner a stable illustrated fallback before they personalise it. */
export function learnerAvatarPresetId(learnerId: number): number {
  return ((Math.max(learnerId, 1) - 1) % AVATAR_PRESETS.length) + 1
}

function initials(name: string): string {
  const parts = name.trim().split(/\s+/)
  return ((parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? '')).toUpperCase() || '?'
}

export interface AvatarProps {
  name: string
  src?: string
  avatarId?: number | null
  size?: keyof typeof SIZES
  className?: string
}

export function Avatar({ name, src, avatarId, size = 'md', className }: AvatarProps) {
  const color = RING[name.length % RING.length]
  const preset = AVATAR_PRESETS.find((item) => item.id === avatarId)
  return (
    <span
      className={cn(
        'inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full font-display font-semibold text-white',
        SIZES[size],
        !src && !preset && color,
        className,
      )}
      title={name}
    >
      {src ? (
        <img src={src} alt="" className="size-full object-cover" />
      ) : preset ? (
        <svg viewBox="0 0 64 64" aria-hidden="true" className="size-full">
          <rect width="64" height="64" fill={preset.background} />
          <path d="M8 64c2-14 11-21 24-21s22 7 24 21" fill={preset.shirt} />
          <circle cx="32" cy="29" r="17" fill={preset.skin} />
          {preset.id % 2 === 0 ? (
            <path d="M15 29c0-14 7-22 17-22s18 8 18 22c-4-7-10-11-18-11s-13 4-17 11" fill={preset.hair} />
          ) : (
            <path d="M16 25C17 12 24 7 33 7c10 0 16 7 16 19-5-5-10-8-17-8-6 0-11 2-16 7" fill={preset.hair} />
          )}
          <circle cx="26" cy="30" r="1.7" fill="#17120f" />
          <circle cx="38" cy="30" r="1.7" fill="#17120f" />
          <path d="M26 37c3.6 3 8.4 3 12 0" fill="none" stroke="#6b2f25" strokeWidth="2" strokeLinecap="round" />
        </svg>
      ) : initials(name)}
    </span>
  )
}
