import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useRef } from 'react'
import {
  ApiError,
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
  const { user } = useAuth()
  const attempts = useRef(new Map<string, string>())
  return useMutation({
    mutationFn: async (input: RequestPayoutInput) => {
      // Capture this account/operation once, including across an in-flight account change.
      const attempt = JSON.stringify([user?.user.id ?? null, input.amount_minor, input.method])
      const storageKey = `mahadum.payout-request.${attempt}`
      if (!attempts.current.has(attempt)) {
        let previous: string | null = null
        try { if (user) previous = sessionStorage.getItem(storageKey) } catch { /* Keep in-memory retry safety. */ }
        attempts.current.set(attempt, previous || crypto.randomUUID())
      }
      const key = attempts.current.get(attempt)!
      try { if (user) sessionStorage.setItem(storageKey, key) } catch { /* Browser storage may be disabled. */ }
      const clear = () => {
        if (attempts.current.get(attempt) === key) attempts.current.delete(attempt)
        try { if (user && sessionStorage.getItem(storageKey) === key) sessionStorage.removeItem(storageKey) } catch { /* In-memory cleanup still succeeds. */ }
      }
      try {
        const result = await referralApi.requestPayout(input, key)
        clear()
        return result
      } catch (error) {
        // Preserve uncertain attempts so the server can replay its recorded response.
        if (error instanceof ApiError && error.status >= 400 && error.status < 500 && error.status !== 409) clear()
        throw error
      }
    },
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: referralKeys.payouts })
      void qc.invalidateQueries({ queryKey: referralKeys.summary })
    },
  })
}
