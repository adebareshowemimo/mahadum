import { useQuery } from '@tanstack/react-query'
import { configApi, type AppConfig } from '@/lib/api'

/** Fallback digital age if /config hasn't loaded yet (matches backend default). */
export const DEFAULT_DIGITAL_AGE = 13

/**
 * Public launch-time config (digital age, feature flags, languages). Cached for
 * the session — it rarely changes within a visit.
 */
export function useConfig() {
  return useQuery<AppConfig>({
    queryKey: ['config'],
    queryFn: configApi.get,
    staleTime: 10 * 60_000,
    retry: 1,
  })
}

/** The admin-defined digital age, with a safe fallback while config loads. */
export function useDigitalAge(): number {
  const { data } = useConfig()
  return data?.digital_age ?? DEFAULT_DIGITAL_AGE
}
