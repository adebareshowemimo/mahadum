import { fireEvent, render, screen } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { EmailConfigurationPage } from './EmailConfigurationPage'

const mocks = vi.hoisted(() => ({
  useEmailConfiguration: vi.fn(),
  useUpdateEmailConfiguration: vi.fn(),
  useTestEmailConfiguration: vi.fn(),
}))

vi.mock('@/lib/admin/queries', () => mocks)

const base = {
  mailer: 'log',
  host: '127.0.0.1',
  port: 2525,
  scheme: null,
  username: null,
  password_set: false,
  from_address: 'hello@example.com',
  from_name: 'MAHADUM.360',
  delivery_enabled: false,
  queue_connection: 'database',
  pending_jobs: 4,
  failed_jobs: 0,
}

describe('EmailConfigurationPage', () => {
  beforeEach(() => {
    mocks.useEmailConfiguration.mockReturnValue({ data: base, isLoading: false, isError: false })
    mocks.useUpdateEmailConfiguration.mockReturnValue({ isPending: false, mutateAsync: vi.fn() })
    mocks.useTestEmailConfiguration.mockReturnValue({ isPending: false, mutateAsync: vi.fn() })
  })

  it('explains why log mail cannot deliver and reports queued jobs', () => {
    render(<EmailConfigurationPage />)

    expect(screen.getByText(/system email is not reaching inboxes/i)).toBeInTheDocument()
    expect(screen.getByText(/4 pending jobs/i)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /send test email/i })).toBeDisabled()
  })

  it('lets an admin send an immediate SMTP test', async () => {
    const mutateAsync = vi.fn().mockResolvedValue({ ok: true, message: 'Sent' })
    mocks.useEmailConfiguration.mockReturnValue({
      data: { ...base, mailer: 'smtp', delivery_enabled: true, password_set: true },
      isLoading: false,
      isError: false,
    })
    mocks.useTestEmailConfiguration.mockReturnValue({ isPending: false, mutateAsync })

    render(<EmailConfigurationPage />)
    fireEvent.change(screen.getByLabelText(/destination email/i), { target: { value: 'admin@example.test' } })
    fireEvent.click(screen.getByRole('button', { name: /send test email/i }))

    expect(mutateAsync).toHaveBeenCalledWith('admin@example.test')
    expect(await screen.findByText('Sent')).toBeInTheDocument()
  })
})
