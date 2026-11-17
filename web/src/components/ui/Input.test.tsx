import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'
import { Input } from './Input'

describe('password visibility', () => {
  it('shows and hides a password without changing its value', async () => {
    const user = userEvent.setup()
    render(<Input label="Confirm new password" type="password" passwordToggle defaultValue="Secret123!" />)
    const field = screen.getByLabelText('Confirm new password') as HTMLInputElement
    expect(field.type).toBe('password')
    await user.click(screen.getByRole('button', { name: 'Show confirm new password' }))
    expect(field.type).toBe('text')
    expect(field.value).toBe('Secret123!')
    await user.click(screen.getByRole('button', { name: 'Hide confirm new password' }))
    expect(field.type).toBe('password')
    expect(field.value).toBe('Secret123!')
  })
})
