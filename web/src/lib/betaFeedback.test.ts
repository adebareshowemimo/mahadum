import { describe, expect, it } from 'vitest'
import { planFeatures } from '@/lib/billing/planFeatures'
import { formatDayStreak } from '@/lib/gamification/format'
import { shuffleWordOptions } from '@/components/learning/player/slides'
import { parseFlashcardCsv } from '@/pages/content/LessonBuilderPage'
import { LANDING_LANGUAGES } from '@/components/landing/languages'

describe('beta feedback regression helpers', () => {
  it('keeps plan inheritance copy consistent and hides unavailable offline downloads', () => {
    expect(planFeatures({
      audience: 'family',
      max_profiles: 5,
      features: { offline_download: false, unlimited_hearts: true },
    })).toEqual([
      'All Individual plan benefits',
      'Up to 5 profiles',
      'Supported by age-appropriate ads',
      'Unlimited hearts',
    ])
  })

  it('uses explicit singular and plural streak wording', () => {
    expect(formatDayStreak(1)).toBe('1 Day Streak')
    expect(formatDayStreak(4)).toBe('4 Day Streaks')
  })

  it('reshuffles word-bank tiles without changing the answer-key array', () => {
    const answer = ['Ẹ', 'káàrọ̀', 'sir']
    const shuffled = shuffleWordOptions(answer, () => 0.999)

    expect(shuffled).not.toEqual(answer)
    expect(shuffled).toEqual(['káàrọ̀', 'Ẹ', 'sir'])
    expect(answer).toEqual(['Ẹ', 'káàrọ̀', 'sir'])
  })

  it('imports quoted UTF-8 flashcard CSV and rejects incomplete rows', () => {
    expect(parseFlashcardCsv('\uFEFFFront,Back\n"Ẹ káàrọ̀, sir","Good morning, sir"\n')).toEqual([
      expect.objectContaining({ front_text: 'Ẹ káàrọ̀, sir', back_text: 'Good morning, sir' }),
    ])
    expect(() => parseFlashcardCsv('Front,Back\nHello,\n')).toThrow('Row 2 needs both Front (Word) and Back (Meaning).')
  })

  it('uses natural English quiz questions without changing translation prompts', () => {
    expect(LANDING_LANGUAGES.find(({ code }) => code === 'en')?.quiz.map(({ question }) => question)).toEqual([
      'Which phrase is a greeting in the morning?',
      'Which word is politely added to a request?',
      "Which phrase is a polite answer to 'How are you?'",
    ])
    expect(LANDING_LANGUAGES.find(({ code }) => code === 'yo')?.quiz.every(({ question }) => question === undefined)).toBe(true)
  })
})
