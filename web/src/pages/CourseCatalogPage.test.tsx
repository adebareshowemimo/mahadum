import { fireEvent, render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { CourseCatalogPage } from './CourseCatalogPage'

const mocks = vi.hoisted(() => ({
  useActiveProfile: vi.fn(),
  useCourses: vi.fn(),
  useEnroll: vi.fn(),
  useStartCourseAsSelf: vi.fn(),
  useAuth: vi.fn(),
  useConfig: vi.fn(),
}))

vi.mock('@/lib/profile/ActiveProfile', () => ({
  useActiveProfile: mocks.useActiveProfile,
}))

vi.mock('@/lib/learning/queries', () => ({
  useCourses: mocks.useCourses,
  useEnroll: mocks.useEnroll,
  useStartCourseAsSelf: mocks.useStartCourseAsSelf,
}))

vi.mock('@/lib/auth/AuthProvider', () => ({
  useAuth: mocks.useAuth,
}))

vi.mock('@/lib/config/useConfig', () => ({
  useConfig: mocks.useConfig,
}))

const course = {
  id: 7,
  title: 'Everyday Yorùbá',
  description: 'Greetings, family words, and useful daily phrases.',
  level_band: 'A1',
  status: 'published',
  is_published: true,
  language: 'yo',
}

function renderPage() {
  render(
    <MemoryRouter>
      <CourseCatalogPage />
    </MemoryRouter>,
  )
}

describe('CourseCatalogPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mocks.useCourses.mockReturnValue({
      data: {
        data: [course],
        meta: { current_page: 1, last_page: 1, per_page: 12, total: 1 },
        filters: { levels: ['A1'] },
      },
      isLoading: false,
      isFetching: false,
      isError: false,
    })
    mocks.useEnroll.mockReturnValue({ isError: false, isPending: false, mutate: vi.fn() })
    mocks.useStartCourseAsSelf.mockReturnValue({ isError: false, isPending: false, mutate: vi.fn() })
    mocks.useAuth.mockReturnValue({ hasRole: () => false })
    mocks.useConfig.mockReturnValue({ data: { languages: [{ id: 1, code: 'yo', name: 'Yorùbá' }] } })
  })

  it('shows published courses before a parent selects a learner', () => {
    mocks.useActiveProfile.mockReturnValue({ activeLearner: null, learners: [{ id: 1 }], setActiveLearner: vi.fn() })

    renderPage()

    expect(mocks.useCourses).toHaveBeenCalledWith({ page: 1, per_page: 12 })
    expect(screen.getByText('Everyday Yorùbá')).toBeInTheDocument()
    expect(screen.getByText(/browse first, choose a learner later/i)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /choose a learner/i })).toBeDisabled()
  })

  it('enables course actions after a learner is selected', () => {
    mocks.useActiveProfile.mockReturnValue({
      activeLearner: { id: 12, display_name: 'Amara' },
      learners: [{ id: 12 }],
      setActiveLearner: vi.fn(),
    })

    renderPage()

    expect(mocks.useCourses).toHaveBeenCalledWith({ learner_id: 12, page: 1, per_page: 12 })
    expect(screen.getByText('Amara')).toBeInTheDocument()
    expect(screen.getByTitle('Amara').querySelector('svg')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /start course/i })).toBeEnabled()
  })

  it('lets a parent start a course with their own account', () => {
    const setActiveLearner = vi.fn()
    const mutate = vi.fn((
      _courseId: number,
      options: { onSuccess: (learner: { id: number; display_name: string }) => void },
    ) => options.onSuccess({ id: 44, display_name: 'Parent Learner' }))
    mocks.useAuth.mockReturnValue({ hasRole: (role: string) => role === 'parent' })
    mocks.useActiveProfile.mockReturnValue({ activeLearner: null, learners: [], setActiveLearner })
    mocks.useStartCourseAsSelf.mockReturnValue({ isError: false, isPending: false, mutate })

    renderPage()
    const start = screen.getByRole('button', { name: /start course/i })
    expect(start).toBeEnabled()
    fireEvent.click(start)

    expect(mutate).toHaveBeenCalledWith(7, expect.any(Object))
    expect(setActiveLearner).toHaveBeenCalledWith(44)
  })

  it('moves through catalogue pages without loading the full library', () => {
    mocks.useActiveProfile.mockReturnValue({ activeLearner: null, learners: [{ id: 1 }], setActiveLearner: vi.fn() })
    mocks.useCourses.mockReturnValue({
      data: {
        data: [course],
        meta: { current_page: 1, last_page: 3, per_page: 12, total: 30 },
        filters: { levels: ['A1'] },
      },
      isLoading: false,
      isFetching: false,
      isError: false,
    })

    renderPage()
    fireEvent.click(screen.getByRole('button', { name: 'Next' }))

    expect(mocks.useCourses).toHaveBeenLastCalledWith({ page: 2, per_page: 12 })
  })
})
