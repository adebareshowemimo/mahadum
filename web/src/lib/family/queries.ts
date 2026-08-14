import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  familyApi,
  type AddChildInput,
  type AssignmentDecision,
  type ChoreDecision,
  type CreateChoreInput,
} from '@/lib/api'

export const familyKeys = {
  family: ['family'] as const,
  child: (learnerId: number) => ['family', 'child', learnerId] as const,
  wallet: ['wallet'] as const,
  chores: ['chores'] as const,
  reviews: ['reviews', 'pending'] as const,
}

export function useFamily(enabled = true) {
  return useQuery({ queryKey: familyKeys.family, queryFn: familyApi.overview, enabled })
}

export function useChild(learnerId: number | null | undefined) {
  return useQuery({
    queryKey: familyKeys.child(learnerId ?? 0),
    queryFn: () => familyApi.child(learnerId as number),
    enabled: !!learnerId,
  })
}

export function useWallet() {
  return useQuery({ queryKey: familyKeys.wallet, queryFn: familyApi.wallet })
}

export function useChores() {
  return useQuery({ queryKey: familyKeys.chores, queryFn: familyApi.chores })
}

export function usePendingReviews() {
  return useQuery({ queryKey: familyKeys.reviews, queryFn: familyApi.pendingReviews })
}

export function useAddChild() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (input: AddChildInput) => familyApi.addChild(input),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: familyKeys.family })
      // New learner profile should appear in the topbar profile switcher.
      void qc.invalidateQueries({ queryKey: ['me'] })
      void qc.invalidateQueries({ queryKey: ['family', 'child'] })
    },
  })
}

export function useSetChildPin() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ learnerId, pin }: { learnerId: number; pin: string | null }) =>
      familyApi.setChildPin(learnerId, pin),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: familyKeys.family })
      // The profile switcher's shield icon reads this via /me.
      void qc.invalidateQueries({ queryKey: ['me'] })
      void qc.invalidateQueries({ queryKey: ['family', 'child'] })
    },
  })
}

export function useTransfer() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (input: { to_learner_id: number; coins: number }) => familyApi.transfer(input),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: familyKeys.wallet })
      void qc.invalidateQueries({ queryKey: familyKeys.family })
      // The active learner's own coin_balance (shown in LearnPage's stats bar) comes from /me.
      void qc.invalidateQueries({ queryKey: ['me'] })
      void qc.invalidateQueries({ queryKey: ['family', 'child'] })
    },
  })
}

export function useFundWallet() {
  return useMutation({
    mutationFn: (input: { amount: number; gateway: 'flutterwave' | 'monnify' | 'paystack' }) =>
      familyApi.fundWallet(input),
  })
}

export function useCreateChore() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (input: CreateChoreInput) => familyApi.createChore(input),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: familyKeys.chores })
      void qc.invalidateQueries({ queryKey: familyKeys.reviews })
    },
  })
}

export function useReviewChore() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ choreId, decision }: { choreId: number; decision: ChoreDecision }) =>
      familyApi.reviewChore(choreId, decision),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: familyKeys.reviews })
      void qc.invalidateQueries({ queryKey: familyKeys.chores })
      void qc.invalidateQueries({ queryKey: familyKeys.wallet })
    },
  })
}

export function useReviewAssignment() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ submissionId, decision }: { submissionId: number; decision: AssignmentDecision }) =>
      familyApi.reviewAssignment(submissionId, decision),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: familyKeys.reviews })
      void qc.invalidateQueries({ queryKey: familyKeys.wallet })
    },
  })
}
