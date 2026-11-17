import { fireEvent, render, screen, within } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { SlideDeck } from './SlideDeck'
import type { LessonPlay } from '@/lib/api'
import { playToSlides, type PlayerService, type QuizSlide, type Slide } from './types'

const grading = vi.hoisted(() => ({ correct: true }))

beforeEach(() => { grading.correct = true })

vi.mock('./slides', () => ({
  SlideView: ({ slide, onGraded, onAdvance }: { slide: Slide; onGraded: (correct: boolean, xp: number) => void; onAdvance: () => void }) => (
    <button onClick={() => {
      if (slide.kind === 'quiz') onGraded(grading.correct, grading.correct ? 1 : 0)
      onAdvance()
    }}>{slide.kind === 'quiz' ? 'Answer correctly' : `Next from video ${slide.componentId}`}</button>
  ),
}))

const questions: QuizSlide[] = [1, 2].map((id) => ({
  id: `q${id}`, componentId: 10, kind: 'quiz', questionId: id, qtype: 'mcq_single',
  prompt: 'Pick', promptAudio: null, promptImage: null, options: [], matchPool: [],
  passThreshold: 0.7, completed: id === 1, wasCorrect: id === 1,
}))

function mount(service = {} as PlayerService) {
  render(<SlideDeck title="Quiz" slides={questions} service={service} initialHearts={5}
    startIndex={1} initialCorrect={1} onExit={() => {}} renderComplete={() => <p>Lesson finished</p>} />)
}

it('lists and plays a video, two distinct quizzes, and a practice video in authored order', () => {
  const quiz = (id: number, position: number): LessonPlay['components'][number] => ({
    id, position, type: 'quiz', xp: 1,
    quiz: {
      pass_threshold: 0.6, hearts_enabled: true,
      questions: [{ id, type: 'mcq_single', prompt: 'Choose', options: [] }],
    },
  })
  const play: LessonPlay = {
    lesson: { id: 51, title: 'Introduce yourself', est_minutes: 5 },
    components: [
      { id: 10, type: 'video', position: 1, xp: 5, video: null },
      quiz(20, 2),
      quiz(30, 3),
      { id: 40, type: 'video', position: 4, xp: 5, video: null },
    ],
  }
  render(<SlideDeck title={play.lesson.title} slides={playToSlides(play)} service={{} as PlayerService} initialHearts={null}
    onExit={() => {}} renderComplete={() => <p>Lesson finished</p>} />)

  const checklist = within(screen.getByRole('list')).getAllByRole('listitem')
  expect(checklist).toHaveLength(4)
  expect(checklist[0]).toHaveTextContent('Video')
  expect(checklist[1]).toHaveTextContent('Quiz')
  expect(checklist[2]).toHaveTextContent('Quiz')
  expect(checklist[3]).toHaveTextContent('Video')

  const click = (name: string) => fireEvent.click(screen.getByRole('button', { name }))
  click('Start')
  click('Next from video 10')
  click('Answer correctly')
  click('Continue to next activity')
  click('Answer correctly')
  click('Continue to next activity')
  click('Next from video 40')
  expect(screen.getByText('Lesson finished')).toBeInTheDocument()
})

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

describe('deliberate retry identity resets', () => {
  it('keeps resumed requests but renews quiz requests when Retry is chosen', () => {
    const retryQuiz = vi.fn()
    mount({ retryQuiz } as unknown as PlayerService)
    fireEvent.click(screen.getByRole('button', { name: /resume/i }))
    expect(retryQuiz).not.toHaveBeenCalled()
    grading.correct = false
    fireEvent.click(screen.getByText('Answer correctly'))
    fireEvent.click(screen.getByRole('button', { name: /^retry quiz$/i }))
    expect(retryQuiz).toHaveBeenCalledTimes(1)
    expect(retryQuiz).toHaveBeenCalledWith(10)
    expect(screen.getByText('Answer correctly')).toBeInTheDocument()
  })

  it('renews each quiz once when starting over', () => {
    const retryQuiz = vi.fn()
    mount({ retryQuiz } as unknown as PlayerService)
    fireEvent.click(screen.getByRole('button', { name: /start over/i }))
    expect(retryQuiz).toHaveBeenCalledTimes(1)
    expect(retryQuiz).toHaveBeenCalledWith(10)
  })
})
