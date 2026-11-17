import { act, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { describe, expect, it, vi } from 'vitest'
import { GuideManagerPage } from './GuideManagerPage'

const { get, post } = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }))
vi.mock('@/lib/api/client', () => ({ api: { get, post, put: vi.fn(), delete: vi.fn() } }))

describe('guide manager', () => {
  it('sends a visible YouTube video with the multipart values expected by the API', async () => {
    const user = userEvent.setup()
    get.mockReset(); post.mockReset()
    get.mockResolvedValue({ data: { data: [], current_page: 1, last_page: 1, total: 0, from: null, to: null } })
    post.mockResolvedValue({ data: { data: { id: 1 } } })
    render(<QueryClientProvider client={new QueryClient()}><GuideManagerPage /></QueryClientProvider>)
    await screen.findByText('No entries found.')
    await user.click(screen.getByRole('button', { name: 'Add video' }))
    expect(screen.getByRole('dialog', { name: 'Add video' })).toBeInTheDocument()
    await user.type(screen.getByRole('textbox', { name: 'Title' }), 'Create an account')
    await user.type(screen.getByRole('textbox', { name: 'YouTube video URL' }), 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
    await user.click(screen.getByRole('checkbox', { name: 'Show on public Guides page' }))
    await user.click(screen.getByRole('button', { name: 'Save video' }))
    await waitFor(() => expect(post).toHaveBeenCalled())
    const body = post.mock.calls[0][1] as FormData
    expect(body.get('section')).toBe('how_to')
    expect(body.get('topic')).toBe('getting_started')
    expect(body.get('is_visible')).toBe('1')
    expect(body.get('youtube_url')).toBe('https://www.youtube.com/watch?v=dQw4w9WgXcQ')
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
  })

  it('shows edit and FAQ forms in closable dialogs', async () => {
    const user = userEvent.setup()
    get.mockReset(); post.mockReset()
    get.mockImplementation((url: string) => Promise.resolve({ data: {
      data: url.includes('guide-faqs') ? [] : [{ id: 7, section: 'how_to', title: 'Hausa lesson', description: '', language: 'hausa', topic: 'learning', source_type: 'youtube', youtube_id: 'NAC7DVDMGzM', file_url: null, duration_seconds: null, sort_order: 1, is_visible: true }],
      current_page: 1, last_page: 1, total: url.includes('guide-faqs') ? 0 : 1, from: 1, to: 1,
    } }))
    render(<QueryClientProvider client={new QueryClient()}><GuideManagerPage /></QueryClientProvider>)
    await screen.findByText('Hausa lesson')
    await user.click(screen.getByRole('button', { name: 'Edit' }))
    expect(screen.getByRole('dialog', { name: 'Edit video' })).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Cancel' }))
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    await user.click(screen.getByRole('tab', { name: 'FAQ' }))
    await user.click(screen.getByRole('button', { name: 'Add question' }))
    expect(screen.getByRole('dialog', { name: 'Add question' })).toBeInTheDocument()
    await user.keyboard('{Escape}')
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('selects a video file and shows actual upload progress until saving finishes', async () => {
    const user = userEvent.setup()
    get.mockReset(); post.mockReset()
    get.mockResolvedValue({ data: { data: [], current_page: 1, last_page: 1, total: 0, from: null, to: null } })
    let finishUpload!: (value: unknown) => void
    post.mockImplementation(() => new Promise((resolve) => { finishUpload = resolve }))
    render(<QueryClientProvider client={new QueryClient()}><GuideManagerPage /></QueryClientProvider>)
    await screen.findByText('No entries found.')
    await user.click(screen.getByRole('button', { name: 'Add video' }))
    await user.type(screen.getByRole('textbox', { name: 'Title' }), 'Intro video')
    await user.selectOptions(screen.getByLabelText('Video source'), 'upload')
    expect(screen.getByRole('button', { name: /Choose video file/ })).toBeInTheDocument()
    const file = new File(['video contents'], 'intro.mp4', { type: 'video/mp4' })
    await user.upload(screen.getByLabelText('Choose video file'), file)
    expect(screen.getByText(/Selected:/)).toHaveTextContent('intro.mp4')
    await user.click(screen.getByRole('button', { name: 'Save video' }))
    await waitFor(() => expect(post).toHaveBeenCalled())
    expect((post.mock.calls[0][1] as FormData).get('file')).toBe(file)
    expect(screen.getByRole('progressbar', { name: 'Video upload progress' })).toHaveAttribute('aria-valuenow', '0')
    act(() => post.mock.calls[0][2].onUploadProgress({ loaded: 50, total: 100 }))
    await waitFor(() => expect(screen.getByRole('progressbar', { name: 'Video upload progress' })).toHaveAttribute('aria-valuenow', '50'))
    act(() => post.mock.calls[0][2].onUploadProgress({ loaded: 100, total: 100 }))
    await waitFor(() => expect(screen.getByText('Upload complete. Saving video…')).toBeInTheDocument())
    await act(async () => finishUpload({ data: { data: { id: 2 } } }))
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
  })
})
