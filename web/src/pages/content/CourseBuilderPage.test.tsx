import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { CourseBuilderPage } from './CourseBuilderPage'

const mocks = vi.hoisted(() => ({ update: vi.fn(), create: vi.fn(), free: true }))
vi.mock('@/lib/content/permissions', () => ({ useCanManageContent: () => true }))
vi.mock('@/components/content/CourseContents', () => ({ CourseContents: () => null }))
vi.mock('@/lib/content/queries', () => {
  const mutation = () => ({ mutateAsync: vi.fn(), mutate: vi.fn(), isPending: false })
  return {
    useAuthorCourses: () => ({ data: [{ id: 12, title: 'Yoruba course' }] }),
    useCourseLevels: () => ({ data: [{ id: 7, title: 'Introduction', position: 1, has_assessment: false, is_free: mocks.free }], isLoading: false }),
    useLevelLessons: () => ({ data: [], isLoading: false }),
    useCreateLevel: () => ({ mutateAsync: mocks.create, isPending: false }),
    useUpdateLevel: () => ({ mutateAsync: mocks.update, isPending: false }),
    useCreateLesson: mutation, useDeleteLesson: mutation, useDeleteLevel: mutation,
    useReorderLessons: mutation, useReorderLevels: mutation, useUpdateLesson: mutation, useUpdateCourse: mutation,
  }
})
function renderPage() {
  return render(<MemoryRouter initialEntries={['/courses/12']}><Routes><Route path="/courses/:courseId" element={<CourseBuilderPage />} /></Routes></MemoryRouter>)
}
describe('Level access editor', () => {
  beforeEach(() => { mocks.free = true; mocks.update.mockReset(); mocks.create.mockReset() })
  it('loads the saved setting and lets a manager make the level paid', async () => {
    renderPage()
    fireEvent.click(screen.getByRole('button', { name: 'Edit' }))
    const dialog = screen.getByRole('dialog', { name: 'Edit level' })
    const toggle = within(dialog).getByRole('switch', { name: 'Free access' })
    expect(toggle).toHaveAttribute('aria-checked', 'true')
    fireEvent.click(toggle)
    fireEvent.click(within(dialog).getByRole('button', { name: 'Save changes' }))
    await waitFor(() => expect(mocks.update).toHaveBeenCalledWith({ levelId: 7, input: { title: 'Introduction', is_free: false } }))
  })
  it('defaults new levels to paid and submits a free designation when chosen', async () => {
    renderPage()
    fireEvent.click(screen.getByRole('button', { name: 'Add level' }))
    const dialog = screen.getByRole('dialog', { name: 'Add level' })
    const toggle = within(dialog).getByRole('switch', { name: 'Free access' })
    expect(toggle).toHaveAttribute('aria-checked', 'false')
    fireEvent.change(within(dialog).getByLabelText('Level title'), { target: { value: 'Bonus' } })
    fireEvent.click(toggle)
    fireEvent.click(within(dialog).getByRole('button', { name: 'Add level' }))
    await waitFor(() => expect(mocks.create).toHaveBeenCalledWith({ title: 'Bonus', is_free: true }))
  })
})
