import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { LoginPage } from './LoginPage'
import { ApiError } from '@/lib/api'

const { login } = vi.hoisted(() => ({ login: vi.fn() }))
vi.mock('@/lib/auth/AuthProvider', () => ({
  useAuth: () => ({ login, loginWithGoogle: vi.fn() }),
}))

function setup() {
  render(
    <MemoryRouter>
      <LoginPage />
    </MemoryRouter>,
  )
}

describe('LoginPage', () => {
  beforeEach(() => login.mockReset())

  it('submits the entered credentials', async () => {
    login.mockResolvedValueOnce(undefined)
    setup()
    await userEvent.type(screen.getByLabelText(/email or username/i), 'ada@demo.test')
    await userEvent.type(screen.getByLabelText(/^password$/i), 'Password123!')
    await userEvent.click(screen.getByRole('button', { name: /^sign in$/i }))
    expect(login).toHaveBeenCalledWith({ login: 'ada@demo.test', password: 'Password123!' })
  })

  it('surfaces an invalid-credentials error', async () => {
    login.mockRejectedValueOnce(new ApiError('The provided credentials are incorrect.', 'invalid_credentials', 401))
    setup()
    await userEvent.type(screen.getByLabelText(/email or username/i), 'ada@demo.test')
    await userEvent.type(screen.getByLabelText(/^password$/i), 'wrong')
    await userEvent.click(screen.getByRole('button', { name: /^sign in$/i }))
    expect(await screen.findByText(/credentials are incorrect/i)).toBeInTheDocument()
  })
})
