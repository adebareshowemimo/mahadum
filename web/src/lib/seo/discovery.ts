export interface SignupDiscoveryInput {
  preferred_language?: string
  language_vote?: string
  other_language?: string
  utm_source?: string
  utm_medium?: string
  utm_campaign?: string
}

const KEY = 'mahadum.attribution'

export function captureAttribution(search = window.location.search): void {
  try {
    if (sessionStorage.getItem(KEY)) return
    const params = new URLSearchParams(search)
    const tags = Object.fromEntries(['utm_source', 'utm_medium', 'utm_campaign'].flatMap((key) => {
      const value = params.get(key)?.trim().slice(0, 255)
      return value ? [[key, value]] : []
    }))
    if (Object.keys(tags).length) sessionStorage.setItem(KEY, JSON.stringify(tags))
  } catch { /* A blocked browser store must not prevent registration. */ }
}

export function signupAttribution(): Pick<SignupDiscoveryInput, 'utm_source' | 'utm_medium' | 'utm_campaign'> {
  try { return JSON.parse(sessionStorage.getItem(KEY) ?? '{}') } catch { return {} }
}

export const VOTE_OPTIONS = ['Ebira', 'Edo', 'Efik', 'Fulfulde', 'Gbagyi', 'Ibibio', 'Idoma', 'Igala', 'Ijaw', 'Isoko', 'Itsekiri', 'Kanuri', 'Nupe', 'Tiv', 'Urhobo', 'Other']
