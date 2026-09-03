import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { describe, expect, it, vi } from 'vitest'
import { ConsumerPageAdvert, isConsumerAdvertRoute } from './ConsumerPageAdvert'

vi.mock('./InlineAdvert', () => ({ InlineAdvert: () => <div data-testid="inline-advert" /> }))

describe('ConsumerPageAdvert', () => {
  it.each([
    '/home',
    '/learn',
    '/learn/courses',
    '/family',
    '/family/children/12',
    '/achievements',
    '/leaderboard',
    '/competitions',
    '/competitions/8',
    '/referrals',
  ])('allows an inline placement on %s', (pathname) => {
    expect(isConsumerAdvertRoute(pathname)).toBe(true)
  })

  it.each(['/learn/lessons/3', '/wallet', '/reviews', '/billing', '/support', '/admin', '/courses']) (
    'keeps adverts out of focused or operational route %s',
    (pathname) => {
      expect(isConsumerAdvertRoute(pathname)).toBe(false)
    },
  )

  it('renders the shared advert on a learner profile', () => {
    render(
      <MemoryRouter initialEntries={['/family/children/12']}>
        <ConsumerPageAdvert />
      </MemoryRouter>,
    )

    expect(screen.getByTestId('inline-advert')).toBeInTheDocument()
  })

  it('does not reserve advert space on billing', () => {
    render(
      <MemoryRouter initialEntries={['/billing']}>
        <ConsumerPageAdvert />
      </MemoryRouter>,
    )

    expect(screen.queryByTestId('inline-advert')).not.toBeInTheDocument()
  })
})
