import type { StoryAgeBand, StoryFormat, StorySummary } from '@/lib/api'

export const STORY_BANDS: Array<{ value: StoryAgeBand; label: string; short: string; description: string }> = [
  { value: '0_5', label: '0–5 · Early childhood', short: 'Ages 0–5', description: 'Colouring and tracing books' },
  { value: '6_12', label: '6–12 · Middle childhood', short: 'Ages 6–12', description: 'Animated stories' },
  { value: '13_17', label: '13–17 · Teen', short: 'Ages 13–17', description: 'Literature and reading books' },
  { value: '18_plus', label: '18+ · Adults', short: 'Adults 18+', description: 'Reading books for adults' },
  { value: 'folklore', label: 'Folklore · All ages', short: 'Folklore', description: 'Folktales in every format' },
]

export function formatsForStoryBand(band: StoryAgeBand): StoryFormat[] {
  if (band === '0_5') return ['colouring', 'tracing']
  if (band === '6_12') return ['animated']
  if (band === 'folklore') return ['colouring', 'tracing', 'animated', 'reading']
  return ['reading']
}

export function storyLength(story: Pick<StorySummary, 'format' | 'pages_count'>): string {
  if (story.format === 'animated') return 'Animated story'
  const count = story.pages_count
  return `${count} ${story.format === 'reading' ? (count === 1 ? 'chapter' : 'chapters') : (count === 1 ? 'page' : 'pages')}`
}

export function storyBand(story: Pick<StorySummary, 'age_band'>) {
  return STORY_BANDS.find((band) => band.value === story.age_band)!
}
