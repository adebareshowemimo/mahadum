import { fireEvent, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { describe, expect, it, vi } from 'vitest'
import { RegisterPage } from './RegisterPage'

vi.mock('@/lib/auth/AuthProvider', () => ({
  useAuth: () => ({ register: vi.fn(), loginWithGoogle: vi.fn() }),
}))

vi.mock('@/lib/config/useConfig', () => ({
  useDigitalAge: () => 13,
  DEFAULT_DIGITAL_AGE: 13,
  useConfig: () => ({ data: undefined }),
}))

function yearsAgo(n: number): string {
  const d = new Date()
  d.setFullYear(d.getFullYear() - n)
  return d.toISOString().slice(0, 10)
}

function setup() {
  render(
    <MemoryRouter>
      <RegisterPage />
    </MemoryRouter>,
  )
}

describe('RegisterPage age gate', () => {
  it('starts on the age step', () => {
    setup()
    expect(screen.getByText(/how old are you/i)).toBeInTheDocument()
    expect(screen.getByLabelText(/date of birth/i)).toBeInTheDocument()
  })

  it('routes an under-13 DOB to the guardian-setup step', async () => {
    setup()
    fireEvent.change(screen.getByLabelText(/date of birth/i), { target: { value: yearsAgo(8) } })
    await userEvent.click(screen.getByRole('button', { name: /continue/i }))
    expect(await screen.findByText(/grown-up needs to help/i)).toBeInTheDocument()
  })

  it('sends an adult DOB straight to the details form', async () => {
    setup()
    fireEvent.change(screen.getByLabelText(/date of birth/i), { target: { value: yearsAgo(30) } })
    await userEvent.click(screen.getByRole('button', { name: /continue/i }))
    expect(await screen.findByLabelText(/first name/i)).toBeInTheDocument()
    expect(screen.getByLabelText(/email/i)).toBeInTheDocument()
  })

  it('requires a date of birth before continuing', async () => {
    setup()
    await userEvent.click(screen.getByRole('button', { name: /continue/i }))
    expect(await screen.findByText(/enter your date of birth/i)).toBeInTheDocument()
  })
})
