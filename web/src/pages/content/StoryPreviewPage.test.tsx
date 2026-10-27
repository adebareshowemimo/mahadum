import { fireEvent, render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { StoryPreviewPage } from './StoryPreviewPage'

const mocks = vi.hoisted(() => ({ story: null as Record<string, unknown> | null }))
vi.mock('@tanstack/react-query', () => ({ useQuery: () => ({ data: mocks.story, isLoading: false, isError: false }) }))

function renderPage() {
  return render(<MemoryRouter initialEntries={['/courses/stories/7/preview']}><Routes>
    <Route path="/courses/stories/:storyId/preview" element={<StoryPreviewPage />} />
  </Routes></MemoryRouter>)
}

describe('creator story preview', () => {
  beforeEach(() => { mocks.story = null })

  it('switches between the twelve free colouring pages and the complete story', () => {
    mocks.story = {
      id: 7, title: 'Colours at the market', course_title: 'Sample course', language: 'yo',
      format: 'colouring', published_at: null, summary: null,
      pages: Array.from({ length: 13 }, (_, index) => ({ id: index + 1, position: index + 1, title: `Page ${index + 1}`, body: null, image_url: null })),
    }
    renderPage()
    expect(screen.getByText('Draft')).toBeInTheDocument()
    expect(screen.getByRole('heading', { name: 'Page 12' })).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Page 13' })).not.toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Full story' }))
    expect(screen.getByRole('heading', { name: 'Page 13' })).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Free preview' }))
    expect(screen.queryByRole('heading', { name: 'Page 13' })).not.toBeInTheDocument()
  })

  it('uses the separate preview and full clips for an animated story', () => {
    mocks.story = {
      id: 7, title: 'The talking drum', course_title: 'Sample course', language: 'yo',
      format: 'animated', published_at: '2026-10-27', summary: null, pages: [],
      preview_video_url: '/preview.mp4', full_video_url: '/full.mp4',
    }
    const { container } = renderPage()
    expect(container.querySelector('video')).toHaveAttribute('src', '/preview.mp4')
    fireEvent.click(screen.getByRole('button', { name: 'Full story' }))
    expect(container.querySelector('video')).toHaveAttribute('src', '/full.mp4')
  })
})
