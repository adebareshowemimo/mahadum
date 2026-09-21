import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { PracticeWithMeCard } from './PracticeWithMeCard'
import { ApiError } from '@/lib/api'
import * as queries from '@/lib/learning/queries'
import { expectNoA11yViolations } from '@/test/a11y'

vi.mock('@/lib/learning/queries', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/lib/learning/queries')>()),
  usePracticeContacts: vi.fn(),
  useInvitePractice: vi.fn(),
}))

const m = vi.mocked(queries)

describe('PracticeWithMeCard', () => {
  it('lists every prefetched family/school contact as soon as the search box is focused, before typing', async () => {
    m.useInvitePractice.mockReturnValue({ mutateAsync: vi.fn(), isPending: false } as never)
    m.usePracticeContacts.mockReturnValue({
      data: [
        { id: 7, name: 'Ada Lovelace', email: 'ada@example.com', relation: 'family', role: 'guardian' },
        { id: 9, name: 'Bola Teacher', email: 'bola@example.com', relation: 'school', role: 'teacher' },
      ],
      isLoading: false,
    } as never)

    render(<PracticeWithMeCard learnerId={3} />)
    await userEvent.click(screen.getByRole('button', { name: /invite someone/i }))
    await userEvent.click(screen.getByPlaceholderText(/search by name or email/i))

    expect(screen.getByText('Ada Lovelace')).toBeInTheDocument()
    expect(screen.getByText('Bola Teacher')).toBeInTheDocument()
  })

  it('searches, lists matches with their family/school relation, and sends an invitation', async () => {
    const mutateAsync = vi.fn().mockResolvedValue({ sent: true, expires_at: '2026-01-01T00:00:00Z' })
    m.useInvitePractice.mockReturnValue({ mutateAsync, isPending: false } as never)
    m.usePracticeContacts.mockReturnValue({
      data: [{ id: 7, name: 'Ada Lovelace', email: 'ada@example.com', relation: 'family', role: 'guardian' }],
      isLoading: false,
    } as never)

    render(<PracticeWithMeCard learnerId={3} />)
    await userEvent.click(screen.getByRole('button', { name: /invite someone/i }))
    await userEvent.type(screen.getByPlaceholderText(/search by name or email/i), 'ada')

    expect(screen.getByText('Ada Lovelace')).toBeInTheDocument()
    expect(screen.getByText('Family')).toBeInTheDocument()

    await userEvent.click(screen.getByText('Ada Lovelace'))

    await waitFor(() => expect(mutateAsync).toHaveBeenCalledWith({ learnerId: 3, recipientUserId: 7 }))
    expect(await screen.findByText(/Invitation sent to Ada Lovelace/)).toBeInTheDocument()
  })

  it('shows an error when the invite fails', async () => {
    const mutateAsync = vi.fn().mockRejectedValue(new ApiError('Not eligible', 'invalid', 422))
    m.useInvitePractice.mockReturnValue({ mutateAsync, isPending: false } as never)
    m.usePracticeContacts.mockReturnValue({
      data: [{ id: 9, name: 'Bola Teacher', email: 'bola@example.com', relation: 'school', role: 'teacher' }],
      isLoading: false,
    } as never)

    render(<PracticeWithMeCard learnerId={3} />)
    await userEvent.click(screen.getByRole('button', { name: /invite someone/i }))
    await userEvent.type(screen.getByPlaceholderText(/search by name or email/i), 'bola')
    await userEvent.click(screen.getByText('Bola Teacher'))

    expect(await screen.findByText('Not eligible')).toBeInTheDocument()
  })

  it('has no automated accessibility violations', async () => {
    m.useInvitePractice.mockReturnValue({ mutateAsync: vi.fn(), isPending: false } as never)
    m.usePracticeContacts.mockReturnValue({ data: [], isLoading: false } as never)
    const { container } = render(<PracticeWithMeCard learnerId={3} />)
    await expectNoA11yViolations(container)
  })
})
