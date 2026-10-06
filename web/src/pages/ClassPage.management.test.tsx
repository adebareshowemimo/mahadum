import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError, schoolApi, type SchoolClassDetail } from '@/lib/api'
import { ClassPage } from './ClassPage'

const auth = vi.hoisted(() => ({ roles: ['school_admin'], orgId: 3 as number | null }))
vi.mock('@/lib/auth/AuthProvider', () => ({ useAuth: () => ({
  activeOrgId: auth.orgId,
  user: { user: { id: 7, roles: auth.roles }, organizations: auth.orgId ? [{ id: auth.orgId }] : [] },
  hasRole: (...roles: string[]) => roles.some((role) => auth.roles.includes(role)),
}) }))

const classroom = {
  id: 11, name: '%th grade', level: 'L1', teacher: null, teacher_user_id: null,
  organization_id: 3, capabilities: { update: true, assign_teacher: true },
  students: [{ learner_id: 21, display_name: 'Existing learner' }],
}

function mount(detail = classroom) {
  vi.spyOn(schoolApi, 'classDetail').mockResolvedValue(detail as SchoolClassDetail)
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  return render(<QueryClientProvider client={client}><MemoryRouter initialEntries={['/classes/11']}>
    <Routes><Route path="/classes/:classId" element={<ClassPage />} /></Routes>
  </MemoryRouter></QueryClientProvider>)
}

describe('class header and teacher management', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
    auth.roles = ['school_admin']
    auth.orgId = 3
    vi.spyOn(schoolApi, 'classCourses').mockResolvedValue([])
    vi.spyOn(schoolApi, 'classAssignments').mockResolvedValue([])
    vi.spyOn(schoolApi, 'teachers').mockResolvedValue([{ id: 9, name: 'Active school teacher' }])
    vi.spyOn(schoolApi, 'updateClass').mockResolvedValue({ id: 11, name: '%th grade' })
  })

  it('assigns an active teacher from the class page with the existing partial payload', async () => {
    mount()
    await screen.findByRole('heading', { name: '%th grade' })
    fireEvent.click(screen.getByRole('button', { name: 'Assign teacher' }))
    const dialog = await screen.findByRole('dialog', { name: 'Assign teacher' })
    await within(dialog).findByRole('option', { name: 'Active school teacher' })
    fireEvent.change(within(dialog).getByLabelText('Teacher'), { target: { value: '9' } })
    fireEvent.click(within(dialog).getByRole('button', { name: 'Save teacher' }))
    await waitFor(() => expect(schoolApi.updateClass).toHaveBeenCalledWith(11, { teacher_user_id: 9 }))
    expect(schoolApi.teachers).toHaveBeenCalledWith(3)
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
    expect(screen.getByText('Existing learner')).toBeInTheDocument()
  })

  it('edits the class name and level without overwriting its teacher or roster', async () => {
    mount()
    await screen.findByRole('heading', { name: '%th grade' })
    fireEvent.click(screen.getByRole('button', { name: 'Edit class' }))
    const dialog = screen.getByRole('dialog', { name: 'Edit class' })
    expect(within(dialog).getByLabelText('Class name')).toHaveValue('%th grade')
    expect(within(dialog).getByLabelText('Level (optional)')).toHaveValue('L1')
    fireEvent.change(within(dialog).getByLabelText('Class name'), { target: { value: '5th grade' } })
    fireEvent.click(within(dialog).getByRole('button', { name: 'Save class' }))
    await waitFor(() => expect(schoolApi.updateClass).toHaveBeenCalledWith(11, { name: '5th grade', level: 'L1' }))
    expect(schoolApi.teachers).not.toHaveBeenCalled()
  })

  it('uses server capabilities so a class teacher can edit but cannot reassign teachers', async () => {
    auth.roles = ['parent', 'teacher']
    mount({ ...classroom, capabilities: { update: true, assign_teacher: false } })
    await screen.findByRole('heading', { name: '%th grade' })
    expect(screen.getByRole('button', { name: 'Edit class' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Assign teacher' })).not.toBeInTheDocument()
    expect(schoolApi.teachers).not.toHaveBeenCalled()
  })

  it('does not offer edit or assignment to a read-only viewer', async () => {
    auth.roles = ['supervisor']
    mount({ ...classroom, capabilities: { update: false, assign_teacher: false } })
    await screen.findByRole('heading', { name: '%th grade' })
    expect(screen.queryByRole('button', { name: 'Edit class' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Assign teacher' })).not.toBeInTheDocument()
  })

  it('shows a useful empty-teacher state instead of silently hiding assignment', async () => {
    vi.mocked(schoolApi.teachers).mockResolvedValue([])
    mount()
    await screen.findByRole('heading', { name: '%th grade' })
    fireEvent.click(screen.getByRole('button', { name: 'Assign teacher' }))
    expect(await screen.findByText(/no active teachers are linked to this school/i)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Save teacher' })).toBeDisabled()
    expect(schoolApi.updateClass).not.toHaveBeenCalled()
  })

  it('keeps the form open and surfaces assignment validation errors', async () => {
    vi.mocked(schoolApi.updateClass).mockRejectedValue(new ApiError('Teacher is no longer active.', 'validation', 422, { teacher_user_id: 'Choose an active teacher in this school.' }))
    mount()
    await screen.findByRole('heading', { name: '%th grade' })
    fireEvent.click(screen.getByRole('button', { name: 'Assign teacher' }))
    const dialog = screen.getByRole('dialog', { name: 'Assign teacher' })
    await within(dialog).findByRole('option', { name: 'Active school teacher' })
    fireEvent.change(within(dialog).getByLabelText('Teacher'), { target: { value: '9' } })
    fireEvent.click(within(dialog).getByRole('button', { name: 'Save teacher' }))
    expect(await screen.findByText('Choose an active teacher in this school.')).toBeInTheDocument()
    expect(screen.getByRole('dialog')).toBeInTheDocument()
  })
})
