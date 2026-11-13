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
  detail: (slug: string) => ['competition', slug] as const,
  mine: ['competitions-mine'] as const,
  admin: ['admin-competitions'] as const,
  adminDetail: (id: number) => ['admin-competition', id] as const,
}

export function useCompetitions() {
  return useQuery({ queryKey: competitionKeys.list, queryFn: competitionApi.list })
}

export function useCompetition(slug: string | null) {
  return useQuery({
    queryKey: competitionKeys.detail(slug ?? ''),
    queryFn: () => competitionApi.show(slug as string),
    enabled: !!slug,
  })
}

export function useMyEntries(enabled = true) {
  return useQuery({ queryKey: competitionKeys.mine, queryFn: competitionApi.mine, enabled })
}

export function useApproveEntryAsGuardian() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ competitionId, entryId }: { competitionId: number; entryId: number }) => competitionApi.approveEntryAsGuardian(competitionId, entryId),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: competitionKeys.mine })
      void qc.invalidateQueries({ queryKey: ['competition'] })
    },
  })
}

export function useVote(competitionId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (entryId: number) => competitionApi.vote(competitionId, entryId),
    onSuccess: () => void qc.invalidateQueries({ queryKey: ['competition'] }),
  })
}

export function useSubmitEntry(competitionId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (input: SubmitEntryInput) => competitionApi.submitEntry(competitionId, input),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['competition'] })
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

export function useUpdateCompetition() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, input }: { id: number; input: Partial<CreateCompetitionInput> }) => adminApi.updateCompetition(id, input),
    onSuccess: (_data, { id }) => {
      void qc.invalidateQueries({ queryKey: competitionKeys.admin })
      void qc.invalidateQueries({ queryKey: competitionKeys.adminDetail(id) })
      void qc.invalidateQueries({ queryKey: competitionKeys.list })
      void qc.invalidateQueries({ queryKey: ['competition'] })
    },
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
