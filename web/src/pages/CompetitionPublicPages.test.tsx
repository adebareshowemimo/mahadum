import { render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { describe, expect, it, vi } from 'vitest'
import { CompetitionDetailPage } from './CompetitionDetailPage'
import { CompetitionsPage } from './CompetitionsPage'

const competition = {
  id: 1, slug: 'language-culture-2026', title: 'Language & Culture 2026', season: 2026,
  status: 'open', entries_count: 1, entries_open_at: '2026-11-01T00:00:00Z',
  submissions_close_at: '2026-11-20T00:00:00Z', voting_closes_at: '2026-12-01T00:00:00Z',
  judging_starts_at: '2026-12-02T00:00:00Z', judging_closes_at: '2026-12-05T00:00:00Z',
  min_activity_days: 90, accepting_entries: true, accepting_votes: true,
  description: 'Celebrate our languages.', rules: 'One entry per school.',
  prizes: 'Books for winners.', eligibility: 'Schools and diaspora families.',
  can_enter: false, voted_categories: [],
  entries: [{ id: 4, category: 'school_play', title: 'The Talking Drum', synopsis: null,
    status: 'approved', votes_count: 2, award_rank: null, entrant: 'Greenfield', language: 'Yoruba' }],
} as const

vi.mock('@/lib/competition/queries', () => ({
  useCompetitions: () => ({ data: [competition], isLoading: false, isError: false }),
  useCompetition: () => ({ data: competition, isLoading: false, isError: false }),
  useVote: () => ({ mutateAsync: vi.fn(), isPending: false }),
  useSubmitEntry: () => ({ mutateAsync: vi.fn(), isPending: false }),
  useMyEntries: () => ({ data: [] }),
  useApproveEntryAsGuardian: () => ({ mutateAsync: vi.fn(), isPending: false }),
}))
vi.mock('@/lib/auth/AuthProvider', () => ({
  useAuth: () => ({ status: 'unauthenticated', user: null }),
}))

describe('public competition pages', () => {
  it('links a guest to the stable competition URL', () => {
    render(<MemoryRouter initialEntries={['/competitions']}><CompetitionsPage /></MemoryRouter>)
    expect(screen.getByRole('link', { name: /Language & Culture 2026/ })).toHaveAttribute('href', '/competitions/language-culture-2026')
  })

  it('shows rules, prizes, eligibility and dates without offering guest entry or voting', () => {
    render(<MemoryRouter initialEntries={['/competitions/language-culture-2026']}><Routes><Route path="/competitions/:competitionId" element={<CompetitionDetailPage />} /></Routes></MemoryRouter>)
    expect(screen.getByText('One entry per school.')).toBeInTheDocument()
    expect(screen.getByText('Books for winners.')).toBeInTheDocument()
    expect(screen.getByText('Schools and diaspora families.')).toBeInTheDocument()
    expect(screen.getByText(/Entries close:/)).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Sign in to vote' })).toHaveAttribute('href', '/login')
    expect(screen.queryByRole('button', { name: 'Submit an entry' })).not.toBeInTheDocument()
  })
})
