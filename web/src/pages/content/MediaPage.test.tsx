import { fireEvent, render, screen } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { MediaPage } from './MediaPage'

const { libraryMock, uploadMutate } = vi.hoisted(() => ({
  libraryMock: vi.fn(),
  uploadMutate: vi.fn(),
}))

vi.mock('@/lib/content/queries', () => ({
  useMediaLibraryInfinite: libraryMock,
  useMediaOrphans: () => ({ data: { data: [], meta: { total: 0 } }, isLoading: false }),
  useUploadMedia: () => ({ mutateAsync: uploadMutate, isPending: false }),
  useDeleteMedia: () => ({ mutate: vi.fn(), isPending: false }),
  usePurgeMediaOrphans: () => ({ mutateAsync: vi.fn(), isPending: false }),
}))

class ObserverMock {
  observe() {}
  disconnect() {}
}

describe('MediaPage', () => {
  beforeEach(() => {
    vi.stubGlobal('IntersectionObserver', ObserverMock)
    libraryMock.mockReturnValue({
      data: {
        pages: [{
          data: [
            { id: 1, type: 'video', url: '/one.mp4', original_name: 'hello.mp4', folder: 'Yoruba/Week 1', created_at: '2026-09-21T00:00:00Z' },
            { id: 2, type: 'video', url: '/two.mp4', original_name: 'welcome.mp4', folder: 'Yoruba/Week 1', created_at: '2026-09-21T00:00:00Z' },
          ],
          folders: [{ name: 'Yoruba/Week 1', total: 2, video_count: 2 }],
          meta: { current_page: 1, last_page: 1, per_page: 24, total: 2, type_counts: { video: 2 } },
        }],
      },
      isLoading: false,
      isError: false,
      fetchNextPage: vi.fn(),
      hasNextPage: false,
      isFetchingNextPage: false,
    })
  })

  it('shows folder and video counts with a list view by default', () => {
    render(<MediaPage />)
    expect(screen.getByText('Matching videos')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Yoruba\/Week 1/i })).toHaveTextContent('2 videos · 2')
    expect(screen.getByRole('columnheader', { name: 'Video / file' })).toBeInTheDocument()
    expect(screen.getByText('hello.mp4')).toBeInTheDocument()
  })

  it('can switch between list and grid views', () => {
    render(<MediaPage />)
    fireEvent.click(screen.getByRole('button', { name: 'Grid' }))
    expect(screen.queryByRole('columnheader', { name: 'Video / file' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Grid' })).toHaveAttribute('aria-pressed', 'true')
  })
})
