import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { describe, expect, it } from 'vitest'
import { ResetPasswordPage } from './ResetPasswordPage'

describe('reset password visibility', () => {
  it('shows and hides both password fields', async () => {
    const user = userEvent.setup()
    render(<MemoryRouter initialEntries={['/reset-password?token=test-token&email=test%40example.com']}><ResetPasswordPage /></MemoryRouter>)
    const password = screen.getByLabelText('New password') as HTMLInputElement
    const confirmation = screen.getByLabelText('Confirm new password') as HTMLInputElement
    expect(password.type).toBe('password')
    expect(confirmation.type).toBe('password')
    await user.click(screen.getByRole('button', { name: 'Show new password' }))
    await user.click(screen.getByRole('button', { name: 'Show confirm new password' }))
    expect(password.type).toBe('text')
    expect(confirmation.type).toBe('text')
  })
})
