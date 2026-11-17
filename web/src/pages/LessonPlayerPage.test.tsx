import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { StrictMode } from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { learningApi } from '@/lib/api'
import { gamificationKeys } from '@/lib/gamification/queries'
import { learningKeys } from '@/lib/learning/queries'
import { LessonPlayerPage } from './LessonPlayerPage'

vi.mock('@/lib/profile/ActiveProfile', () => {
  const activeLearner = { id: 1, display_name: 'Ada' }
  return { useActiveProfile: () => ({ activeLearner }) }
})
vi.mock('@/lib/billing/entitlements', () => ({ useEntitlements: () => ({ ads: false }) }))
vi.mock('@/components/learning/player', () => ({
  SlideDeck: ({ renderComplete }: { renderComplete: () => React.ReactNode }) => renderComplete(),
  playToSlides: () => [],
  resumePlan: () => ({ startIndex: 0, priorCorrect: 0 }),
  createLiveService: () => ({}),
}))
afterEach(() => vi.restoreAllMocks())

describe('Lesson completion achievements', () => {
  it('completes a text-only proverb lesson after the learner finishes reading', async () => {
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    vi.spyOn(learningApi, 'play').mockResolvedValue({ lesson: { id: 20, title: 'A saying', summary: 'Its meaning', est_minutes: 2 }, components: [], hearts_remaining: 5 } as never)
    vi.spyOn(learningApi, 'complete').mockResolvedValue({
      lesson_score: 1, xp_total: 0, streak: { count: 1, state: 'active' }, badges_unlocked: [], next_node: null,
    } as never)
    render(<QueryClientProvider client={client}><MemoryRouter initialEntries={['/learn/lessons/20?return=%2Flearn%2Fproverbs-trivia%2F3']}>
      <Routes><Route path="/learn/lessons/:lessonId" element={<LessonPlayerPage />} /></Routes>
    </MemoryRouter></QueryClientProvider>)
    expect(await screen.findByText('Its meaning')).toBeInTheDocument()
    expect(learningApi.complete).not.toHaveBeenCalled()
    await userEvent.click(screen.getByRole('button', { name: 'Finish reading' }))
    expect(await screen.findByRole('heading', { name: 'Lesson complete!' })).toBeInTheDocument()
    expect(learningApi.complete).toHaveBeenCalledWith(20, 1)
  })

  it('F8: refreshes the learner’s cached badges and progress after a successful completion', async () => {
    const client = new QueryClient({ defaultOptions: { queries: { retry: false, staleTime: 60_000 } } })
    const keys = [learningKeys.path(1), gamificationKeys.badges(1), gamificationKeys.streak(1), gamificationKeys.hearts(1), gamificationKeys.league(1)]
    for (const key of [...keys, gamificationKeys.badges(2)]) client.setQueryData(key, {})
    vi.spyOn(learningApi, 'play').mockResolvedValue({ lesson: { id: 10, title: 'Level 0' }, components: [] } as never)
    vi.spyOn(learningApi, 'complete').mockResolvedValue({
      lesson_score: 1, xp_total: 14, streak: { count: 1, state: 'active' },
      badges_unlocked: [{ name: 'First Steps', id: 1 }], next_node: null,
    })
    render(<StrictMode><QueryClientProvider client={client}><MemoryRouter initialEntries={['/lessons/10/play']}>
      <Routes><Route path="/lessons/:lessonId/play" element={<LessonPlayerPage />} /></Routes>
    </MemoryRouter></QueryClientProvider></StrictMode>)

    expect(await screen.findByRole('heading', { name: 'Lesson complete!' })).toBeInTheDocument()
    expect(screen.getByText('🏅 1 new badge!')).toBeInTheDocument()
    expect(screen.getByText('First Steps')).toBeInTheDocument()
    await waitFor(() => {
      for (const key of keys) expect(client.getQueryState(key)?.isInvalidated).toBe(true)
    })
    expect(client.getQueryState(gamificationKeys.badges(2))?.isInvalidated).toBe(false)
    expect(learningApi.complete).toHaveBeenCalledWith(10, 1)
    expect(learningApi.complete).toHaveBeenCalledTimes(1)
  })
})
