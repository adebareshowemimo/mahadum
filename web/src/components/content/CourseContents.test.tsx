import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, useLocation } from 'react-router-dom'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { contentApi, type AuthorLesson } from '@/lib/api'
import { contentKeys } from '@/lib/content/queries'
import { CourseContents } from './CourseContents'

afterEach(() => vi.restoreAllMocks())

function Location() {
  return <output aria-label="Location">{useLocation().pathname}</output>
}

const lesson = (id: number, title: string, position: number): AuthorLesson => ({
  id, title, position, est_minutes: 5, is_locked_by_default: false, is_published: false, published_at: null,
})

function setup() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false, staleTime: Infinity } } })
  render(<QueryClientProvider client={client}><MemoryRouter initialEntries={['/courses/12/lessons/79']}>
    <CourseContents courseId={12} currentLessonId={79} /><Location />
  </MemoryRouter></QueryClientProvider>)
  return client
}

describe('Course table of contents', () => {
  it('uses saved unit and lesson order, highlights the current lesson and links directly to another lesson', async () => {
    vi.spyOn(contentApi, 'levels').mockResolvedValue([
      { id: 2, title: 'Second unit', position: 2, has_assessment: false },
      { id: 1, title: 'First unit', position: 1, has_assessment: false },
    ])
    vi.spyOn(contentApi, 'lessons').mockImplementation(async (id) => id === 1
      ? [lesson(80, 'Next lesson', 2), lesson(79, 'Introduce yourself', 1)] : [])
    const client = setup()
    const current = await screen.findByRole('link', { name: /Introduce yourself/ })
    expect(current).toHaveAttribute('aria-current', 'page')
    const nav = screen.getByRole('navigation', { name: 'Course table of contents' })
    expect(within(nav).getAllByRole('link').map((link) => link.getAttribute('href')))
      .toEqual(['/courses/12/lessons/79', '/courses/12/lessons/80'])
    expect(screen.getByText('No lessons yet.')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Edit table of contents' })).toHaveAttribute('href', '/courses/12#course-structure')
    await userEvent.click(screen.getByRole('link', { name: /Next lesson/ }))
    expect(screen.getByLabelText('Location')).toHaveTextContent('/courses/12/lessons/80')
    // Existing editor mutations refresh this same cache; the contents follow saved changes.
    client.setQueryData(contentKeys.lessons(1), [lesson(79, 'Renamed lesson', 1)])
    expect(await screen.findByRole('link', { name: /Renamed lesson/ })).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: /Next lesson/ })).not.toBeInTheDocument()
  })

  it('explains how to create contents for an empty course', async () => {
    vi.spyOn(contentApi, 'levels').mockResolvedValue([])
    setup()
    expect(await screen.findByText(/Add a level and lessons below/)).toBeInTheDocument()
  })

  it('reports a failed outline request', async () => {
    vi.spyOn(contentApi, 'levels').mockRejectedValue(new Error('Unavailable'))
    setup()
    expect(await screen.findByText('Couldn’t load the table of contents.')).toBeInTheDocument()
  })
})
