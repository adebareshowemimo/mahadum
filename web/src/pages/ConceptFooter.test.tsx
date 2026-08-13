import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ConceptFooter } from './LandingVariantsPage'
import { useAuth } from '@/lib/auth/AuthProvider'

vi.mock('@/lib/auth/AuthProvider', () => ({
  useAuth: vi.fn(),
}))

describe('ConceptFooter account navigation', () => {
  beforeEach(() => vi.mocked(useAuth).mockReset())

  it('shows sign in to guests', () => {
    vi.mocked(useAuth).mockReturnValue({ status: 'unauthenticated' } as ReturnType<typeof useAuth>)
    render(<MemoryRouter><ConceptFooter /></MemoryRouter>)

    expect(screen.getByRole('link', { name: 'Sign in' })).toHaveAttribute('href', '/login')
    expect(screen.queryByRole('link', { name: 'Dashboard' })).not.toBeInTheDocument()
  })

  it.each(['authenticated', 'loading'] as const)('shows the dashboard when auth is %s', (status) => {
    vi.mocked(useAuth).mockReturnValue({ status } as ReturnType<typeof useAuth>)
    render(<MemoryRouter><ConceptFooter /></MemoryRouter>)

    expect(screen.getByRole('link', { name: 'Dashboard' })).toHaveAttribute('href', '/home')
    expect(screen.queryByRole('link', { name: 'Sign in' })).not.toBeInTheDocument()
  })
})
