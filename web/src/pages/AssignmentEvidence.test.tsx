import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { schoolApi, type ClassAssignmentDetail } from '@/lib/api'
import { AssignmentDetailContent } from './AssignmentsPage'

vi.mock('@/lib/auth/AuthProvider', () => ({ useAuth: () => ({ hasRole: () => true }) }))
afterEach(() => vi.restoreAllMocks())

function show(mediaType: string | null, canGrade = true) {
  const data: ClassAssignmentDetail = {
    id: 3, title: 'Greetings', instructions: null, due_at: null, coin_reward: 50, can_grade: canGrade,
    roster: [{ learner_id: 21, display_name: 'Ada', submission_id: 4, status: 'submitted', passed: null,
      score: null, feedback: null, submitted_at: null, graded_at: null, text_body: 'E kaaro',
      media_url: '/storage/assignments/existing-evidence', media_type: mediaType }],
  }
  vi.spyOn(schoolApi, 'classAssignmentDetail').mockResolvedValue(data)
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(<QueryClientProvider client={client}><MemoryRouter><AssignmentDetailContent classId={1} assignmentId={3} /></MemoryRouter></QueryClientProvider>)
}

describe('teacher assignment evidence', () => {
  it.each(['audio', 'video'])('displays uploaded %s alongside the answer before grading', async (type) => {
    show(type)
    const player = await screen.findByLabelText(`Submitted ${type} by Ada`)
    expect(player.tagName.toLowerCase()).toBe(type)
    expect(player).toHaveAttribute('src', '/storage/assignments/existing-evidence')
    expect(player).toHaveAttribute('controls')
    expect(player).not.toHaveAttribute('autoplay')
    expect(screen.getByText('E kaaro')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Grade' })).toBeInTheDocument()
  })

  it('provides an open link for legacy evidence with an unknown type', async () => {
    show(null)
    expect(await screen.findByRole('link', { name: 'Open submitted work' })).toHaveAttribute('href', '/storage/assignments/existing-evidence')
  })

  it('honours denied grading capability even when the account has a teacher role', async () => {
    show('audio', false)
    expect(await screen.findByLabelText('Submitted audio by Ada')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Grade' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Award badge/ })).not.toBeInTheDocument()
  })
})
