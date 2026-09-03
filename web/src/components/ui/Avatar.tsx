import { cn } from '@/lib/cn'

const SIZES = { sm: 'size-8 text-xs', md: 'size-10 text-sm', lg: 'size-12 text-base' } as const

const RING = [
  'bg-rainbow-blue', 'bg-rainbow-teal', 'bg-rainbow-green', 'bg-rainbow-orange',
  'bg-rainbow-purple', 'bg-rainbow-pink',
]

export const AVATAR_PRESETS = [
  { id: 1, label: 'Bat', src: 'avatar-bat.png' },
  { id: 2, label: 'Rooster', src: 'avatar-rooster.png' },
  { id: 3, label: 'Cow', src: 'avatar-cow.png' },
  { id: 4, label: 'Crocodile', src: 'avatar-crocodile.png' },
  { id: 5, label: 'Deer', src: 'avatar-deer.png' },
  { id: 6, label: 'Donkey', src: 'avatar-donkey.png' },
  { id: 7, label: 'Dove', src: 'avatar-dove.png' },
  { id: 8, label: 'Duck', src: 'avatar-duck.png' },
  { id: 9, label: 'Eagle', src: 'avatar-eagle.png' },
  { id: 10, label: 'Elephant', src: 'avatar-elephant.png' },
  { id: 11, label: 'Goat', src: 'avatar-goat.png' },
  { id: 12, label: 'Hawk', src: 'avatar-hawk.png' },
  { id: 13, label: 'Hen', src: 'avatar-hen.png' },
  { id: 14, label: 'Horse', src: 'avatar-horse.png' },
  { id: 15, label: 'Lion', src: 'avatar-lion.png' },
  { id: 16, label: 'Lizard', src: 'avatar-lizard.png' },
  { id: 17, label: 'Mosquito', src: 'avatar-mosquito.png' },
  { id: 18, label: 'Peacock', src: 'avatar-peacock.png' },
  { id: 19, label: 'Cultural portrait 10', src: 'avatar-cultural-10.png' },
  { id: 20, label: 'Cultural portrait 11', src: 'avatar-cultural-11.png' },
  { id: 21, label: 'Cultural portrait 12', src: 'avatar-cultural-12.png' },
  { id: 22, label: 'Cultural portrait 13', src: 'avatar-cultural-13.png' },
  { id: 23, label: 'Cultural portrait 14', src: 'avatar-cultural-14.png' },
  { id: 24, label: 'Cultural portrait 15', src: 'avatar-cultural-15.png' },
  { id: 25, label: 'Cultural portrait 16', src: 'avatar-cultural-16.png' },
  { id: 26, label: 'Cultural portrait 17', src: 'avatar-cultural-17.png' },
  { id: 27, label: 'Cultural portrait 18', src: 'avatar-cultural-18.png' },
  { id: 28, label: 'Cultural portrait 19', src: 'avatar-cultural-19.png' },
  { id: 29, label: 'Cultural portrait 20', src: 'avatar-cultural-20.png' },
  { id: 30, label: 'Cultural portrait 21', src: 'avatar-cultural-21.png' },
  { id: 31, label: 'Cultural portrait 22', src: 'avatar-cultural-22.png' },
  { id: 32, label: 'Cultural portrait 23', src: 'avatar-cultural-23.png' },
  { id: 33, label: 'Cultural portrait 24', src: 'avatar-cultural-24.png' },
  { id: 34, label: 'Cultural portrait 4', src: 'avatar-cultural-4.png' },
  { id: 35, label: 'Cultural portrait 5', src: 'avatar-cultural-5.png' },
  { id: 36, label: 'Cultural portrait 6', src: 'avatar-cultural-6.png' },
  { id: 37, label: 'Cultural portrait 7', src: 'avatar-cultural-7.png' },
  { id: 38, label: 'Cultural portrait 8', src: 'avatar-cultural-8.png' },
  { id: 39, label: 'Cultural portrait 9', src: 'avatar-cultural-9.png' },
  { id: 40, label: 'Pig', src: 'avatar-pig.png' },
  { id: 41, label: 'Praying mantis', src: 'avatar-praying-mantis.png' },
  { id: 42, label: 'Rabbit', src: 'avatar-rabbit.png' },
  { id: 43, label: 'Rat', src: 'avatar-rat.png' },
  { id: 44, label: 'Scorpion', src: 'avatar-scorpion.png' },
  { id: 45, label: 'Snake', src: 'avatar-snake.png' },
  { id: 46, label: 'Song bird', src: 'avatar-song-bird.png' },
  { id: 47, label: 'Spider', src: 'avatar-spider.png' },
  { id: 48, label: 'Tiger', src: 'avatar-tiger.png' },
  { id: 49, label: 'Turtle', src: 'avatar-turtle.png' },
  { id: 50, label: 'Vulture', src: 'avatar-vulture.png' },
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
        <img
          src={presetUrl(preset.src)}
          alt=""
          loading="lazy"
          decoding="async"
          className="size-full bg-white object-cover"
        />
      ) : initials(name)}
    </span>
  )
}
