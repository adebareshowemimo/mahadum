import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { StoryQuiz } from './StoryQuiz'

describe('StoryQuiz', () => {
  it('checks an answer, shows feedback, and reaches the locked ending', async () => {
    const onAnswer = vi.fn(async () => ({ correct: false, correct_index: 0, explanation: 'The first answer is right.' }))
    render(<StoryQuiz title="Greeting quiz" questions={[{ id: 1, position: 1, prompt: 'Which greeting?', options: ['Ndewo', 'Mba'] }]} onAnswer={onAnswer} endContent={<p>Unlock remaining questions</p>} onExit={vi.fn()} />)
    fireEvent.click(screen.getByRole('button', { name: 'Start quiz' }))
    expect(screen.getByText('Which greeting?')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: /Mba/ }))
    fireEvent.click(screen.getByRole('button', { name: 'Check' }))
    await waitFor(() => expect(onAnswer).toHaveBeenCalledWith(expect.objectContaining({ id: 1 }), 1))
    expect(screen.getByText('Answer: Ndewo')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Finish' }))
    fireEvent.click(screen.getByRole('button', { name: 'Finish lesson' }))
    expect(screen.getByText('Unlock remaining questions')).toBeInTheDocument()
  })
})
