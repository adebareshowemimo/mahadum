import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { LearnPage } from './LearnPage'

vi.mock('@/lib/profile/ActiveProfile', () => ({
  useActiveProfile: () => ({ activeLearner: null }),
}))

vi.mock('@/lib/learning/queries', () => ({
  usePath: () => ({ data: undefined, isLoading: false, isError: false }),
  useCourses: vi.fn(),
  useEnroll: vi.fn(),
}))

vi.mock('@/lib/gamification/queries', () => ({
  useHearts: vi.fn(),
  useStreak: vi.fn(),
}))

vi.mock('@/pages/CourseCatalogPage', () => ({
  CourseCatalogPage: () => <div>Published course catalogue</div>,
}))

describe('LearnPage', () => {
  it('shows the course catalogue when no learner is selected', () => {
    render(<LearnPage />)
    expect(screen.getByText('Published course catalogue')).toBeInTheDocument()
  })
})
