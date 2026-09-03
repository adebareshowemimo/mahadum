import { fireEvent, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { describe, expect, it, vi } from 'vitest'
import { RegisterPage } from './RegisterPage'

vi.mock('@/lib/auth/AuthProvider', () => ({
  useAuth: () => ({ register: vi.fn(), loginWithGoogle: vi.fn(), hasRole: () => false }),
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
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter>
        <RegisterPage />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

describe('RegisterPage age gate', () => {
  it('starts with the three account type choices', () => {
    setup()
    expect(screen.getByRole('radio', { name: /Family/i })).toBeInTheDocument()
    expect(screen.getByRole('radio', { name: /Educator\/School/i })).toBeInTheDocument()
    expect(screen.getByRole('radio', { name: /Institution/i })).toBeInTheDocument()
  })

  async function continueAsFamily() {
    await userEvent.click(screen.getByRole('radio', { name: /Family/i }))
    await userEvent.click(screen.getByRole('button', { name: /continue/i }))
  }

  it('moves from account selection to the age step', async () => {
    setup()
    await continueAsFamily()
    expect(screen.getByText(/how old are you/i)).toBeInTheDocument()
    expect(screen.getByLabelText(/date of birth/i)).toBeInTheDocument()
  })

  it('routes an under-13 DOB to the guardian-setup step', async () => {
    setup()
    await continueAsFamily()
    fireEvent.change(screen.getByLabelText(/date of birth/i), { target: { value: yearsAgo(8) } })
    await userEvent.click(screen.getByRole('button', { name: /continue/i }))
    expect(await screen.findByText(/grown-up needs to help/i)).toBeInTheDocument()
  })

  it('sends an adult DOB straight to the details form', async () => {
    setup()
    await continueAsFamily()
    fireEvent.change(screen.getByLabelText(/date of birth/i), { target: { value: yearsAgo(30) } })
    await userEvent.click(screen.getByRole('button', { name: /continue/i }))
    expect(await screen.findByLabelText(/first name/i)).toBeInTheDocument()
    expect(screen.getByLabelText(/^email$/i)).toBeInTheDocument()
    expect(screen.getByLabelText(/phone number/i)).toBeRequired()
  })

  it('requires a date of birth before continuing', async () => {
    setup()
    await continueAsFamily()
    await userEvent.click(screen.getByRole('button', { name: /continue/i }))
    expect(await screen.findByText(/enter your date of birth/i)).toBeInTheDocument()
  })

  it('requires an adult for an Educator/School account', async () => {
    setup()
    await userEvent.click(screen.getByRole('radio', { name: /Educator\/School/i }))
    await userEvent.click(screen.getByRole('button', { name: /continue/i }))
    fireEvent.change(screen.getByLabelText(/date of birth/i), { target: { value: yearsAgo(8) } })
    await userEvent.click(screen.getByRole('button', { name: /continue/i }))
    expect(await screen.findByText(/must be at least 13/i)).toBeInTheDocument()
  })

  it('asks an adult institution registrant for the institution name', async () => {
    setup()
    await userEvent.click(screen.getByRole('radio', { name: /Institution/i }))
    await userEvent.click(screen.getByRole('button', { name: /continue/i }))
    fireEvent.change(screen.getByLabelText(/date of birth/i), { target: { value: yearsAgo(30) } })
    await userEvent.click(screen.getByRole('button', { name: /continue/i }))
    expect(await screen.findByLabelText(/institution name/i)).toBeRequired()
  })

  it('shows only the missing registration fields for Google signup', async () => {
    setup()
    await continueAsFamily()
    fireEvent.change(screen.getByLabelText(/date of birth/i), { target: { value: yearsAgo(30) } })
    await userEvent.click(screen.getByRole('button', { name: /continue/i }))
    await userEvent.click(screen.getByRole('radio', { name: 'Google' }))

    expect(screen.getByLabelText(/phone number/i)).toBeRequired()
    expect(screen.queryByLabelText(/^email$/i)).not.toBeInTheDocument()
    expect(screen.queryByLabelText(/^password$/i)).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: /continue with google/i })).toBeInTheDocument()
  })
})
