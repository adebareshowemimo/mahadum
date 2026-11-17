import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { describe, expect, it, vi } from 'vitest'
import { PlacementPage } from './PlacementPage'

const mocks = vi.hoisted(() => ({ questions: vi.fn(), submit: vi.fn(), enroll: vi.fn() }))
vi.mock('@/lib/api', async (load) => {
  const actual = await load<typeof import('@/lib/api')>()
  return { ...actual, learningApi: { ...actual.learningApi,
    placementQuestions: mocks.questions, submitPlacement: mocks.submit, enroll: mocks.enroll } }
})
vi.mock('@/lib/profile/ActiveProfile', () => ({ useActiveProfile: () => ({ activeLearner: { id: 7, display_name: 'Ada' } }) }))

function mount() {
  render(<QueryClientProvider client={new QueryClient()}><MemoryRouter initialEntries={['/learn/placement?course=3&language=2']}><Routes>
    <Route path="/learn/placement" element={<PlacementPage />} />
    <Route path="/learn" element={<p>Learning page</p>} />
  </Routes></MemoryRouter></QueryClientProvider>)
}

describe('placement', () => {
  it('submits four answers before enrolling', async () => {
    mocks.questions.mockReset().mockResolvedValue({ available: true, result_level: null, questions: [1, 2, 3, 4].map((id) => ({
      id, prompt: `Prompt ${id}`, prompt_audio: id < 3 ? '/audio.mp3' : null,
      options: [{ id: id * 10, label: 'Yes' }, { id: id * 10 + 1, label: 'No' }],
    })) })
    mocks.submit.mockReset().mockResolvedValue({ result_level: 'Intermediate', score: 1, starting_level_position: 2 })
    mocks.enroll.mockReset().mockResolvedValue({ enrollment_id: 9 })
    mount()
    expect(await screen.findByRole('heading', { name: 'A short placement check' })).toBeInTheDocument()
    for (const id of [1, 2, 3, 4]) fireEvent.click(screen.getAllByRole('radio', { name: 'Yes' })[id - 1])
    fireEvent.click(screen.getByRole('button', { name: 'See my starting point' }))
    await waitFor(() => expect(mocks.submit).toHaveBeenCalledWith(7, 2, [1, 2, 3, 4].map((id) => ({ question_id: id, option_id: id * 10 }))))
    await waitFor(() => expect(mocks.enroll).toHaveBeenCalledWith(7, 3))
    expect(await screen.findByText('Learning page')).toBeInTheDocument()
  })
})
