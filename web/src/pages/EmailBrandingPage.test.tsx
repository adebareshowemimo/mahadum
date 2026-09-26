import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { EmailBrandingPage } from './EmailBrandingPage'

const mocks = vi.hoisted(() => ({
  useEmailBranding: vi.fn(),
  useUpdateEmailBranding: vi.fn(),
}))

vi.mock('@/lib/admin/queries', () => mocks)

const branding = {
  enabled: true,
  header_enabled: true,
  footer_enabled: true,
  header_html: '<p>Header</p>',
  footer_html: '<p>Footer</p>',
  preview_html: '<html><body>Preview</body></html>',
}

describe('EmailBrandingPage', () => {
  const mutateAsync = vi.fn()

  beforeEach(() => {
    mutateAsync.mockReset().mockResolvedValue(branding)
    mocks.useEmailBranding.mockReturnValue({ data: branding, isLoading: false, isError: false })
    mocks.useUpdateEmailBranding.mockReturnValue({ isPending: false, mutateAsync })
  })

  it('manages global header and footer visibility and content', async () => {
    render(<MemoryRouter><EmailBrandingPage /></MemoryRouter>)

    fireEvent.click(screen.getByRole('switch', { name: /show system header/i }))
    fireEvent.click(screen.getAllByRole('button', { name: 'HTML' })[0])
    fireEvent.change(screen.getAllByLabelText(/html email source/i)[0], { target: { value: '<h1>New header</h1>' } })
    fireEvent.click(screen.getByRole('button', { name: /save email branding/i }))

    await waitFor(() => expect(mutateAsync).toHaveBeenCalledWith(expect.objectContaining({
      enabled: true,
      header_enabled: false,
      footer_enabled: true,
      header_html: '<h1>New header</h1>',
    })))
  })
})
