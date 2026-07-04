import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { PaywallGate } from './PaywallGate'
import { FREE_ENTITLEMENTS, useEntitlements } from '@/lib/billing/entitlements'

vi.mock('@/lib/billing/entitlements', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/lib/billing/entitlements')>()
  return { ...actual, useEntitlements: vi.fn() }
})

function renderGate() {
  return render(
    <MemoryRouter>
      <PaywallGate feature="family_dashboard">
        <div>secret family content</div>
      </PaywallGate>
    </MemoryRouter>,
  )
}

describe('PaywallGate', () => {
  beforeEach(() => vi.mocked(useEntitlements).mockReset())

  it('locks the content and shows an upgrade CTA when not entitled', () => {
    vi.mocked(useEntitlements).mockReturnValue(FREE_ENTITLEMENTS)
    renderGate()
    expect(screen.queryByText('secret family content')).not.toBeInTheDocument()
    expect(screen.getByText(/Premium \(Family\)/)).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /see plans/i })).toHaveAttribute('href', '/billing')
  })

  it('renders the children when the feature is entitled', () => {
    vi.mocked(useEntitlements).mockReturnValue({ ...FREE_ENTITLEMENTS, family_dashboard: true })
    renderGate()
    expect(screen.getByText('secret family content')).toBeInTheDocument()
  })
})
