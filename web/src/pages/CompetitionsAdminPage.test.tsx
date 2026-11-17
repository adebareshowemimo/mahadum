import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { describe, expect, it, vi } from 'vitest'
import { CompetitionsAdminPage } from './CompetitionsAdminPage'

const competition = {
  id: 7, title: 'Language & Culture 2026', slug: 'language-culture-2026', season: 2026,
  description: 'A cultural celebration.', rules: 'One entry per school.',
  prizes: 'Books.', eligibility: 'Schools and families.', status: 'draft', statuses: ['draft', 'open', 'voting', 'closed'],
  entries_open_at: null, submissions_close_at: null, voting_closes_at: null,
  judging_starts_at: null, judging_closes_at: null, min_activity_days: 90, entries_count: 0,
}
vi.mock('@/lib/competition/queries', () => ({
  useAdminCompetitions: () => ({ data: [competition], isLoading: false, isError: false }),
  useCreateCompetition: () => ({ mutateAsync: vi.fn(), isPending: false }),
  useUpdateCompetition: () => ({ mutateAsync: vi.fn(), isPending: false }),
  useSetCompetitionStatus: () => ({ mutateAsync: vi.fn(), isPending: false }),
  useAdminCompetition: () => ({ data: null, isLoading: false, isError: false }),
  useJudgeCompetition: () => ({ mutateAsync: vi.fn(), isPending: false }),
  useModerateEntry: () => ({ mutateAsync: vi.fn(), isPending: false }),
}))

describe('competition organiser page', () => {
  it('offers public details and a copyable stable link', async () => {
    const writeText = vi.fn().mockResolvedValue(undefined)
    const user = userEvent.setup()
    Object.defineProperty(navigator, 'clipboard', { configurable: true, value: { writeText } })
    render(<MemoryRouter><CompetitionsAdminPage /></MemoryRouter>)
    expect(screen.getByRole('link', { name: 'Public page' })).toHaveAttribute('href', '/competitions/language-culture-2026')
    await user.click(screen.getByRole('button', { name: 'Copy link' }))
    expect(writeText).toHaveBeenCalledWith(`${window.location.origin}/competitions/language-culture-2026`)
    await user.click(screen.getByRole('button', { name: 'Edit details' }))
    expect(screen.getByRole('textbox', { name: 'Rules' })).toHaveValue('One entry per school.')
    expect(screen.getByRole('textbox', { name: 'Prizes' })).toHaveValue('Books.')
    expect(screen.getByLabelText('Judging starts')).toBeInTheDocument()
  })
})
