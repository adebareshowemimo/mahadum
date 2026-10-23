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

export function useSubscriptionGateways() {
  return useQuery({ queryKey: ['subscription-gateways'], queryFn: billingApi.subscriptionGateways, retry: false, staleTime: 60_000 })
}

export function useCreateSubscription() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (input: CreateSubscriptionInput) => billingApi.createSubscription(input),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: billingKeys.subscriptions })
      // Invoice subscriptions activate immediately → refresh entitlements via /me.
      void qc.invalidateQueries({ queryKey: ['me'] })
      void qc.invalidateQueries({ queryKey: ['learner-path'] })
      void qc.invalidateQueries({ queryKey: ['hearts'] })
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
      void qc.invalidateQueries({ queryKey: ['learner-path'] })
      void qc.invalidateQueries({ queryKey: ['hearts'] })
    },
  })
}

export function useChangeSubscription() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, input }: { id: number; input: CreateSubscriptionInput }) => billingApi.changeSubscription(id, input),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: billingKeys.subscriptions })
      // Invoice changes activate immediately → refresh entitlements via /me.
      void qc.invalidateQueries({ queryKey: ['me'] })
      void qc.invalidateQueries({ queryKey: ['learner-path'] })
      void qc.invalidateQueries({ queryKey: ['hearts'] })
    },
  })
}

export function useRetrySubscription() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => billingApi.retrySubscription(id),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: billingKeys.subscriptions })
      void qc.invalidateQueries({ queryKey: ['me'] })
      void qc.invalidateQueries({ queryKey: ['learner-path'] })
      void qc.invalidateQueries({ queryKey: ['hearts'] })
    },
  })
}

export function useTelcoStatus(enabled = true) {
  return useQuery({
    queryKey: billingKeys.telcoStatus,
    enabled,
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
      void qc.invalidateQueries({ queryKey: ['learner-path'] })
      void qc.invalidateQueries({ queryKey: ['hearts'] })
      void qc.invalidateQueries({ queryKey: billingKeys.subscriptions })
      void qc.invalidateQueries({ queryKey: billingKeys.telcoStatus })
    },
  })
}

export function useDataProviders() {
  return useQuery({ queryKey: [...billingKeys.dataBundles, 'providers'], queryFn: billingApi.dataProviders, retry: false })
}

export function useDataNetworks(enabled = true) {
  return useQuery({ queryKey: [...billingKeys.dataBundles, 'networks'], queryFn: billingApi.dataNetworks, enabled, retry: false, staleTime: 60_000 })
}

export function useDataOffers(network: string, enabled = true) {
  return useQuery({ queryKey: [...billingKeys.dataBundles, 'offers', network], queryFn: () => billingApi.dataOffers(network), enabled: enabled && !!network, retry: false, staleTime: 60_000 })
}

export function useDataBillers(provider: 'monnify' | 'flutterwave' = 'monnify', enabled = true) {
  return useQuery({ queryKey: [...billingKeys.dataBundles, provider, 'billers'], queryFn: () => billingApi.dataBillers(provider), enabled, retry: false, staleTime: 5 * 60_000 })
}

export function useDataBundles(billerCode: string, provider: 'monnify' | 'flutterwave' = 'monnify', enabled = true) {
  return useQuery({ queryKey: [...billingKeys.dataBundles, provider, billerCode], queryFn: () => billingApi.dataBundles(billerCode, provider), enabled: enabled && !!billerCode, retry: false, staleTime: 5 * 60_000 })
}
