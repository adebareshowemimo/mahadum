import { fireEvent, render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { describe, expect, it, vi } from 'vitest'
import { AppLayout } from './AppLayout'

const profile = vi.hoisted(() => ({ child: false }))
vi.mock('@/lib/auth/AuthProvider', () => ({ useAuth: () => ({ user: { user: { name: 'Parent', first_name: 'Parent', email: 'parent@example.test', roles: ['parent'], capabilities: [] }, organizations: [] } }) }))
vi.mock('@/lib/profile/ActiveProfile', () => ({ useActiveProfile: () => ({ activeLearner: profile.child ? { id: 2, display_name: 'Tosin', is_child: true } : null }) }))
vi.mock('@/lib/theme', () => ({ useTheme: () => ({ theme: 'light', toggle: vi.fn() }) }))
vi.mock('@/lib/nav/navigation', () => ({ visibleSections: () => [] }))
vi.mock('@/components/layout/ProfileSwitcher', () => ({ ProfileSwitcher: () => null }))
vi.mock('@/components/adverts/AdvertLeaderboard', () => ({ AdvertLeaderboard: () => <div data-testid="general-banner" /> }))
vi.mock('@/components/adverts/InlineAdvert', () => ({ InlineAdvert: ({ position }: { position: string }) => <div data-testid="data-banner" data-position={position} /> }))

function renderPage(pathname: string) {
  return render(
    <MemoryRouter initialEntries={[pathname]}>
      <Routes>
        <Route element={<AppLayout />}>
          <Route path="*" element={<h1>Current page</h1>} />
        </Route>
      </Routes>
    </MemoryRouter>,
  )
}

describe('authenticated advert placement', () => {
  it.each(['/billing', '/billing/data', '/tasks', '/wallet', '/reviews', '/support', '/learn/lessons/3', '/admin'])('keeps the complete %s page free of banners', (pathname) => {
    renderPage(pathname)
    expect(screen.getByRole('heading', { name: 'Current page' })).toBeInTheDocument()
    expect(screen.queryByTestId('general-banner')).not.toBeInTheDocument()
    expect(screen.queryByTestId('data-banner')).not.toBeInTheDocument()
    expect(screen.queryByLabelText('Sponsored content')).not.toBeInTheDocument()
  })

  it('keeps the intended data slot on Home without adding a general banner', () => {
    renderPage('/home')
    expect(screen.getByTestId('data-banner')).toHaveAttribute('data-position', 'profile_data_topup')
    expect(screen.queryByTestId('general-banner')).not.toBeInTheDocument()
  })
})

describe('child account menu', () => {
  it.each(['/home', '/notifications', '/billing'])('opens learning instead of %s', (pathname) => {
    profile.child = true
    render(
      <MemoryRouter initialEntries={[pathname]}>
        <Routes>
          <Route element={<AppLayout />}>
            <Route path="/home" element={<h1>Parent account</h1>} />
            <Route path="/notifications" element={<h1>Account notifications</h1>} />
            <Route path="/billing" element={<h1>Billing</h1>} />
            <Route path="/learn" element={<h1>Child learning</h1>} />
          </Route>
        </Routes>
      </MemoryRouter>,
    )
    expect(screen.getByRole('heading', { name: 'Child learning' })).toBeInTheDocument()
    expect(screen.queryByText('Parent account')).not.toBeInTheDocument()
    profile.child = false
  })

  it('shows the child name without the parent identity', () => {
    profile.child = true
    renderPage('/learn')
    fireEvent.click(screen.getByRole('button', { name: /Tosin/ }))
    expect(screen.getAllByText('Tosin').length).toBeGreaterThan(0)
    expect(screen.queryByText('Parent')).not.toBeInTheDocument()
    expect(screen.queryByText('parent@example.test')).not.toBeInTheDocument()
    profile.child = false
  })
})
