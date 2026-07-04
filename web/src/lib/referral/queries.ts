import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { referralApi, type RequestPayoutInput } from '@/lib/api'

export const referralKeys = {
  code: ['referral-code'] as const,
  summary: ['referral-summary'] as const,
  payouts: ['payouts'] as const,
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
