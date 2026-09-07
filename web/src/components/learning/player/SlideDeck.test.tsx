import { fireEvent, render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { SlideDeck } from './SlideDeck'
import type { PlayerService, QuizSlide } from './types'

vi.mock('./slides', () => ({
  SlideView: ({ onGraded, onAdvance }: { onGraded: (correct: boolean, xp: number) => void; onAdvance: () => void }) => (
    <button onClick={() => { onGraded(true, 1); onAdvance() }}>Answer correctly</button>
  ),
}))

const questions: QuizSlide[] = [1, 2].map((id) => ({
  id: `q${id}`, componentId: 10, kind: 'quiz', questionId: id, qtype: 'mcq_single',
  prompt: 'Pick', promptAudio: null, promptImage: null, options: [], matchPool: [],
  passThreshold: 0.7, completed: id === 1, wasCorrect: id === 1,
}))

function mount() {
  render(<SlideDeck title="Quiz" slides={questions} service={{} as PlayerService} initialHearts={5}
    startIndex={1} initialCorrect={1} onExit={() => {}} renderComplete={() => <p>Lesson finished</p>} />)
}

describe('quiz summaries', () => {
  it('counts resumed answers once', () => {
    mount()
    fireEvent.click(screen.getByRole('button', { name: /resume/i }))
    fireEvent.click(screen.getByText('Answer correctly'))
    expect(screen.getByText('2 out of 2 correct')).toBeInTheDocument()
    expect(screen.getByText(/100% · 2 answered/)).toBeInTheDocument()
  })

  it('does not add old answers when starting over', () => {
    mount()
    fireEvent.click(screen.getByRole('button', { name: /start over/i }))
    fireEvent.click(screen.getByText('Answer correctly'))
    fireEvent.click(screen.getByText('Answer correctly'))
    expect(screen.getByText('2 out of 2 correct')).toBeInTheDocument()
    expect(screen.getByText(/100% · 2 answered/)).toBeInTheDocument()
  })
})
