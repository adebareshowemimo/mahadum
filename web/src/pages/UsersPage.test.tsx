import { render, screen, waitFor, within } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { UsersPage } from './UsersPage'
import { ApiError, type AdminUserRow } from '@/lib/api'

const { useAdminUsersMock, assignMutate, setStatusMutate } = vi.hoisted(() => ({
  useAdminUsersMock: vi.fn(),
  assignMutate: vi.fn(),
  setStatusMutate: vi.fn(),
}))

vi.mock('@/lib/admin/queries', () => ({
  useAdminUsers: useAdminUsersMock,
  useAssignUserRole: () => ({ mutateAsync: assignMutate, isPending: false }),
  useSetUserStatus: () => ({ mutateAsync: setStatusMutate, isPending: false }),
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

function renderPage() {
  return render(
    <MemoryRouter>
      <UsersPage />
    </MemoryRouter>,
  )
}

function mockUsers(rows: AdminUserRow[]) {
  useAdminUsersMock.mockReturnValue({
    data: {
      data: rows,
      meta: { current_page: 1, last_page: 1, per_page: 20, total: rows.length },
    },
    isLoading: false,
    isError: false,
    isFetching: false,
  })
}

async function openUserModal() {
  const { fireEvent } = await import('@testing-library/react')
  fireEvent.click(screen.getByText('Ada Obi'))
  return screen.getByRole('dialog')
}

describe('UsersPage', () => {
  beforeEach(() => {
    useAdminUsersMock.mockReset()
    assignMutate.mockReset()
    setStatusMutate.mockReset()
  })

  it('surfaces a load error', () => {
    useAdminUsersMock.mockReturnValue({ data: undefined, isLoading: false, isError: true, isFetching: false })
    renderPage()
    expect(screen.getByText(/couldn’t load users/i)).toBeInTheDocument()
  })

  it('lists users and opens the detail modal on row click', async () => {
    mockUsers([USER])
    renderPage()
    const dialog = await openUserModal()
    expect(within(dialog).getByText('ada@example.com')).toBeInTheDocument()
  })

  it('shows org memberships in the row and the modal', async () => {
    const { fireEvent } = await import('@testing-library/react')
    const member: AdminUserRow = {
      ...USER,
      roles: ['school_admin'],
      organizations: [{ id: 3, name: 'Sunrise Academy', role: 'school_admin', status: 'active' }],
    }
    mockUsers([member])
    renderPage()
    // Column badge (appears in the row before the modal opens).
    expect(screen.getAllByText('Sunrise Academy').length).toBeGreaterThan(0)
    fireEvent.click(screen.getByText('Ada Obi'))
    const dialog = screen.getByRole('dialog')
    expect(within(dialog).getByText('Sunrise Academy')).toBeInTheDocument()
  })

  it('grants a role and reflects the returned roles', async () => {
    const { fireEvent } = await import('@testing-library/react')
    mockUsers([USER])
    assignMutate.mockResolvedValue({ id: 7, roles: ['parent', 'teacher'], status: 'active' })
    renderPage()
    const dialog = await openUserModal()

    fireEvent.click(within(dialog).getByRole('button', { name: /\+ teacher/i }))

    await waitFor(() =>
      expect(assignMutate).toHaveBeenCalledWith({ userId: 7, input: { role: 'teacher', action: 'assign' } }),
    )
    // The toggle now shows teacher as granted.
    await waitFor(() => expect(within(dialog).getByRole('button', { name: /✓ teacher/i })).toBeInTheDocument())
  })

  it('surfaces the self-lockout 422 when revoking own super_admin', async () => {
    const { fireEvent } = await import('@testing-library/react')
    const admin: AdminUserRow = { ...USER, roles: ['super_admin'] }
    mockUsers([admin])
    assignMutate.mockRejectedValue(
      new ApiError('You cannot revoke your own super_admin role.', 'validation', 422),
    )
    renderPage()
    const dialog = await openUserModal()

    fireEvent.click(within(dialog).getByRole('button', { name: /✓ super_admin/i }))

    await waitFor(() =>
      expect(within(dialog).getByText(/cannot revoke your own super_admin/i)).toBeInTheDocument(),
    )
  })

  it('suspends an active account', async () => {
    const { fireEvent } = await import('@testing-library/react')
    mockUsers([USER])
    setStatusMutate.mockResolvedValue({ id: 7, status: 'suspended' })
    renderPage()
    const dialog = await openUserModal()

    fireEvent.click(within(dialog).getByRole('button', { name: /suspend account/i }))

    await waitFor(() => expect(setStatusMutate).toHaveBeenCalledWith({ userId: 7, status: 'suspended' }))
    // After suspension the modal offers reactivation instead.
    await waitFor(() =>
      expect(within(dialog).getByRole('button', { name: /reactivate account/i })).toBeInTheDocument(),
    )
  })

  it('surfaces a self-suspension 422', async () => {
    const { fireEvent } = await import('@testing-library/react')
    mockUsers([USER])
    setStatusMutate.mockRejectedValue(new ApiError('You cannot suspend your own account.', 'validation', 422))
    renderPage()
    const dialog = await openUserModal()

    fireEvent.click(within(dialog).getByRole('button', { name: /suspend account/i }))

    await waitFor(() =>
      expect(within(dialog).getByText(/cannot suspend your own account/i)).toBeInTheDocument(),
    )
  })
})
