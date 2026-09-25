import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { EmailCampaignsPage } from './EmailCampaignsPage'

const mocks = vi.hoisted(() => ({
  useContactLists: vi.fn(),
  useCreateEmailCampaign: vi.fn(),
  useEmailCampaigns: vi.fn(),
  useSendEmailCampaign: vi.fn(),
  useTestEmailCampaign: vi.fn(),
}))

vi.mock('@/lib/admin/queries', () => mocks)

describe('EmailCampaignsPage', () => {
  const mutateAsync = vi.fn()

  beforeEach(() => {
    mutateAsync.mockReset().mockResolvedValue({ id: 1 })
    mocks.useEmailCampaigns.mockReturnValue({
      data: { data: [], meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 } },
      isLoading: false,
      isError: false,
      isFetching: false,
    })
    mocks.useContactLists.mockReturnValue({ data: [{ id: 7, name: 'Newsletter', subscribed: 12 }] })
    mocks.useCreateEmailCampaign.mockReturnValue({ isPending: false, mutateAsync })
    mocks.useTestEmailCampaign.mockReturnValue({ isPending: false, mutateAsync: vi.fn() })
    mocks.useSendEmailCampaign.mockReturnValue({ isPending: false, mutateAsync: vi.fn() })
  })

  it('creates a rich HTML campaign from the shared visual editor', async () => {
    render(<MemoryRouter><EmailCampaignsPage /></MemoryRouter>)

    fireEvent.click(screen.getByRole('button', { name: /new campaign/i }))
    fireEvent.change(screen.getByLabelText(/subject/i), { target: { value: 'September update' } })
    fireEvent.click(screen.getByRole('button', { name: 'HTML' }))
    fireEvent.change(screen.getByLabelText(/html email source/i), { target: { value: '<h2>Hello families</h2>' } })
    fireEvent.change(screen.getByLabelText(/^list$/i), { target: { value: '7' } })
    fireEvent.click(screen.getByRole('button', { name: /save draft/i }))

    await waitFor(() => expect(mutateAsync).toHaveBeenCalledWith(expect.objectContaining({
      subject: 'September update',
      content_mode: 'html',
      body: '',
      html_body: '<h2>Hello families</h2>',
      audience_type: 'contact_list',
      audience: { contact_list_id: 7 },
    })))
  })
})
