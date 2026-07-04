import { cn } from '@/lib/cn'
import { Avatar, Badge, Button, Card, CardBody } from '@/components/ui'

/* --------------------------------------------------------------- proverb card */

export function ProverbCard({ proverb, translation, language }: { proverb: string; translation: string; language: string }) {
  return (
    <Card className="w-80 overflow-hidden border-clay-200 bg-clay-50 dark:border-clay-700 dark:bg-clay-900/20">
      <CardBody className="flex flex-col gap-3">
        <span className="text-3xl" aria-hidden>🗣️</span>
        <p className="font-display text-lg font-semibold leading-snug text-clay-700 dark:text-clay-200">“{proverb}”</p>
        <p className="text-sm italic text-muted">{translation}</p>
        <Badge variant="clay">{language} proverb</Badge>
      </CardBody>
    </Card>
  )
}

/* ----------------------------------------------------------- word of the day */

export function WordOfDayCard({ word, meaning, pronunciation, language }: { word: string; meaning: string; pronunciation: string; language: string }) {
  return (
    <Card className="w-72">
      <CardBody className="flex flex-col gap-2">
        <div className="flex items-center justify-between">
          <Badge variant="primary">Word of the day</Badge>
          <span className="text-xs font-semibold text-muted">{language}</span>
        </div>
        <p className="font-display text-2xl font-bold text-foreground">{word}</p>
        <p className="text-sm text-muted">/{pronunciation}/ · {meaning}</p>
        <Button variant="soft" size="sm" leftIcon={<span>🔊</span>} className="mt-1 self-start">Hear it</Button>
      </CardBody>
    </Card>
  )
}

/* --------------------------------------------------------- native speaker card */

export function NativeSpeakerCard({ name, region, language }: { name: string; region: string; language: string }) {
  return (
    <Card className="w-64">
      <CardBody className="flex items-center gap-3">
        <div className="relative">
          <Avatar name={name} size="lg" />
          <span className="absolute -bottom-0.5 -right-0.5 grid size-5 place-items-center rounded-full bg-leaf-500 text-[10px] text-white ring-2 ring-surface">✓</span>
        </div>
        <div className="min-w-0">
          <p className="truncate font-display font-semibold text-foreground">{name}</p>
          <p className="truncate text-sm text-muted">{region}</p>
          <Badge variant="success" className="mt-1">Native {language} speaker</Badge>
        </div>
      </CardBody>
    </Card>
  )
}

/* ------------------------------------------------------ language selection card */

const LANG_COLOR: Record<string, string> = {
  Yorùbá: 'bg-rainbow-green', Igbo: 'bg-rainbow-blue', Hausa: 'bg-rainbow-orange', Pidgin: 'bg-rainbow-purple',
}

export function LanguageSelectionCard({ language, flag, learners, selected }: { language: string; flag: string; learners: string; selected?: boolean }) {
  return (
    <button
      className={cn(
        'flex w-44 flex-col items-center gap-2 rounded-2xl border-2 p-4 text-center transition-colors',
        selected ? 'border-primary bg-primary-soft' : 'border-border bg-surface hover:bg-surface-muted',
      )}
    >
      <span className={cn('grid size-14 place-items-center rounded-2xl text-3xl text-white', LANG_COLOR[language] ?? 'bg-rainbow-teal')}>{flag}</span>
      <p className="font-display text-lg font-bold text-foreground">{language}</p>
      <p className="text-xs text-muted">{learners} learners</p>
      {selected && <Badge variant="primary" dot>Selected</Badge>}
    </button>
  )
}

/* ----------------------------------------------------------- cultural video card */

export function CulturalVideoCard({ title, kind, duration }: { title: string; kind: string; duration: string }) {
  return (
    <Card className="w-72 overflow-hidden">
      <div className="relative grid h-32 place-items-center bg-gradient-to-br from-clay-400 to-clay-600">
        <span className="grid size-12 place-items-center rounded-full bg-white/90 text-clay-600 text-xl shadow-lg">▶</span>
        <span className="absolute bottom-2 right-2 rounded bg-charcoal-900/70 px-1.5 py-0.5 text-[11px] font-semibold text-white">{duration}</span>
      </div>
      <CardBody className="flex flex-col gap-1">
        <Badge variant="clay" className="self-start">{kind}</Badge>
        <h4 className="font-display font-semibold text-foreground">{title}</h4>
      </CardBody>
    </Card>
  )
}
