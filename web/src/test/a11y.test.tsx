import { render } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { describe, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { expectNoA11yViolations } from './a11y'
import { LoginPage } from '@/pages/LoginPage'
import { RegisterPage } from '@/pages/RegisterPage'
import { ForgotPasswordPage } from '@/pages/ForgotPasswordPage'
import { DashboardPage } from '@/pages/DashboardPage'
import { Button, Input, Modal } from '@/components/ui'

vi.mock('@/lib/auth/AuthProvider', () => ({
  useAuth: () => ({
    login: vi.fn(),
    register: vi.fn(),
    loginWithGoogle: vi.fn(),
    logout: vi.fn(),
    hasRole: () => false,
    user: {
      user: { id: 1, first_name: 'Ada', last_name: 'Okeke', name: 'Ada Okeke', email: 'ada@demo.test', email_verified: true, roles: ['parent'] },
      families: [{ id: 1, name: "Okeke's Family", child_limit: 6, learners: [] }],
      organizations: [],
      active_organization_id: null,
      subscription: null,
    },
  }),
}))
vi.mock('@/lib/config/useConfig', () => ({
  useDigitalAge: () => 13,
  DEFAULT_DIGITAL_AGE: 13,
  useConfig: () => ({ data: undefined }),
}))

function wrap(ui: ReactNode) {
  return render(<MemoryRouter>{ui}</MemoryRouter>)
}

describe('accessibility (WCAG 2.1 A/AA via axe)', () => {
  it('login page has no violations', async () => {
    const { container } = wrap(<LoginPage />)
    await expectNoA11yViolations(container)
  })

  it('register (age step) has no violations', async () => {
    const { container } = wrap(<RegisterPage />)
    await expectNoA11yViolations(container)
  })

  it('forgot-password page has no violations', async () => {
    const { container } = wrap(<ForgotPasswordPage />)
    await expectNoA11yViolations(container)
  })

  it('dashboard has no violations', async () => {
    const { container } = wrap(<DashboardPage />)
    await expectNoA11yViolations(container)
  })

  it('an open modal with a form has no violations', async () => {
    const { container } = wrap(
      <Modal open title="Add a child" description="Create a learner profile." onClose={() => {}}>
        <form>
          <Input label="Display name" />
          <Button type="submit">Save</Button>
        </form>
      </Modal>,
    )
    await expectNoA11yViolations(container)
  })
})
