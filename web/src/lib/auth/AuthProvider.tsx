import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import {
  authApi,
  orgStore,
  setUnauthorizedHandler,
  tokenStore,
  type AuthSession,
  type LoginInput,
  type Me,
  type RegisterInput,
  type Role,
} from '@/lib/api'

export type AuthStatus = 'loading' | 'authenticated' | 'unauthenticated'

interface AuthContextValue {
  status: AuthStatus
  user: Me | null
  /** Convenience accessor for the active organization (tenant) id. */
  activeOrgId: number | null
  login: (input: LoginInput) => Promise<void>
  register: (input: RegisterInput) => Promise<void>
  loginWithGoogle: (idToken: string) => Promise<void>
  logout: () => Promise<void>
  setActiveOrg: (id: number | null) => void
  /** True if the user holds any of the given roles. */
  hasRole: (...roles: Role[]) => boolean
  /** Force a refetch of /me (e.g. after profile changes). */
  refresh: () => Promise<void>
}

const AuthContext = createContext<AuthContextValue | null>(null)

const ME_KEY = ['me'] as const

export function AuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient()
  // Token presence drives whether we attempt to load the session at all.
  const [hasToken, setHasToken] = useState<boolean>(() => tokenStore.get() != null)

  const meQuery = useQuery({
    queryKey: ME_KEY,
    queryFn: authApi.me,
    enabled: hasToken,
    staleTime: 60_000,
    retry: false,
  })

  // When a session is established, persist the token + abilities and load /me.
  const adoptSession = useCallback(
    async (session: AuthSession) => {
      tokenStore.set(session.token)
      if (session.user.active_organization_id != null) {
        orgStore.set(session.user.active_organization_id)
      }
      setHasToken(true)
      // Seed the cache from the lighter token payload, then fetch the full /me.
      await queryClient.invalidateQueries({ queryKey: ME_KEY })
    },
    [queryClient],
  )

  const login = useCallback(
    async (input: LoginInput) => adoptSession(await authApi.login(input)),
    [adoptSession],
  )
  const register = useCallback(
    async (input: RegisterInput) => adoptSession(await authApi.register(input)),
    [adoptSession],
  )
  const loginWithGoogle = useCallback(
    async (idToken: string) => adoptSession(await authApi.google(idToken)),
    [adoptSession],
  )

  const clearSession = useCallback(() => {
    tokenStore.clear()
    orgStore.set(null)
    setHasToken(false)
    queryClient.removeQueries({ queryKey: ME_KEY })
  }, [queryClient])

  const logout = useCallback(async () => {
    try {
      await authApi.logout()
    } catch {
      // Even if the revoke call fails (offline / already-expired token), drop
      // the local session — the user asked to leave.
    } finally {
      clearSession()
    }
  }, [clearSession])

  // Reflect a forced 401 (token rejected by the server) into auth state.
  useEffect(() => {
    setUnauthorizedHandler(() => {
      setHasToken(false)
      queryClient.removeQueries({ queryKey: ME_KEY })
    })
    return () => setUnauthorizedHandler(null)
  }, [queryClient])

  const setActiveOrg = useCallback(
    (id: number | null) => {
      orgStore.set(id)
      void queryClient.invalidateQueries({ queryKey: ME_KEY })
    },
    [queryClient],
  )

  const user = meQuery.data ?? null

  const status: AuthStatus = !hasToken
    ? 'unauthenticated'
    : meQuery.isSuccess
      ? 'authenticated'
      : meQuery.isError
        ? 'unauthenticated'
        : 'loading'

  const hasRole = useCallback(
    (...roles: Role[]) => !!user && roles.some((r) => user.user.roles.includes(r)),
    [user],
  )

  const refresh = useCallback(async () => {
    await meQuery.refetch()
  }, [meQuery])

  const value = useMemo<AuthContextValue>(
    () => ({
      status,
      user,
      activeOrgId: user?.active_organization_id ?? null,
      login,
      register,
      loginWithGoogle,
      logout,
      setActiveOrg,
      hasRole,
      refresh,
    }),
    [status, user, login, register, loginWithGoogle, logout, setActiveOrg, hasRole, refresh],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used within an <AuthProvider>')
  return ctx
}
