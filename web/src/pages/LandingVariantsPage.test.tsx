import { fireEvent, render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { beforeAll, describe, expect, it, vi } from 'vitest'
import { expectNoA11yViolations } from '@/test/a11y'
import { LandingV4Page, LandingV5Page } from './LandingExtendedVariantsPage'
import { LandingV1Page, LandingV2Page, LandingV3Page } from './LandingVariantsPage'

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

function renderConcept(component: React.ReactNode) {
  return render(<MemoryRouter>{component}</MemoryRouter>)
}

describe('landing concepts', () => {
  it.each([
    ['V1', <LandingV1Page />],
    ['V2', <LandingV2Page />],
    ['V3', <LandingV3Page />],
    ['V4', <LandingV4Page />],
    ['V5', <LandingV5Page />],
  ])('%s has no automated accessibility violations', async (_name, component) => {
    const { container } = renderConcept(component)
    await expectNoA11yViolations(container)
  })

  it('gives every concept a distinct primary promise', () => {
    const { unmount: unmountV1 } = renderConcept(<LandingV1Page />)
    expect(screen.getByRole('heading', { level: 1, name: /One hello can bring the whole family closer/i })).toBeInTheDocument()
    unmountV1()

    const { unmount: unmountV2 } = renderConcept(<LandingV2Page />)
    expect(screen.getByRole('heading', { level: 1, name: /Every new word opens a bigger world/i })).toBeInTheDocument()
    unmountV2()

    const { unmount: unmountV3 } = renderConcept(<LandingV3Page />)
    expect(screen.getByRole('heading', { level: 1, name: /Keep the language moving everywhere/i })).toBeInTheDocument()
    unmountV3()

    const { unmount: unmountV4 } = renderConcept(<LandingV4Page />)
    expect(screen.getByRole('heading', { level: 1, name: /Hear it at breakfast/i })).toBeInTheDocument()
    unmountV4()

    renderConcept(<LandingV5Page />)
    expect(screen.getByRole('heading', { level: 1, name: /Build the place where a language keeps growing/i })).toBeInTheDocument()
  })

  it('V1 connects language selection to the family greeting', async () => {
    const user = userEvent.setup()
    renderConcept(<LandingV1Page />)

    expect(within(screen.getByTestId('v1-greeting')).getByText('Ẹ káàrọ̀')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Igbo' }))
    expect(within(screen.getByTestId('v1-greeting')).getByText('Ụtụtụ ọma')).toBeInTheDocument()
  })

  it('V2 changes the visual story and content as adventures change', async () => {
    const user = userEvent.setup()
    renderConcept(<LandingV2Page />)

    await user.click(screen.getByRole('tab', { name: 'Story night' }))
    expect(screen.getByRole('heading', { name: /Listen, wonder and answer back/i })).toBeInTheDocument()
    expect(screen.getByRole('img', { name: /folktale by lantern light/i })).toBeInTheDocument()
  })

  it('V3 recalculates a school estimate from the published seat band', () => {
    renderConcept(<LandingV3Page />)
    fireEvent.change(screen.getByRole('slider', { name: /How many students/i }), { target: { value: '600' } })
    expect(screen.getByText('₦3,200,000')).toBeInTheDocument()
    expect(screen.getByText('₦5,000')).toBeInTheDocument()
  })

  it('V4 moves a phrase into a real family moment', async () => {
    const user = userEvent.setup()
    renderConcept(<LandingV4Page />)

    await user.click(screen.getByRole('tab', { name: 'Family call' }))
    expect(screen.getByRole('heading', { name: /Let the lesson arrive in a real conversation/i })).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Hausa' }))
    expect(screen.getAllByText('Ina kwana').length).toBeGreaterThan(0)
  })

  it('V5 explains the ecosystem from each role', async () => {
    const user = userEvent.setup()
    renderConcept(<LandingV5Page />)

    await user.click(screen.getByRole('tab', { name: /School/i }))
    expect(screen.getByRole('heading', { name: /Language learning has an operational home/i })).toBeInTheDocument()
    expect(screen.getByText('CSV roster import')).toBeInTheDocument()
  })

  it.each([
    ['V1', <LandingV1Page />],
    ['V2', <LandingV2Page />],
    ['V3', <LandingV3Page />],
    ['V4', <LandingV4Page />],
    ['V5', <LandingV5Page />],
  ])('%s includes a signature hero animation without gating its content', (_name, component) => {
    const { container } = renderConcept(component)
    expect(container.querySelector('.motion-hero-copy')).toBeInTheDocument()
    expect(container.querySelector('.motion-hero-media, .motion-hero-pan')).toBeInTheDocument()
  })
})
