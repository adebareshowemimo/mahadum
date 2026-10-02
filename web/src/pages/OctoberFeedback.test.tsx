import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { beforeAll, describe, expect, it, vi } from 'vitest'
import { FamiliesPage, InstitutionsPage } from './PublicAudiencePages'
import { ContactPage, contactEmailUrl } from './PublicTrustPages'
import { AboutPage } from './AboutPage'
import { LandingV5Page } from './LandingExtendedVariantsPage'
import { PricingPage } from './PricingPage'
import { pricingApi, type PricingInfo } from '@/lib/api'

vi.mock('@/lib/auth/AuthProvider', () => ({
  useAuth: () => ({ status: 'unauthenticated', user: null, hasRole: () => false, logout: async () => {} }),
}))

beforeAll(() => {
  class MockIO {
    constructor(private cb: IntersectionObserverCallback) {}
    observe(el: Element) { this.cb([{ isIntersecting: true, target: el } as IntersectionObserverEntry], this as never) }
    disconnect() {}
    unobserve() {}
  }
  vi.stubGlobal('IntersectionObserver', MockIO)
})

function show(page: React.ReactNode, route = '/') {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(<QueryClientProvider client={client}><MemoryRouter initialEntries={[route]}>{page}</MemoryRouter></QueryClientProvider>)
}

describe('October 1 public feedback', () => {
  it('F01-F04/F06/F07: renders the family corrections without the unlimited-free promise', () => {
    const { container } = show(<FamiliesPage />)
    expect(screen.getByText(/Mahadum360 helps families.*your home yours/)).toBeInTheDocument()
    expect(screen.getByText('Ndewo nwa m!')).toBeInTheDocument()
    expect(screen.getByText('Playful, age-respectful free lessons in beginner Level 0 let you try the platform before committing.')).toBeInTheDocument()
    expect(screen.getByText('Hear submitted practice or choose a trusted adult to practise with the child.')).toBeInTheDocument()
    expect(within(container.querySelector('footer')!).getByText(/Mahadum360, RC 9601595/)).toBeInTheDocument()
    expect(container.textContent).not.toContain('never lock learning behind hearts or payment')
  })

  it('S01/S02/S04/S05 heading/S06: shows school corrections without advertising unverified data fulfillment', async () => {
    const user = userEvent.setup()
    show(<LandingV5Page />)
    await user.click(screen.getByRole('tab', { name: /Teacher/ }))
    await user.click(screen.getByRole('tab', { name: /Learner/ }))
    expect(screen.getAllByText('Beginner Level 0 stays free')).toHaveLength(2)
    expect(screen.getByRole('heading', { name: 'A playful path from free Level 0 to deeper learning.' })).toBeInTheDocument()
    expect(screen.queryByText(/never locks the next lesson/)).not.toBeInTheDocument()
    expect(screen.queryByText('Out of data? Buy on Mahadum360')).not.toBeInTheDocument()
    expect(screen.getByText(/family missions, language and culture clubs/)).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Reliable connection' })).toBeInTheDocument()
    expect(screen.queryByText(/Buy data directly from the website and continue learning/)).not.toBeInTheDocument()
    expect(screen.getByText('A clear, simple school quote.')).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'See annual subscription fees and the cost per roll.' })).toBeInTheDocument()
  })

  it('I01: institution CTAs keep their distinct topics and direct email agrees', () => {
    show(<InstitutionsPage />)
    for (const [label, topic] of [['Discuss a partnership', 'partnership'], ['Discuss a campus', 'university'], ['Plan a programme', 'culture'], ['Explore delivery', 'government'], ['Discuss access', 'telecom']]) {
      const destinations = screen.getAllByRole('link', { name: label }).map(link => link.getAttribute('href'))
      expect(destinations).toContain(`/contact?topic=${topic}`)
      expect(destinations.every(href => href === `/contact?topic=${topic}` || href === '/contact?topic=partnership')).toBe(true)
    }
    expect(screen.getByRole('link', { name: /Partnerships@Mahadum360.com/ })).toHaveAttribute('href', 'mailto:Partnerships@Mahadum360.com')
  })

  it.each(['family', 'support', 'school', 'partnership', 'university', 'culture', 'government', 'telecom'])('I01: %s draft safely encodes content for the approved recipient', topic => {
    const draft = contactEmailUrl(topic, 'A & B', 'reply@example.test', 'Hello? &bcc=other@example.test\nNext line')
    expect(draft.split('?')[0]).toBe('mailto:Partnerships@Mahadum360.com')
    const params = new URLSearchParams(draft.split('?')[1])
    expect([...params.keys()]).toEqual(['subject', 'body'])
    expect(params.get('subject')).toBe(`Mahadum ${topic} enquiry from A & B`)
    expect(params.get('body')).toContain('Hello? &bcc=other@example.test\nNext line')
  })

  it('I01/C01: visible contact recipients update on selection and preserve sensitive routes', async () => {
    const user = userEvent.setup()
    show(<ContactPage />)
    expect(screen.getByRole('button', { name: 'Prepare email for the family & learning team' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /Partnerships@Mahadum360.com/ })).toHaveAttribute('href', 'mailto:Partnerships@Mahadum360.com')
    await user.click(screen.getByRole('radio', { name: /Child safety/ }))
    expect(screen.getByRole('link', { name: /safety@mahadum360.app/ })).toHaveAttribute('href', 'mailto:safety@mahadum360.app')
    expect(contactEmailUrl('accessibility', '', '', '').split('?')[0]).toBe('mailto:accessibility@mahadum360.app')
    await user.click(screen.getByRole('radio', { name: /Technical support/ }))
    expect(screen.getByRole('link', { name: /Partnerships@Mahadum360.com/ })).toBeInTheDocument()
  })

  it('A01-A04/A06: corrects the about text and teenager panel', async () => {
    const user = userEvent.setup()
    const { container } = show(<AboutPage />)
    expect(screen.getByRole('heading', { name: 'Mahadum360 opened its online school.' })).toBeInTheDocument()
    expect(screen.getByText(/Mahadum360 is designed/)).toBeInTheDocument()
    await user.click(screen.getByRole('tab', { name: 'Teen learners' }))
    expect(screen.getByText(/Age respectful design/)).toBeInTheDocument()
    expect(screen.getByText('Level 0 remains free, so every child can learn basic conversations before choosing a paid plan for deeper learning.')).toBeInTheDocument()
    expect(screen.getByText('Start with five joyful minutes. Every child learns basic conversations. Let the language become part of everyday life again.')).toBeInTheDocument()
    expect(container.textContent).not.toContain('Keep every lesson free')
  })

  it('P01-P04: renders monthly and annual paid cards and preserves other free benefits', async () => {
    const fixture: PricingInfo = {
      free: { name: 'Free', blurb: 'Start with Level 0' },
      consumer: ['month', 'year'].flatMap(interval => (['individual', 'family'] as const).map(audience => ({
        code: `${audience}-${interval}`, name: audience, audience, interval, price_minor: 123400, currency: 'NGN', max_profiles: audience === 'family' ? 6 : 1,
        features: { ads: false, offline_download: false, unlimited_hearts: true, family_dashboard: audience === 'family' },
      }))),
      school: { term_months: 9, bands: [{ label: 'School', registration_minor: 10000, per_student_minor: 1000 }] },
    }
    vi.spyOn(pricingApi, 'get').mockResolvedValue(fixture)
    const user = userEvent.setup()
    show(<PricingPage />)
    expect(screen.getByText('Start your learning journey and build basic language skills at no cost with Level 0. Only commit to a paid plan when you are satisfied. Unlock the paid curriculum for deeper learning and unlimited hearts, with family tools on Family plans.')).toBeInTheDocument()
    expect(await screen.findByText('All L0 lessons and quiz free')).toBeInTheDocument()
    expect(screen.getByText('Upgrade to unlock Level 1 and beyond.')).toBeInTheDocument()
    expect(screen.getByText('Every household can begin with free Level 0. Paid plans unlock deeper learning.')).toBeInTheDocument()
    expect(screen.queryByText(/No locked course/)).not.toBeInTheDocument()
    expect(screen.queryByText('Level 0 in every language')).not.toBeInTheDocument()
    for (const benefit of ['Speaking practice', 'XP, streaks and badges', 'Supported by age-appropriate ads']) expect(screen.getByText(benefit)).toBeInTheDocument()
    expect(screen.getAllByText('Paid lessons start from Level 1 only')).toHaveLength(2)
    await user.click(screen.getByRole('button', { name: 'Annual' }))
    expect(screen.getAllByText('Paid lessons start from Level 1 only')).toHaveLength(2)
    expect(screen.getByRole('button', { name: 'Annual' })).toHaveAttribute('aria-pressed', 'true')
    vi.restoreAllMocks()
  })
})
