import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { describe, expect, it, vi } from 'vitest'
import { LearningGoalPage } from './LearningGoalPage'

const save = vi.hoisted(() => vi.fn())
const refresh = vi.hoisted(() => vi.fn())
vi.mock('@/lib/api', async load => {
  const actual = await load<typeof import('@/lib/api')>()
  return { ...actual, learningApi: { ...actual.learningApi, setLearningGoal: save } }
})
vi.mock('@/lib/auth/AuthProvider', () => ({
  useAuth: () => ({
    user: { learner_profiles: [{ id: 7, display_name: 'Ada', learning_goal: null, is_child: false }], families: [] },
    refresh,
  }),
}))
vi.mock('@/lib/config/useConfig', () => ({
  useConfig: () => ({ data: { learning_goal_question: 'Why are you learning?', learning_goals: [
    { key: 'grandparents', label: 'Grandparents', message: 'Family conversations' },
    { key: 'travel', label: 'Travel', message: 'Travel confidence' },
  ] }, isLoading: false }),
}))

describe('learning goal onboarding', () => {
  it('saves the chosen option on the learner profile and returns to learning', async () => {
    save.mockReset().mockResolvedValue({ learner_id: 7, learning_goal: 'travel' })
    refresh.mockReset().mockResolvedValue(undefined)
    render(<QueryClientProvider client={new QueryClient()}><MemoryRouter initialEntries={['/learning-goal']}><Routes>
      <Route path="/learning-goal" element={<LearningGoalPage />} />
      <Route path="/learn" element={<p>Learning page</p>} />
    </Routes></MemoryRouter></QueryClientProvider>)
    expect(screen.getByRole('heading', { name: 'Why are you learning?' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Back to learning' })).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Home' })).not.toBeInTheDocument()
    fireEvent.click(screen.getByRole('radio', { name: /Travel/ }))
    fireEvent.click(screen.getByRole('button', { name: 'Continue with my goal' }))
    await waitFor(() => expect(save).toHaveBeenCalledWith(7, 'travel'))
    expect(await screen.findByText('Learning page')).toBeInTheDocument()
  })
})
