import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { billingApi, type CreateSubscriptionInput } from '@/lib/api'

export const billingKeys = {
  plans: ['plans'] as const,
  subscriptions: ['subscriptions'] as const,
  telcoStatus: ['telco-status'] as const,
  dataBundles: ['data-bundles'] as const,
}

export function usePlans() {
  return useQuery({ queryKey: billingKeys.plans, queryFn: billingApi.plans, staleTime: 10 * 60_000 })
}

export function useSubscriptions() {
  return useQuery({ queryKey: billingKeys.subscriptions, queryFn: billingApi.subscriptions })
}

export function useCreateSubscription() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (input: CreateSubscriptionInput) => billingApi.createSubscription(input),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: billingKeys.subscriptions })
      // Invoice subscriptions activate immediately → refresh entitlements via /me.
      void qc.invalidateQueries({ queryKey: ['me'] })
    },
  })
}

export function useCancelSubscription() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => billingApi.cancelSubscription(id),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: billingKeys.subscriptions })
      void qc.invalidateQueries({ queryKey: ['me'] })
    },
  })
}

export function useTelcoStatus() {
  return useQuery({
    queryKey: billingKeys.telcoStatus,
    queryFn: billingApi.telcoStatus,
    retry: false, // 404 when the user has no airtime subscription
  })
}

export function useTelcoSubscribe() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (input: { plan_id: number; msisdn: string; operator: 'mtn' | 'airtel' | 'glo' | 't2' }) =>
      billingApi.telcoSubscribe(input),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['me'] })
      void qc.invalidateQueries({ queryKey: billingKeys.subscriptions })
      void qc.invalidateQueries({ queryKey: billingKeys.telcoStatus })
    },
  })
}

export function useDataBundles() {
  return useQuery({ queryKey: billingKeys.dataBundles, queryFn: billingApi.dataBundles, staleTime: 10 * 60_000 })
}
