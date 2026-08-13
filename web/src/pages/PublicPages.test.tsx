import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { beforeAll, describe, expect, it, vi } from 'vitest'
import { expectNoA11yViolations } from '@/test/a11y'
import { FamiliesPage, InstitutionsPage } from './PublicAudiencePages'
import {
  AccessibilityPage,
  ChildSafetyPage,
  ContactPage,
  PrivacyPage,
  TermsPage,
} from './PublicTrustPages'

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

function renderPage(page: React.ReactNode, route = '/') {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[route]}>{page}</MemoryRouter>
    </QueryClientProvider>,
  )
}

describe('public information pages', () => {
  it.each([
    [<FamiliesPage />, /Hear your child say it in your language/i],
    [<InstitutionsPage />, /Take Nigerian languages further/i],
    [<ContactPage />, /Let’s start with what you need/i],
    [<PrivacyPage />, /Privacy notice/i],
    [<TermsPage />, /Terms and conditions/i],
    [<ChildSafetyPage />, /Child safety is part of the learning design/i],
    [<AccessibilityPage />, /Learning should work for more people/i],
  ])('renders the expected page heading', (page, heading) => {
    renderPage(page)
    expect(screen.getByRole('heading', { level: 1, name: heading })).toBeInTheDocument()
  })

  it('has no automated accessibility violations on the combined family direction', async () => {
    const { container } = renderPage(<FamiliesPage />, '/families')
    await expectNoA11yViolations(container)
  })

  it('has no automated accessibility violations on the legal-page pattern', async () => {
    const { container } = renderPage(<PrivacyPage />, '/privacy')
    await expectNoA11yViolations(container)
  })

  it('preselects institutional contact enquiries from the route', () => {
    renderPage(<ContactPage />, '/contact?topic=partnership')
    expect(screen.getByRole('radio', { name: /Partnerships/i })).toBeChecked()
    expect(screen.getByRole('button', { name: /Partnerships team/i })).toBeInTheDocument()
  })

  it('has no automated accessibility violations on the contact form', async () => {
    const { container } = renderPage(<ContactPage />, '/contact')
    await expectNoA11yViolations(container)
  })
})
