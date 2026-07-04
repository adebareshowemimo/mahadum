import { cn } from '@/lib/cn'

const SIZES = { sm: 'size-8 text-xs', md: 'size-10 text-sm', lg: 'size-12 text-base' } as const

const RING = [
  'bg-rainbow-blue', 'bg-rainbow-teal', 'bg-rainbow-green', 'bg-rainbow-orange',
  'bg-rainbow-purple', 'bg-rainbow-pink',
]

function initials(name: string): string {
  const parts = name.trim().split(/\s+/)
  return ((parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? '')).toUpperCase() || '?'
}

export interface AvatarProps {
  name: string
  src?: string
  size?: keyof typeof SIZES
  className?: string
}

export function Avatar({ name, src, size = 'md', className }: AvatarProps) {
  const color = RING[name.length % RING.length]
  return (
    <span
      className={cn(
        'inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full font-display font-semibold text-white',
        SIZES[size],
        !src && color,
        className,
      )}
      title={name}
    >
      {src ? <img src={src} alt={name} className="size-full object-cover" /> : initials(name)}
    </span>
  )
}
