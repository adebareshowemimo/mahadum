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
  wallet: ['wallet'] as const,
  chores: ['chores'] as const,
  reviews: ['reviews', 'pending'] as const,
}

export function useFamily() {
  return useQuery({ queryKey: familyKeys.family, queryFn: familyApi.overview })
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
    },
  })
}

export function useSetPin() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (pin: string) => familyApi.setPin(pin),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: familyKeys.family })
      void qc.invalidateQueries({ queryKey: ['me'] })
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
