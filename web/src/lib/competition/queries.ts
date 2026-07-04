import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  adminApi,
  competitionApi,
  type CompetitionStatus,
  type CreateCompetitionInput,
  type EntryModeration,
  type SubmitEntryInput,
} from '@/lib/api'

export const competitionKeys = {
  list: ['competitions'] as const,
  detail: (id: number) => ['competition', id] as const,
  mine: ['competitions-mine'] as const,
  admin: ['admin-competitions'] as const,
  adminDetail: (id: number) => ['admin-competition', id] as const,
}

export function useCompetitions() {
  return useQuery({ queryKey: competitionKeys.list, queryFn: competitionApi.list })
}

export function useCompetition(id: number | null) {
  return useQuery({
    queryKey: competitionKeys.detail(id ?? 0),
    queryFn: () => competitionApi.show(id as number),
    enabled: !!id,
  })
}

export function useMyEntries() {
  return useQuery({ queryKey: competitionKeys.mine, queryFn: competitionApi.mine })
}

export function useVote(competitionId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (entryId: number) => competitionApi.vote(competitionId, entryId),
    onSuccess: () => void qc.invalidateQueries({ queryKey: competitionKeys.detail(competitionId) }),
  })
}

export function useSubmitEntry(competitionId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (input: SubmitEntryInput) => competitionApi.submitEntry(competitionId, input),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: competitionKeys.detail(competitionId) })
      void qc.invalidateQueries({ queryKey: competitionKeys.mine })
    },
  })
}

/* ── Organiser (super_admin) ── */

export function useAdminCompetitions() {
  return useQuery({ queryKey: competitionKeys.admin, queryFn: adminApi.competitions })
}

export function useCreateCompetition() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (input: CreateCompetitionInput) => adminApi.createCompetition(input),
    onSuccess: () => void qc.invalidateQueries({ queryKey: competitionKeys.admin }),
  })
}

export function useAdminCompetition(id: number | null) {
  return useQuery({
    queryKey: competitionKeys.adminDetail(id ?? 0),
    queryFn: () => adminApi.competition(id as number),
    enabled: !!id,
  })
}

export function useSetCompetitionStatus() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, status }: { id: number; status: CompetitionStatus }) => adminApi.setCompetitionStatus(id, status),
    onSuccess: (_data, { id }) => {
      void qc.invalidateQueries({ queryKey: competitionKeys.admin })
      void qc.invalidateQueries({ queryKey: competitionKeys.list })
      void qc.invalidateQueries({ queryKey: competitionKeys.adminDetail(id) })
    },
  })
}

export function useModerateEntry(competitionId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ entryId, status }: { entryId: number; status: EntryModeration }) =>
      adminApi.moderateEntry(competitionId, entryId, status),
    onSuccess: () => void qc.invalidateQueries({ queryKey: competitionKeys.adminDetail(competitionId) }),
  })
}

export function useJudgeCompetition(competitionId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (awards: { entry_id: number; rank: number }[]) => adminApi.judgeCompetition(competitionId, awards),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: competitionKeys.admin })
      void qc.invalidateQueries({ queryKey: competitionKeys.adminDetail(competitionId) })
    },
  })
}
