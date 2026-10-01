import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  referralApi,
  schoolApi,
  type RequestPayoutInput,
  type SendReferralInvitationInput,
} from '@/lib/api'
import { useAuth } from '@/lib/auth/AuthProvider'

export const referralKeys = {
  code: ['referral-code'] as const,
  summary: ['referral-summary'] as const,
  payouts: ['payouts'] as const,
  activations: (userId: number, organizationId: number | undefined, search: string, page: number) =>
    ['referral-activations', userId, organizationId ?? 'personal', search, page] as const,
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

export function useReferralActivations(search: string, page = 1, organizationId?: number) {
  const { user } = useAuth()
  const userId = user?.user.id ?? 0
  const params = { search: search || undefined, page }
  return useQuery({
    queryKey: referralKeys.activations(userId, organizationId, search, page),
    queryFn: () => organizationId ? schoolApi.referralActivations(organizationId, params) : referralApi.activations(params),
    enabled: userId > 0,
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
