import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { MemoryRouter, Route, Routes, useLocation } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { UsersPage } from './UsersPage'
import { UserDetailPage } from './UserDetailPage'
import { ApiError, type AdminUserRow } from '@/lib/api'

const { useAdminUsersMock, useAdminUserMock, assignMutate, setStatusMutate, createMutate } = vi.hoisted(() => ({
  useAdminUsersMock: vi.fn(),
  useAdminUserMock: vi.fn(),
  assignMutate: vi.fn(),
  setStatusMutate: vi.fn(),
  createMutate: vi.fn(),
}))

vi.mock('@/lib/admin/queries', () => ({
  useAdminUsers: useAdminUsersMock,
  useAdminUser: useAdminUserMock,
  useAssignUserRole: () => ({ mutateAsync: assignMutate, isPending: false }),
  useSetUserStatus: () => ({ mutateAsync: setStatusMutate, isPending: false }),
  useCreateAdminUser: () => ({ mutateAsync: createMutate, isPending: false }),
  useAdminOrganizations: () => ({ data: { data: [{ id: 3, name: 'Sunrise Academy' }] } }),
}))

const USER: AdminUserRow = {
  id: 7,
  name: 'Ada Obi',
  email: 'ada@example.com',
  phone: null,
  status: 'active',
  roles: ['parent'],
  email_verified: true,
  created_at: null,
  last_login_at: null,
  organizations: [],
}

function LocationProbe() {
  return <span data-testid="location">{useLocation().pathname}</span>
}

function mockUsers(rows: AdminUserRow[]) {
  useAdminUsersMock.mockReturnValue({
    data: { data: rows, meta: { current_page: 1, last_page: 1, per_page: 20, total: rows.length } },
    isLoading: false,
    isError: false,
    isFetching: false,
  })
}

function renderList() {
  return render(<MemoryRouter><UsersPage /><LocationProbe /></MemoryRouter>)
}

function renderDetail(user: AdminUserRow = USER) {
  useAdminUserMock.mockReturnValue({ data: user, isLoading: false, isError: false })
  return render(
    <MemoryRouter initialEntries={['/admin/users/7']}>
      <Routes><Route path="/admin/users/:userId" element={<UserDetailPage />} /></Routes>
    </MemoryRouter>,
  )
}

describe('UsersPage and UserDetailPage', () => {
  beforeEach(() => {
    useAdminUsersMock.mockReset()
    useAdminUserMock.mockReset()
    assignMutate.mockReset()
    setStatusMutate.mockReset()
    createMutate.mockReset()
  })

  it('surfaces a directory load error', () => {
    useAdminUsersMock.mockReturnValue({ data: undefined, isLoading: false, isError: true, isFetching: false })
    renderList()
    expect(screen.getByText(/couldn’t load users/i)).toBeInTheDocument()
  })

  it('navigates a selected row to its bookmarkable detail page', () => {
    mockUsers([USER])
    renderList()
    fireEvent.click(screen.getByText('Ada Obi'))
    expect(screen.getByTestId('location')).toHaveTextContent('/admin/users/7')
  })

  it('creates and securely invites an organization user', async () => {
    mockUsers([])
    createMutate.mockResolvedValue({ id: 8 })
    renderList()
    fireEvent.click(screen.getByRole('button', { name: /create user/i }))
    const dialog = screen.getByRole('dialog')
    fireEvent.change(within(dialog).getByLabelText('First name'), { target: { value: 'Tola' } })
    fireEvent.change(within(dialog).getByLabelText('Last name'), { target: { value: 'Teacher' } })
    fireEvent.change(within(dialog).getByLabelText('Email'), { target: { value: 'tola@example.test' } })
    fireEvent.change(within(dialog).getByLabelText('Role'), { target: { value: 'teacher' } })
    fireEvent.change(within(dialog).getByLabelText('Organization'), { target: { value: '3' } })
    fireEvent.click(within(dialog).getByRole('button', { name: /create and invite/i }))

    await waitFor(() => expect(createMutate).toHaveBeenCalledWith(expect.objectContaining({
      first_name: 'Tola',
      last_name: 'Teacher',
      email: 'tola@example.test',
      role: 'teacher',
      organization_id: 3,
    })))
  })

  it('shows organization membership on the detail page', () => {
    renderDetail({ ...USER, roles: ['school_admin'], organizations: [{ id: 3, name: 'Sunrise Academy', role: 'school_admin', status: 'active' }] })
    expect(screen.getByText('Sunrise Academy')).toBeInTheDocument()
    expect(screen.getByText('ada@example.com')).toBeInTheDocument()
  })

  it('grants a platform role', async () => {
    renderDetail()
    assignMutate.mockResolvedValue({ ...USER, roles: ['parent', 'teacher'] })
    fireEvent.click(screen.getByRole('button', { name: /\+ teacher/i }))
    await waitFor(() => expect(assignMutate).toHaveBeenCalledWith({ userId: 7, input: { role: 'teacher', action: 'assign' } }))
  })

  it('surfaces the self-lockout response', async () => {
    renderDetail({ ...USER, roles: ['super_admin'] })
    assignMutate.mockRejectedValue(new ApiError('You cannot revoke your own super_admin role.', 'validation', 422))
    fireEvent.click(screen.getByRole('button', { name: /✓ super_admin/i }))
    await waitFor(() => expect(screen.getByText(/cannot revoke your own super_admin/i)).toBeInTheDocument())
  })

  it('suspends an active account', async () => {
    renderDetail()
    setStatusMutate.mockResolvedValue({ ...USER, status: 'suspended' })
    fireEvent.click(screen.getByRole('button', { name: /suspend account/i }))
    await waitFor(() => expect(setStatusMutate).toHaveBeenCalledWith({ userId: 7, status: 'suspended' }))
  })

  it('surfaces a self-suspension response', async () => {
    renderDetail()
    setStatusMutate.mockRejectedValue(new ApiError('You cannot suspend your own account.', 'validation', 422))
    fireEvent.click(screen.getByRole('button', { name: /suspend account/i }))
    await waitFor(() => expect(screen.getByText(/cannot suspend your own account/i)).toBeInTheDocument())
  })
})
