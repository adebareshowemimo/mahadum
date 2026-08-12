import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { beforeAll, describe, expect, it, vi } from 'vitest'
import { expectNoA11yViolations } from '@/test/a11y'
import { AboutPage } from './AboutPage'

vi.mock('@/lib/auth/AuthProvider', () => ({
  useAuth: () => ({ status: 'unauthenticated', user: null, hasRole: () => false, logout: async () => {} }),
}))

beforeAll(() => {
  class MockIO {
    constructor(private cb: IntersectionObserverCallback) {}
    observe(el: Element) {
      this.cb([{ isIntersecting: true, target: el } as IntersectionObserverEntry], this as never)
    }
    disconnect() {}
    unobserve() {}
  }
  vi.stubGlobal('IntersectionObserver', MockIO)
})

function renderAbout() {
  return render(
    <MemoryRouter>
      <AboutPage />
    </MemoryRouter>,
  )
}

describe('AboutPage', () => {
  it('has no automated accessibility violations', async () => {
    const { container } = renderAbout()
    await expectNoA11yViolations(container)
  })

  it('connects each expanded-age character to a distinct learning story', async () => {
    const user = userEvent.setup()
    renderAbout()

    expect(screen.getByRole('img', { name: /Kene, a young Igbo voice guide/i })).toBeInTheDocument()

    await user.click(screen.getByRole('tab', { name: 'Teen learners' }))
    expect(screen.getByRole('img', { name: /Aondo, a confident mid-teen learner/i })).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: /Teenagers should be able to recognise themselves here/i })).toBeInTheDocument()

    await user.click(screen.getByRole('tab', { name: 'Young adults' }))
    expect(screen.getByRole('img', { name: /Musa, a Hausa young adult/i })).toBeInTheDocument()
  })
})
