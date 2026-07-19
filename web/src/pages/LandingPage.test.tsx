import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { beforeAll, describe, expect, it, vi } from 'vitest'
import { LandingPage } from './LandingPage'
import { LANDING_LANGUAGES } from '@/components/landing/languages'
import { expectNoA11yViolations } from '@/test/a11y'

vi.mock('@/lib/auth/AuthProvider', () => ({
  useAuth: () => ({ status: 'guest', user: null, hasRole: () => false }),
}))

// jsdom has no IntersectionObserver; Reveal falls back to visible without it,
// but stubbing it keeps the reveal path itself under test.
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

function renderPage() {
  return render(
    <MemoryRouter>
      <LandingPage />
    </MemoryRouter>,
  )
}

const yoruba = LANDING_LANGUAGES[0]
const igbo = LANDING_LANGUAGES[1]

describe('LandingPage', () => {
  it('has no accessibility violations', async () => {
    const { container } = renderPage()
    await expectNoA11yViolations(container)
  })

  it('leads with the four languages using their correct diacritics', () => {
    renderPage()
    // Yorùbá, not "Yoruba" — diacritic correctness is a trust signal here and
    // a competitor is publicly criticised for getting it wrong.
    expect(screen.getAllByText('Yorùbá').length).toBeGreaterThan(0)
    expect(screen.getAllByText('Igbo').length).toBeGreaterThan(0)
  })

  it('switching language updates the greeting card and the lesson', async () => {
    const user = userEvent.setup()
    renderPage()

    // Scoped to the hero card: some greetings also appear as quiz options.
    const greeting = () => within(screen.getByTestId('hero-greeting'))
    expect(greeting().getByText(yoruba.greeting)).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: /Igbo/i }))

    expect(greeting().getByText(igbo.greeting)).toBeInTheDocument()
    expect(greeting().queryByText(yoruba.greeting)).not.toBeInTheDocument()
    // The lesson follows the selection rather than staying on Yorùbá.
    expect(screen.getByRole('button', { name: new RegExp(igbo.quiz[0].options[igbo.quiz[0].answer]) })).toBeInTheDocument()
  })

  it('grades a correct answer encouragingly and never says "wrong"', async () => {
    const user = userEvent.setup()
    renderPage()

    const round = yoruba.quiz[0]
    await user.click(screen.getByRole('button', { name: new RegExp(round.options[round.answer]) }))

    expect(screen.getByText('Well done!')).toBeInTheDocument()
    // BR-5: feedback is encouraging — "wrong"/"fail" must never appear.
    expect(screen.queryByText(/wrong|incorrect|fail/i)).not.toBeInTheDocument()
  })

  it('softens an incorrect answer and still reveals the answer', async () => {
    const user = userEvent.setup()
    renderPage()

    const round = yoruba.quiz[0]
    const wrongIndex = round.answer === 0 ? 1 : 0
    await user.click(screen.getByRole('button', { name: new RegExp(round.options[wrongIndex]) }))

    expect(screen.getByText(/Almost/)).toBeInTheDocument()
    expect(screen.getByText(round.note)).toBeInTheDocument()
  })

  it('completes the lesson and offers a signup', async () => {
    const user = userEvent.setup()
    renderPage()

    for (const round of yoruba.quiz) {
      await user.click(screen.getByRole('button', { name: new RegExp(round.options[round.answer]) }))
      await user.click(screen.getByRole('button', { name: /Next word|See how you did/ }))
    }

    expect(screen.getByText(/You just learned all three/)).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /Start learning free/i })).toBeInTheDocument()
  })

  it('releases coins only after the parent approves', async () => {
    const user = userEvent.setup()
    renderPage()

    // BR-8: nothing is released automatically.
    expect(screen.queryByText(/50 coins released/)).not.toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: /Approve & release coins/i }))

    expect(screen.getByText(/50 coins released/)).toBeInTheDocument()
    expect(screen.getByText('All caught up')).toBeInTheDocument()
  })

  it('expands an FAQ answer on demand', async () => {
    const user = userEvent.setup()
    renderPage()

    const trigger = screen.getByRole('button', { name: /Can I pay with airtime/i })
    expect(trigger).toHaveAttribute('aria-expanded', 'false')

    await user.click(trigger)

    expect(trigger).toHaveAttribute('aria-expanded', 'true')
    const panelId = trigger.getAttribute('aria-controls')!
    expect(within(document.getElementById(panelId)!).getByText(/airtime billing/i)).toBeInTheDocument()
  })
})
