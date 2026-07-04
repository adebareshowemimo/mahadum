import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react'
import { useAuth } from '@/lib/auth/AuthProvider'
import type { LearnerProfile } from '@/lib/api'

const STORAGE_KEY = 'mahadum.active_learner'

interface ActiveProfileValue {
  /** All learner profiles across the signed-in user's families. */
  learners: LearnerProfile[]
  activeLearnerId: number | null
  activeLearner: LearnerProfile | null
  setActiveLearner: (id: number | null) => void
}

const ActiveProfileContext = createContext<ActiveProfileValue | null>(null)

export function ActiveProfileProvider({ children }: { children: ReactNode }) {
  const { user, status } = useAuth()
  const [activeLearnerId, setId] = useState<number | null>(() => {
    const raw = localStorage.getItem(STORAGE_KEY)
    return raw ? Number(raw) : null
  })

  const learners = useMemo(
    () => (user?.families ?? []).flatMap((f) => f.learners ?? []),
    [user],
  )

  const setActiveLearner = useCallback((id: number | null) => {
    setId(id)
    if (id == null) localStorage.removeItem(STORAGE_KEY)
    else localStorage.setItem(STORAGE_KEY, String(id))
  }, [])

  // Drop the active profile on sign-out.
  useEffect(() => {
    if (status === 'unauthenticated' && activeLearnerId != null) setActiveLearner(null)
  }, [status, activeLearnerId, setActiveLearner])

  // Once /me loads, clear a stale id that no longer maps to a known learner.
  useEffect(() => {
    if (user && activeLearnerId != null && !learners.some((l) => l.id === activeLearnerId)) {
      setActiveLearner(null)
    }
  }, [user, learners, activeLearnerId, setActiveLearner])

  const value = useMemo<ActiveProfileValue>(
    () => ({
      learners,
      activeLearnerId,
      activeLearner: learners.find((l) => l.id === activeLearnerId) ?? null,
      setActiveLearner,
    }),
    [learners, activeLearnerId, setActiveLearner],
  )

  return <ActiveProfileContext.Provider value={value}>{children}</ActiveProfileContext.Provider>
}

export function useActiveProfile(): ActiveProfileValue {
  const ctx = useContext(ActiveProfileContext)
  if (!ctx) throw new Error('useActiveProfile must be used within an <ActiveProfileProvider>')
  return ctx
}
