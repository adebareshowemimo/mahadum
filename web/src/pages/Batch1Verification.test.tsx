import type { ReactNode } from 'react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen, within, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { adminApi, gamificationApi, type FlaggedReferral } from '@/lib/api'
import { ReferralCodesAdminPage } from './ReferralCodesAdminPage'
import { FraudReviewPage } from './FraudReviewPage'
import { AchievementsPage } from './AchievementsPage'

vi.mock('@/lib/profile/ActiveProfile', () => ({
  useActiveProfile: () => ({ activeLearner: { id: 1, display_name: 'Ada' } }),
}))

afterEach(() => vi.restoreAllMocks())

function show(page: ReactNode) {
  return render(<QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
    {page}
  </QueryClientProvider>)
}

describe('Batch 1 existing feature verification', () => {
  it('X5/F3: displays the channel counts without hiding their columns on mobile', async () => {
    vi.spyOn(adminApi, 'referralCodes').mockResolvedValue({
      data: [{ id: 1, sn: 1, code: 'BATCHONE', status: 'active', owner: { type: 'user', id: 1, name: 'Ada' },
        count_activated: 3, via_email: 2, via_phone: 1, active_count: 2, inactive_count: 1 }],
      meta: { current_page: 1, last_page: 1, per_page: 20, total: 1 },
    })
    show(<ReferralCodesAdminPage />)
    const row = await screen.findByRole('row', { name: /BATCHONE/ })
    expect(within(row).getAllByRole('cell').map(cell => cell.textContent)).toEqual([
      '1', 'BATCHONEAda · user', '3', '2', '1', '2', '1',
    ])
    for (const name of ['Via email', 'Via phone']) {
      expect(screen.getByRole('columnheader', { name })).not.toHaveClass('hidden')
    }
    expect(within(row).getAllByRole('cell').every(cell => !cell.classList.contains('hidden'))).toBe(true)
  })

  it('B53: confirms fraud, displays frozen state, then clears the review queue', async () => {
    const code: FlaggedReferral = { id: 1, code: 'VELOCITY', kind: 'user', status: 'flagged',
      owner: { type: 'user', id: 1, name: 'Ada' }, referrals_24h: 16, referrals_total: 16, updated_at: null }
    let queue = [code]
    vi.spyOn(adminApi, 'flaggedReferrals').mockImplementation(async () => queue)
    const freeze = vi.spyOn(adminApi, 'freezeReferral').mockImplementation(async () => {
      queue = [{ ...code, status: 'frozen' }]
      return { id: 1, status: 'frozen' }
    })
    const clear = vi.spyOn(adminApi, 'clearReferral').mockImplementation(async () => {
      queue = []
      return { id: 1, status: 'active' }
    })
    show(<FraudReviewPage />)
    expect(await screen.findByText('flagged')).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Confirm fraud' }))
    expect(await screen.findByText('frozen')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Confirm fraud' })).not.toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Clear' }))
    expect(await screen.findByText(/No referral codes under review/)).toBeInTheDocument()
    expect(freeze).toHaveBeenCalledWith(1)
    expect(clear).toHaveBeenCalledWith(1)
  })

  it('F8: opens an earned badge with its level, detail and actual earned date', async () => {
    vi.spyOn(gamificationApi, 'streak').mockResolvedValue({ count: 1, longest: 1, state: 'active' } as never)
    vi.spyOn(gamificationApi, 'hearts').mockResolvedValue({ current: 5, unlimited_hearts: false } as never)
    vi.spyOn(gamificationApi, 'leagueCurrent').mockResolvedValue({ weekly_xp: 20 } as never)
    vi.spyOn(gamificationApi, 'badges').mockResolvedValue({
      earned: [{ code: 'tier_0', name: 'Star Starter', level: 0, icon: '⭐',
        description: 'Awarded for successfully completing Level 0 content.', earned_at: '2026-10-06T15:00:00Z' }],
      locked: [{ code: 'tier_5', name: 'Culture Master', level: 5, icon: '👑', description: 'Complete Level 5.' }],
    })
    show(<AchievementsPage />)
    await userEvent.click(await screen.findByRole('button', { name: /Star Starter/ }))
    const detail = screen.getByRole('dialog', { name: 'Star Starter' })
    expect(within(detail).getByText('Level 0')).toBeInTheDocument()
    expect(within(detail).getByText('Awarded for successfully completing Level 0 content.')).toBeInTheDocument()
    expect(within(detail).getByText(`Earned ${new Date('2026-10-06T15:00:00Z').toLocaleDateString()}`)).toBeInTheDocument()
    await userEvent.click(within(detail).getByRole('button', { name: /close/i }))
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    await userEvent.click(screen.getByRole('button', { name: /Culture Master/ }))
    expect(within(screen.getByRole('dialog')).getByText('Not earned yet')).toBeInTheDocument()
  })
})
