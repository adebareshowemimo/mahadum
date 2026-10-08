import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useRef } from 'react'
import {
  ApiError,
  familyApi,
  type AddChildInput,
  type AssignmentDecision,
  type ChoreDecision,
  type CreateChoreInput,
} from '@/lib/api'
import { useAuth } from '@/lib/auth/AuthProvider'

export const familyKeys = {
  family: ['family'] as const,
  child: (learnerId: number) => ['family', 'child', learnerId] as const,
  wallet: ['wallet'] as const,
  chores: ['chores'] as const,
  reviews: ['reviews', 'pending'] as const,
  tasks: (learnerId: number) => ['tasks', learnerId] as const,
}

export function useLearnerTasks(learnerId: number | null) {
  return useQuery({ queryKey: familyKeys.tasks(learnerId ?? 0), queryFn: () => familyApi.tasks(learnerId as number), enabled: !!learnerId })
}

export function useSubmitTask(learnerId: number | null) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (input: { kind: 'chore' | 'assignment'; id: number; text?: string }) => input.kind === 'chore'
      ? familyApi.submitChore(input.id, learnerId as number)
      : familyApi.submitClassAssignment(input.id, learnerId as number, input.text ?? ''),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: familyKeys.tasks(learnerId ?? 0) })
      void qc.invalidateQueries({ queryKey: familyKeys.reviews })
      void qc.invalidateQueries({ queryKey: ['school-classes'] })
      void qc.invalidateQueries({ queryKey: ['class-assignments'] })
      void qc.invalidateQueries({ queryKey: ['class-completion'] })
    },
  })
}

export function useReviewClassAssignment() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ submissionId, decision }: { submissionId: number; decision: AssignmentDecision }) => familyApi.reviewClassAssignment(submissionId, decision),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: familyKeys.reviews })
      void qc.invalidateQueries({ queryKey: familyKeys.wallet })
      void qc.invalidateQueries({ queryKey: familyKeys.family })
      void qc.invalidateQueries({ queryKey: ['family', 'child'] })
      void qc.invalidateQueries({ queryKey: ['tasks'] })
      void qc.invalidateQueries({ queryKey: ['me'] })
    },
  })
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

export function useUpdateLearnerAvatar() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ learnerId, avatarId, photo }: { learnerId: number; avatarId?: number; photo?: File }) =>
      familyApi.updateLearnerAvatar(learnerId, { avatarId, photo }),
    onSuccess: (_, variables) => {
      void qc.invalidateQueries({ queryKey: familyKeys.family })
      void qc.invalidateQueries({ queryKey: familyKeys.child(variables.learnerId) })
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
      // The active learner's own coin_balance (shown in LearnPage's stats bar) comes from /me.
      void qc.invalidateQueries({ queryKey: ['me'] })
      void qc.invalidateQueries({ queryKey: ['family', 'child'] })
    },
  })
}

export function useFundWallet() {
  const { user } = useAuth()
  const attempts = useRef(new Map<string, string>())
  const fingerprint = (input: { amount: number; gateway: string }) => JSON.stringify([user?.user.id ?? null, input.amount, input.gateway])
  function clear(input: { amount: number; gateway: string }) {
    const attempt = fingerprint(input)
    attempts.current.delete(attempt)
    try { if (user) sessionStorage.removeItem(`mahadum.wallet-funding.${attempt}`) } catch { /* Storage may be disabled. */ }
  }
  return useMutation({
    mutationFn: (input: { amount: number; gateway: 'flutterwave' | 'monnify' | 'paystack' }) => {
      const attempt = fingerprint(input)
      if (!attempts.current.has(attempt)) {
        let previous: string | null = null
        try { if (user) previous = sessionStorage.getItem(`mahadum.wallet-funding.${attempt}`) } catch { /* Retain in-memory retry safety. */ }
        attempts.current.set(attempt, previous || crypto.randomUUID())
      }
      try { if (user) sessionStorage.setItem(`mahadum.wallet-funding.${attempt}`, attempts.current.get(attempt)!) } catch { /* Continue with the in-memory key. */ }
      return familyApi.fundWallet({ amount: input.amount, gateway: input.gateway }, attempts.current.get(attempt)!)
    },
    onSuccess: (_data, input) => clear(input),
    onError: (error, input) => {
      // A definite rejection can be corrected. Uncertain failures retain the
      // key so the API can replay a previously recorded successful checkout.
      if (error instanceof ApiError && error.status >= 400 && error.status < 500 && error.status !== 409) clear(input)
    },
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
      void qc.invalidateQueries({ queryKey: familyKeys.family })
      void qc.invalidateQueries({ queryKey: ['family', 'child'] })
      void qc.invalidateQueries({ queryKey: ['tasks'] })
      void qc.invalidateQueries({ queryKey: ['me'] })
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
      void qc.invalidateQueries({ queryKey: familyKeys.family })
      void qc.invalidateQueries({ queryKey: ['family', 'child'] })
      void qc.invalidateQueries({ queryKey: ['me'] })
    },
  })
}
