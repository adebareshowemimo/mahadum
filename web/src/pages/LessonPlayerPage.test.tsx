import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen, waitFor } from '@testing-library/react'
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
  it('F8: refreshes the learner’s cached badges and progress after a successful completion', async () => {
    const client = new QueryClient({ defaultOptions: { queries: { retry: false, staleTime: 60_000 } } })
    const keys = [learningKeys.path(1), gamificationKeys.badges(1), gamificationKeys.streak(1), gamificationKeys.hearts(1), gamificationKeys.league(1)]
    for (const key of [...keys, gamificationKeys.badges(2)]) client.setQueryData(key, {})
    vi.spyOn(learningApi, 'play').mockResolvedValue({ lesson: { id: 10, title: 'Level 0' } } as never)
    vi.spyOn(learningApi, 'complete').mockResolvedValue({
      lesson_score: 1, xp_total: 14, streak: { count: 1, state: 'active' },
      badges_unlocked: [{ name: 'Star Starter', id: 1 }], next_node: null,
    })
    render(<QueryClientProvider client={client}><MemoryRouter initialEntries={['/lessons/10/play']}>
      <Routes><Route path="/lessons/:lessonId/play" element={<LessonPlayerPage />} /></Routes>
    </MemoryRouter></QueryClientProvider>)

    expect(await screen.findByRole('heading', { name: 'Lesson complete!' })).toBeInTheDocument()
    expect(screen.getByText('🏅 1 new badge!')).toBeInTheDocument()
    await waitFor(() => {
      for (const key of keys) expect(client.getQueryState(key)?.isInvalidated).toBe(true)
    })
    expect(client.getQueryState(gamificationKeys.badges(2))?.isInvalidated).toBe(false)
    expect(learningApi.complete).toHaveBeenCalledWith(10, 1)
  })
})
