import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { EmailTemplateDetailPage } from './EmailTemplateDetailPage'

const mocks = vi.hoisted(() => ({
  useEmailTemplate: vi.fn(),
  useEmailTemplatePreview: vi.fn(),
  useResetEmailTemplate: vi.fn(),
  useUpdateEmailTemplate: vi.fn(),
}))

vi.mock('@/lib/admin/queries', () => mocks)

const template = {
  key: 'welcome',
  label: 'Welcome (post-verify)',
  category: 'Auth',
  trigger: 'User verifies their email address',
  customizable: true,
  placeholders: { '{{brand_name}}': 'Platform name' },
  default: {
    subject: 'Welcome to {{brand_name}}',
    content_mode: 'structured' as const,
    include_branding: true,
    greeting: 'Welcome!',
    body: 'Your account is ready.',
    html_body: null,
    action_text: 'Start learning',
    action_url: '{{brand_url}}',
  },
  override: null,
}

function renderPage() {
  return render(
    <MemoryRouter initialEntries={['/admin/emails/templates/welcome']}>
      <Routes>
        <Route path="/admin/emails/templates/:templateKey" element={<EmailTemplateDetailPage />} />
      </Routes>
    </MemoryRouter>,
  )
}

describe('EmailTemplateDetailPage', () => {
  const mutateAsync = vi.fn()

  beforeEach(() => {
    mutateAsync.mockReset().mockResolvedValue(template)
    mocks.useEmailTemplate.mockReturnValue({ data: template, isLoading: false, isError: false })
    mocks.useEmailTemplatePreview.mockReturnValue({ data: { key: 'welcome', subject: 'Welcome', html: '<p>Preview</p>' }, isLoading: false, isError: false })
    mocks.useUpdateEmailTemplate.mockReturnValue({ isPending: false, mutateAsync })
    mocks.useResetEmailTemplate.mockReturnValue({ isPending: false, mutateAsync: vi.fn() })
  })

  it('offers rich HTML mode for a customizable template and saves source HTML', async () => {
    renderPage()

    fireEvent.change(screen.getByLabelText(/content format/i), { target: { value: 'html' } })
    fireEvent.click(screen.getByRole('switch', { name: /include the system email header/i }))
    fireEvent.click(screen.getByRole('button', { name: 'HTML' }))
    fireEvent.change(screen.getByLabelText(/html email source/i), { target: { value: '<h2>Hello {{brand_name}}</h2>' } })
    fireEvent.click(screen.getByRole('button', { name: /save changes/i }))

    await waitFor(() => expect(mutateAsync).toHaveBeenCalledWith({
      key: 'welcome',
      input: expect.objectContaining({
        content_mode: 'html',
        include_branding: false,
        html_body: '<h2>Hello {{brand_name}}</h2>',
      }),
    }))
  })
})
