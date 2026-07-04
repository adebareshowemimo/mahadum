import { describe, expect, it } from 'vitest'
import { authorToSlides, createPreviewService, playToSlides, type Slide } from './index'
import type { AuthorLesson, LessonPlay } from '@/lib/api'

const videoSlide = (slides: Slide[]) => slides.find((s) => s.kind === 'video')!
const quizSlides = (slides: Slide[]) => slides.filter((s): s is Extract<Slide, { kind: 'quiz' }> => s.kind === 'quiz')

describe('playToSlides — video gate & resume', () => {
  const play: LessonPlay = {
    lesson: { id: 1, title: 'L', est_minutes: 5 },
    components: [
      {
        id: 10,
        type: 'video',
        position: 1,
        xp: 5,
        require_watch: true,
        resume_position: 42,
        completed: false,
        video: { duration: 120, quality: '720p', src: 'x.mp4', hls: null, poster: null, captions: [] },
      },
    ],
  }

  it('carries require_watch, resume position and completion onto the slide', () => {
    const v = videoSlide(playToSlides(play))
    expect(v.requireWatch).toBe(true)
    expect(v.resumeAt).toBe(42)
    expect(v.alreadyCompleted).toBe(false)
    expect(v.src).toBe('x.mp4')
  })

  it('defaults gate/resume fields when the server omits them', () => {
    const v = videoSlide(
      playToSlides({ ...play, components: [{ id: 11, type: 'video', position: 1, xp: 5, video: null }] }),
    )
    expect(v.requireWatch).toBe(false)
    expect(v.resumeAt).toBe(0)
    expect(v.alreadyCompleted).toBe(false)
  })
})

describe('authorToSlides — preview has no resume context', () => {
  const lesson: AuthorLesson = {
    id: 1,
    title: 'L',
    position: 1,
    est_minutes: 5,
    is_locked_by_default: false,
    published_at: null,
    is_published: false,
    components: [
      {
        id: 20,
        type: 'video',
        position: 1,
        title: null,
        is_required: true,
        xp_value: 5,
        settings: { require_watch: true },
        detail: { title: 'V', src: null },
      },
    ],
  }

  it('reads the gate from settings but never resumes (no learner)', () => {
    const v = videoSlide(authorToSlides(lesson).slides)
    expect(v.requireWatch).toBe(true)
    expect(v.resumeAt).toBe(0)
    expect(v.alreadyCompleted).toBe(false)
  })
})

describe('playToSlides — quiz question types', () => {
  const play: LessonPlay = {
    lesson: { id: 1, title: 'L', est_minutes: 5 },
    components: [
      {
        id: 30,
        type: 'quiz',
        position: 1,
        xp: 10,
        quiz: {
          pass_threshold: 0.6,
          hearts_enabled: true,
          questions: [
            { id: 1, type: 'listen_and_respond', prompt: 'Reply', prompt_audio: 'a.mp3', options: [{ id: 5, label: 'Yes' }] },
            { id: 2, type: 'match_pairs', prompt: 'Match', options: [{ id: 6, label: 'Mama' }], match_pool: ['Mother', 'Father'] },
          ],
        },
      },
    ],
  }

  it('carries qtype, prompt audio and the match pool onto quiz slides', () => {
    const [listen, match] = quizSlides(playToSlides(play))
    expect(listen.qtype).toBe('listen_and_respond')
    expect(listen.promptAudio).toBe('a.mp3')
    expect(match.qtype).toBe('match_pairs')
    expect(match.matchPool).toEqual(['Mother', 'Father'])
  })
})

describe('assignment slides', () => {
  it('maps the play payload to an assignment slide with its coin reward', () => {
    const play: LessonPlay = {
      lesson: { id: 1, title: 'L', est_minutes: 5 },
      components: [
        {
          id: 50,
          type: 'assignment',
          position: 1,
          xp: 6,
          assignment: { prompt: 'Greet a grandparent', expected_media: 'video', max_duration_seconds: 60, coin_reward: 40 },
        },
      ],
    }
    const slide = playToSlides(play).find((s) => s.kind === 'assignment')
    expect(slide).toMatchObject({ kind: 'assignment', prompt: 'Greet a grandparent', expectedMedia: 'video', maxDuration: 60, coinReward: 40 })
  })
})

describe('exercise & game slides', () => {
  it('maps flashcard and memory-game payloads onto slides', () => {
    const play: LessonPlay = {
      lesson: { id: 1, title: 'L', est_minutes: 5 },
      components: [
        {
          id: 60,
          type: 'exercise',
          position: 1,
          xp: 4,
          exercise: { mode: 'flashcards', cards: [{ id: 1, front: 'Nna', back: 'Father', mnemonic: null, audio: 'n.mp3', image: null }] },
        },
        {
          id: 61,
          type: 'game',
          position: 2,
          xp: 4,
          game: { game_type: 'memory', pairs: [{ a: 'Nna', b: 'Father' }, { a: 'Nne', b: 'Mother' }] },
        },
      ],
    }
    const slides = playToSlides(play)
    const exercise = slides.find((s) => s.kind === 'exercise')
    const game = slides.find((s) => s.kind === 'game')
    expect(exercise).toMatchObject({ kind: 'exercise', cards: [{ front: 'Nna', back: 'Father', audio: 'n.mp3' }] })
    expect(game).toMatchObject({ kind: 'game', gameType: 'memory' })
    expect(game && game.kind === 'game' && game.pairs).toHaveLength(2)
  })
})

describe('createPreviewService — local grading by type', () => {
  const lesson: AuthorLesson = {
    id: 1,
    title: 'L',
    position: 1,
    est_minutes: 5,
    is_locked_by_default: false,
    published_at: null,
    is_published: false,
    components: [
      {
        id: 40,
        type: 'quiz',
        position: 1,
        title: null,
        is_required: true,
        xp_value: 10,
        settings: {},
        detail: {
          questions: [
            { id: 10, type: 'word_bank', prompt: 'Order', options: [{ id: 1, label: 'A', is_correct: false }, { id: 2, label: 'B', is_correct: false }] },
            { id: 11, type: 'match_pairs', prompt: 'Match', options: [{ id: 3, label: 'Mama', is_correct: false, match_target: 'Mother' }] },
            { id: 12, type: 'mcq_multi', prompt: 'Pick', options: [{ id: 4, label: 'X', is_correct: true }, { id: 5, label: 'Y', is_correct: true }, { id: 6, label: 'Z', is_correct: false }] },
          ],
        },
      },
    ],
  }
  const { slides, key } = authorToSlides(lesson)
  const wordBank = quizSlides(slides).find((s) => s.questionId === 10)!
  const match = quizSlides(slides).find((s) => s.questionId === 11)!
  const multi = quizSlides(slides).find((s) => s.questionId === 12)!

  it('word_bank is order-sensitive', async () => {
    const svc = createPreviewService(key, { value: 5 })
    expect((await svc.gradeQuiz(wordBank, { optionIds: [1, 2] })).correct).toBe(true)
    expect((await svc.gradeQuiz(wordBank, { optionIds: [2, 1] })).correct).toBe(false)
  })

  it('match_pairs checks each pairing', async () => {
    const svc = createPreviewService(key, { value: 5 })
    expect((await svc.gradeQuiz(match, { pairs: [{ option_id: 3, match_target: 'Mother' }] })).correct).toBe(true)
    expect((await svc.gradeQuiz(match, { pairs: [{ option_id: 3, match_target: 'Father' }] })).correct).toBe(false)
  })

  it('mcq_multi needs the exact set (any order)', async () => {
    const svc = createPreviewService(key, { value: 5 })
    expect((await svc.gradeQuiz(multi, { optionIds: [5, 4] })).correct).toBe(true)
    expect((await svc.gradeQuiz(multi, { optionIds: [4] })).correct).toBe(false)
    expect((await svc.gradeQuiz(multi, { optionIds: [4, 5, 6] })).correct).toBe(false)
  })
})
