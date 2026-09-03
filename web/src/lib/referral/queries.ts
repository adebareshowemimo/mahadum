import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  referralApi,
  type RequestPayoutInput,
  type SendReferralInvitationInput,
} from '@/lib/api'

export const referralKeys = {
  code: ['referral-code'] as const,
  summary: ['referral-summary'] as const,
  payouts: ['payouts'] as const,
  activations: (search: string) => ['referral-activations', search] as const,
  invitations: ['referral-invitations'] as const,
}

export function useReferralCode() {
  return useQuery({ queryKey: referralKeys.code, queryFn: referralApi.code })
}

export function useReferralSummary() {
  return useQuery({ queryKey: referralKeys.summary, queryFn: referralApi.summary })
}

export function usePayouts() {
  return useQuery({ queryKey: referralKeys.payouts, queryFn: referralApi.payouts })
}

export function useReferralActivations(search: string) {
  return useQuery({
    queryKey: referralKeys.activations(search),
    queryFn: () => referralApi.activations({ search: search || undefined }),
  })
}

export function useReferralInvitations() {
  return useQuery({ queryKey: referralKeys.invitations, queryFn: referralApi.invitations })
}

export function useSendInvitation() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (input: SendReferralInvitationInput) => referralApi.sendInvitation(input),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: referralKeys.invitations })
      void qc.invalidateQueries({ queryKey: ['referral-activations'] })
    },
  })
}

export function useRequestPayout() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (input: RequestPayoutInput) => referralApi.requestPayout(input),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: referralKeys.payouts })
      void qc.invalidateQueries({ queryKey: referralKeys.summary })
    },
  })
}
