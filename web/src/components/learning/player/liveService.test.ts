import { beforeEach, describe, expect, it, vi } from 'vitest'
import { learningApi } from '@/lib/api'
import { createLiveService, type QuizSlide } from './types'

vi.mock('@/lib/api', () => ({ learningApi: { answer: vi.fn() } }))

const question = (componentId = 10, questionId = 1): QuizSlide => ({
  id: `q${componentId}-${questionId}`, componentId, questionId, kind: 'quiz', qtype: 'mcq_single',
  completed: false, wasCorrect: false, prompt: 'Pick', promptAudio: null, promptImage: null,
  options: [], matchPool: [], passThreshold: 0.7,
})
const result = { correct: true, correct_answer: { option_ids: [1] }, explanation: null, xp_awarded: 1,
  hearts_remaining: 5, unlimited_hearts: false, practice_mode: false, competitive_paused_until: null }

describe('live answer request identity', () => {
  beforeEach(() => vi.mocked(learningApi.answer).mockReset().mockResolvedValue(result))

  it('reuses the identity after a lost reply and after a repeated submission', async () => {
    const service = createLiveService(2, 3)
    vi.mocked(learningApi.answer).mockRejectedValueOnce(new Error('Lost reply'))
    await expect(service.gradeQuiz(question(), { optionId: 1 })).rejects.toThrow('Lost reply')
    await service.gradeQuiz(question(), { optionId: 1 })
    await service.gradeQuiz(question(), { optionId: 1 })
    const calls = vi.mocked(learningApi.answer).mock.calls.map(([input]) => input)
    expect(calls[0].requestId).toMatch(/^[a-f0-9-]{36}$/)
    expect(new Set(calls.map((input) => input.requestId)).size).toBe(1)
    expect(calls[0]).toMatchObject({ componentId: 10, learnerId: 3, questionId: 1, answer: { option_id: 1 } })
  })

  it('renews only the deliberate retry component and distinguishes corrected answers', async () => {
    const service = createLiveService(2, 3)
    await service.gradeQuiz(question(), { optionId: 1 })
    await service.gradeQuiz(question(20, 2), { optionId: 1 })
    service.retryQuiz?.(10)
    await service.gradeQuiz(question(), { optionId: 1 })
    await service.gradeQuiz(question(20, 2), { optionId: 1 })
    await service.gradeQuiz(question(), { optionId: 2 })
    const ids = vi.mocked(learningApi.answer).mock.calls.map(([input]) => input.requestId)
    expect(ids[2]).not.toBe(ids[0])
    expect(ids[3]).toBe(ids[1])
    expect(ids[4]).not.toBe(ids[2])
  })
})
