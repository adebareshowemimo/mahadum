import { cn } from '@/lib/cn'

const SIZES = { sm: 'size-8 text-xs', md: 'size-10 text-sm', lg: 'size-12 text-base' } as const

const RING = [
  'bg-rainbow-blue', 'bg-rainbow-teal', 'bg-rainbow-green', 'bg-rainbow-orange',
  'bg-rainbow-purple', 'bg-rainbow-pink',
]

export const AVATAR_PRESETS = [
  { id: 1, label: 'Bat', src: 'BAT.png' },
  { id: 2, label: 'Rooster', src: 'COCK.png' },
  { id: 3, label: 'Cow', src: 'COW.png' },
  { id: 4, label: 'Crocodile', src: 'CROCODILE.png' },
  { id: 5, label: 'Deer', src: 'DEER.png' },
  { id: 6, label: 'Donkey', src: 'DONKEY.png' },
  { id: 7, label: 'Dove', src: 'DOVE.png' },
  { id: 8, label: 'Duck', src: 'DUCK.png' },
  { id: 9, label: 'Eagle', src: 'EAGLE.png' },
  { id: 10, label: 'Elephant', src: 'ELEPHANT.png' },
  { id: 11, label: 'Goat', src: 'GOAT.png' },
  { id: 12, label: 'Hawk', src: 'HAWK.png' },
  { id: 13, label: 'Hen', src: 'HEN.png' },
  { id: 14, label: 'Horse', src: 'HORSE.png' },
  { id: 15, label: 'Lion', src: 'LION.png' },
  { id: 16, label: 'Lizard', src: 'LIZARD.png' },
  { id: 17, label: 'Mosquito', src: 'MOSQUITO.png' },
  { id: 18, label: 'Peacock', src: 'PEACOCK.png' },
  { id: 19, label: 'Cultural portrait 10', src: 'Picture10.png' },
  { id: 20, label: 'Cultural portrait 11', src: 'Picture11.png' },
  { id: 21, label: 'Cultural portrait 12', src: 'Picture12.png' },
  { id: 22, label: 'Cultural portrait 13', src: 'Picture13.png' },
  { id: 23, label: 'Cultural portrait 14', src: 'Picture14.png' },
  { id: 24, label: 'Cultural portrait 15', src: 'Picture15.png' },
  { id: 25, label: 'Cultural portrait 16', src: 'Picture16.png' },
  { id: 26, label: 'Cultural portrait 17', src: 'Picture17.png' },
  { id: 27, label: 'Cultural portrait 18', src: 'Picture18.png' },
  { id: 28, label: 'Cultural portrait 19', src: 'Picture19.png' },
  { id: 29, label: 'Cultural portrait 20', src: 'Picture20.png' },
  { id: 30, label: 'Cultural portrait 21', src: 'Picture21.png' },
  { id: 31, label: 'Cultural portrait 22', src: 'Picture22.png' },
  { id: 32, label: 'Cultural portrait 23', src: 'Picture23.png' },
  { id: 33, label: 'Cultural portrait 24', src: 'Picture24.png' },
  { id: 34, label: 'Cultural portrait 4', src: 'Picture4.png' },
  { id: 35, label: 'Cultural portrait 5', src: 'Picture5.png' },
  { id: 36, label: 'Cultural portrait 6', src: 'Picture6.png' },
  { id: 37, label: 'Cultural portrait 7', src: 'Picture7.png' },
  { id: 38, label: 'Cultural portrait 8', src: 'Picture8.png' },
  { id: 39, label: 'Cultural portrait 9', src: 'Picture9.png' },
  { id: 40, label: 'Pig', src: 'PIG.png' },
  { id: 41, label: 'Praying mantis', src: 'PRAYING MANTIS.png' },
  { id: 42, label: 'Rabbit', src: 'RABBIT.png' },
  { id: 43, label: 'Rat', src: 'RAT.png' },
  { id: 44, label: 'Scorpion', src: 'SCORPION.png' },
  { id: 45, label: 'Snake', src: 'SNAKE.png' },
  { id: 46, label: 'Song bird', src: 'SONG BIRD.png' },
  { id: 47, label: 'Spider', src: 'SPIDER.png' },
  { id: 48, label: 'Tiger', src: 'TIGER.png' },
  { id: 49, label: 'Turtle', src: 'TURTLE.png' },
  { id: 50, label: 'Vulture', src: 'VULTURE.png' },
  { id: 51, label: 'Yoruba girl in indigo', src: 'generated-yoruba-girl.png' },
  { id: 52, label: 'Igbo boy in isiagu', src: 'generated-igbo-boy.png' },
  { id: 53, label: 'Hausa girl in emerald', src: 'generated-hausa-girl.png' },
  { id: 54, label: 'Hausa boy in teal', src: 'generated-hausa-boy.png' },
] as const

const AVATAR_ASSET_BASE = '/Mahadam%20avatar%20images/'

function presetUrl(filename: string): string {
  return `${AVATAR_ASSET_BASE}${encodeURIComponent(filename)}`
}

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
        <img src={presetUrl(preset.src)} alt="" className="size-full bg-white object-cover" />
      ) : initials(name)}
    </span>
  )
}
