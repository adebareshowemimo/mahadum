import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { gamificationApi } from '@/lib/api'

export const gamificationKeys = {
  streak: (id: number) => ['streak', id] as const,
  hearts: (id: number) => ['hearts', id] as const,
  badges: (id: number) => ['badges', id] as const,
  league: (id: number) => ['league-current', id] as const,
  leaderboard: (id: number) => ['leaderboard', id] as const,
}

export function useStreak(learnerId: number | null | undefined) {
  return useQuery({
    queryKey: gamificationKeys.streak(learnerId ?? 0),
    queryFn: () => gamificationApi.streak(learnerId as number),
    enabled: !!learnerId,
  })
}

export function useHearts(learnerId: number | null | undefined) {
  return useQuery({
    queryKey: gamificationKeys.hearts(learnerId ?? 0),
    queryFn: () => gamificationApi.hearts(learnerId as number),
    enabled: !!learnerId,
  })
}

export function useBadges(learnerId: number | null | undefined) {
  return useQuery({
    queryKey: gamificationKeys.badges(learnerId ?? 0),
    queryFn: () => gamificationApi.badges(learnerId as number),
    enabled: !!learnerId,
  })
}

export function useLeagueCurrent(learnerId: number | null | undefined) {
  return useQuery({
    queryKey: gamificationKeys.league(learnerId ?? 0),
    queryFn: () => gamificationApi.leagueCurrent(learnerId as number),
    enabled: !!learnerId,
  })
}

export function useLeaderboard(learnerId: number | null | undefined) {
  return useQuery({
    queryKey: gamificationKeys.leaderboard(learnerId ?? 0),
    queryFn: () => gamificationApi.leaderboard(learnerId as number),
    enabled: !!learnerId,
  })
}

export function useArmShield(learnerId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: () => gamificationApi.armShield(learnerId),
    onSuccess: () => qc.invalidateQueries({ queryKey: gamificationKeys.streak(learnerId) }),
  })
}

export function useRefillHearts(learnerId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (method: 'ad' | 'coins') => gamificationApi.refillHearts(learnerId, method),
    onSuccess: () => qc.invalidateQueries({ queryKey: gamificationKeys.hearts(learnerId) }),
  })
}
