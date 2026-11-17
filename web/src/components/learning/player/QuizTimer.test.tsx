import { act, render, screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { SlideView } from './slides'
import type { PlayerService, QuizSlide } from './types'

afterEach(() => vi.useRealTimers())

describe('timed quiz question', () => {
  it('records a missed answer once when the configured time expires', async () => {
    vi.useFakeTimers()
    const slide: QuizSlide = {
      id: 'q1', componentId: 5, kind: 'quiz', questionId: 1, qtype: 'mcq_single',
      prompt: 'Choose a greeting', promptAudio: null, promptImage: null,
      options: [{ id: 1, label: 'Ndewo' }], matchPool: [], passThreshold: 0.6,
      timeLimitSeconds: 5, completed: false, wasCorrect: false,
    }
    const gradeQuiz = vi.fn().mockResolvedValue({ correct: false, correctOptionIds: [1], correctText: null,
      correctPairs: [], explanation: null, xpAwarded: 0, heartsRemaining: 4,
      attemptsExhausted: false, practiceMode: false, competitivePausedUntil: null })
    render(<SlideView slide={slide} service={{ gradeQuiz } as unknown as PlayerService} isLast={false}
      onAdvance={vi.fn()} onGraded={vi.fn()} onHearts={vi.fn()} onPracticeMode={vi.fn()} />)

    expect(screen.getByRole('timer')).toHaveTextContent('5s remaining')
    await act(async () => { await vi.advanceTimersByTimeAsync(5500) })
    await act(async () => {})
    expect(gradeQuiz).toHaveBeenCalledOnce()
    expect(gradeQuiz).toHaveBeenCalledWith(slide, { optionId: null })
    expect(screen.getByText('Not quite')).toBeInTheDocument()
  })
})
