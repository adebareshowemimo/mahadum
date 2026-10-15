import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { beforeAll, describe, expect, it, vi } from 'vitest'
import { expectNoA11yViolations } from '@/test/a11y'
import { FamiliesPage, InstitutionsPage } from './PublicAudiencePages'
import {
  AccessibilityPage,
  ChildSafetyPage,
  ChildrenPrivacyPage,
  ContactPage,
  PrivacyPage,
  RefundPolicyPage,
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
    [<RefundPolicyPage />, /No refund policy for lessons and subscriptions/i],
    [<ChildrenPrivacyPage />, /Children’s privacy and parental consent notice/i],
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

  it('links families directly to the playable lesson on the selected landing page', () => {
    renderPage(<FamiliesPage />, '/families')

    expect(screen.getByRole('link', { name: /Try a lesson/i })).toHaveAttribute('href', '/#v1-quiz')
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

  it('preserves billing corrections and separates data purchases from the no-refund rule', () => {
    renderPage(<RefundPolicyPage />, '/refunds')
    expect(screen.getByText(/Duplicate charge: you were charged more than once/)).toBeInTheDocument()
    expect(screen.getByText(/This policy does not cover data top-ups/)).toBeInTheDocument()
    expect(screen.getByText(/Nothing in this policy takes away rights/)).toBeInTheDocument()
    expect(screen.getByText(/within 30 days of the charge/)).toBeInTheDocument()
  })

  it('publishes the supplied child privacy periods without editorial placeholders', () => {
    const { container } = renderPage(<ChildrenPrivacyPage />, '/children-privacy')
    expect(screen.getByText(/Recordings are deleted after 90 days/)).toHaveTextContent(/30 days after approval or rejection/)
    expect(screen.getByText(/inactive for 24 months/)).toHaveTextContent(/within 30 days/)
    expect(container.textContent).not.toMatch(/DRAFT FOR LEGAL REVIEW|\[confirm|\[\[|Name \/ role/i)
    expect(screen.getAllByRole('link', { name: /Refund policy/i })[0]).toHaveAttribute('href', '/refunds')
  })

  it('has no automated accessibility violations on the contact form', async () => {
    const { container } = renderPage(<ContactPage />, '/contact')
    await expectNoA11yViolations(container)
  })
})
