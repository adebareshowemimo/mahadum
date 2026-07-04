import { render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { AdminRoute, GuestRoute, ProtectedRoute, RoleRoute, TeacherRoute } from './ProtectedRoute'
import type { AuthStatus } from '@/lib/auth/AuthProvider'
import type { Role } from '@/lib/api'

const { useAuthMock } = vi.hoisted(() => ({ useAuthMock: vi.fn() }))
vi.mock('@/lib/auth/AuthProvider', () => ({ useAuth: useAuthMock }))

function setStatus(status: AuthStatus) {
  useAuthMock.mockReturnValue({ status })
}

/** Authenticate as a user holding exactly `roles` (drives `hasRole`). */
function setRoles(roles: Role[], status: AuthStatus = 'authenticated') {
  useAuthMock.mockReturnValue({
    status,
    hasRole: (...wanted: Role[]) => wanted.some((r) => roles.includes(r)),
  })
}

describe('ProtectedRoute', () => {
  beforeEach(() => useAuthMock.mockReset())

  function renderProtected() {
    return render(
      <MemoryRouter initialEntries={['/']}>
        <Routes>
          <Route element={<ProtectedRoute />}>
            <Route path="/" element={<div>PROTECTED</div>} />
          </Route>
          <Route path="/login" element={<div>LOGIN</div>} />
        </Routes>
      </MemoryRouter>,
    )
  }

  it('renders the protected content when authenticated', () => {
    setStatus('authenticated')
    renderProtected()
    expect(screen.getByText('PROTECTED')).toBeInTheDocument()
  })

  it('redirects to /login when unauthenticated', () => {
    setStatus('unauthenticated')
    renderProtected()
    expect(screen.getByText('LOGIN')).toBeInTheDocument()
    expect(screen.queryByText('PROTECTED')).not.toBeInTheDocument()
  })

  it('shows neither while the session is loading', () => {
    setStatus('loading')
    renderProtected()
    expect(screen.queryByText('PROTECTED')).not.toBeInTheDocument()
    expect(screen.queryByText('LOGIN')).not.toBeInTheDocument()
  })
})

describe('RoleRoute', () => {
  beforeEach(() => useAuthMock.mockReset())

  function renderRole(roles: Role[]) {
    return render(
      <MemoryRouter initialEntries={['/admin']}>
        <Routes>
          <Route element={<RoleRoute roles={roles} />}>
            <Route path="/admin" element={<div>ADMIN</div>} />
          </Route>
          <Route path="/home" element={<div>HOME</div>} />
          <Route path="/login" element={<div>LOGIN</div>} />
        </Routes>
      </MemoryRouter>,
    )
  }

  it('renders the guarded content when the user holds an allowed role', () => {
    setRoles(['super_admin'])
    renderRole(['super_admin'])
    expect(screen.getByText('ADMIN')).toBeInTheDocument()
  })

  it('renders when the user holds one of several allowed roles', () => {
    setRoles(['teacher'])
    renderRole(['school_admin', 'teacher'])
    expect(screen.getByText('ADMIN')).toBeInTheDocument()
  })

  it('redirects to /home when the user lacks every allowed role', () => {
    setRoles(['parent'])
    renderRole(['super_admin'])
    expect(screen.getByText('HOME')).toBeInTheDocument()
    expect(screen.queryByText('ADMIN')).not.toBeInTheDocument()
  })

  it('bounces to /login when unauthenticated', () => {
    setRoles([], 'unauthenticated')
    renderRole(['super_admin'])
    expect(screen.getByText('LOGIN')).toBeInTheDocument()
    expect(screen.queryByText('ADMIN')).not.toBeInTheDocument()
  })

  it('shows neither content nor a redirect while the session is loading', () => {
    setRoles([], 'loading')
    renderRole(['super_admin'])
    expect(screen.queryByText('ADMIN')).not.toBeInTheDocument()
    expect(screen.queryByText('HOME')).not.toBeInTheDocument()
    expect(screen.queryByText('LOGIN')).not.toBeInTheDocument()
  })
})

describe('AdminRoute', () => {
  beforeEach(() => useAuthMock.mockReset())

  function renderAdmin() {
    return render(
      <MemoryRouter initialEntries={['/admin']}>
        <Routes>
          <Route element={<AdminRoute />}>
            <Route path="/admin" element={<div>ADMIN</div>} />
          </Route>
          <Route path="/home" element={<div>HOME</div>} />
        </Routes>
      </MemoryRouter>,
    )
  }

  it('admits a super_admin', () => {
    setRoles(['super_admin'])
    renderAdmin()
    expect(screen.getByText('ADMIN')).toBeInTheDocument()
  })

  it.each<Role>(['parent', 'student', 'teacher', 'school_admin', 'supervisor', 'content_owner'])(
    'redirects a %s to /home',
    (role) => {
      setRoles([role])
      renderAdmin()
      expect(screen.getByText('HOME')).toBeInTheDocument()
      expect(screen.queryByText('ADMIN')).not.toBeInTheDocument()
    },
  )
})

describe('TeacherRoute', () => {
  beforeEach(() => useAuthMock.mockReset())

  function renderTeacher() {
    return render(
      <MemoryRouter initialEntries={['/classes']}>
        <Routes>
          <Route element={<TeacherRoute />}>
            <Route path="/classes" element={<div>CLASSES</div>} />
          </Route>
          <Route path="/home" element={<div>HOME</div>} />
        </Routes>
      </MemoryRouter>,
    )
  }

  it('admits a teacher', () => {
    setRoles(['teacher'])
    renderTeacher()
    expect(screen.getByText('CLASSES')).toBeInTheDocument()
  })

  it.each<Role>(['parent', 'student', 'super_admin', 'school_admin', 'supervisor', 'content_owner'])(
    'redirects a %s to /home',
    (role) => {
      setRoles([role])
      renderTeacher()
      expect(screen.getByText('HOME')).toBeInTheDocument()
      expect(screen.queryByText('CLASSES')).not.toBeInTheDocument()
    },
  )
})

describe('GuestRoute', () => {
  beforeEach(() => useAuthMock.mockReset())

  function renderGuest() {
    return render(
      <MemoryRouter initialEntries={['/login']}>
        <Routes>
          <Route element={<GuestRoute />}>
            <Route path="/login" element={<div>LOGIN</div>} />
          </Route>
          <Route path="/home" element={<div>HOME</div>} />
        </Routes>
      </MemoryRouter>,
    )
  }

  it('shows the guest page when unauthenticated', () => {
    setStatus('unauthenticated')
    renderGuest()
    expect(screen.getByText('LOGIN')).toBeInTheDocument()
  })

  it('redirects authenticated users to /home', () => {
    setStatus('authenticated')
    renderGuest()
    expect(screen.getByText('HOME')).toBeInTheDocument()
    expect(screen.queryByText('LOGIN')).not.toBeInTheDocument()
  })
})
