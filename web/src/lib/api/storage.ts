// Client-side persistence for the auth token, active organization, and a stable
// per-browser device id. The token is mirrored in memory so the request
// interceptor never pays a localStorage read on the hot path.
//
// NOTE: we use bearer-token auth (the API's /auth/login returns a token). If we
// later move the web SPA to Sanctum cookie/session auth, this module is the only
// place that needs to change — see lib/api/client.ts for the matching note.

const TOKEN_KEY = 'mahadum.token'
const ORG_KEY = 'mahadum.active_org'
const DEVICE_KEY = 'mahadum.device_id'

let memToken: string | null | undefined

export const tokenStore = {
  get(): string | null {
    if (memToken === undefined) memToken = localStorage.getItem(TOKEN_KEY)
    return memToken
  },
  set(token: string): void {
    memToken = token
    localStorage.setItem(TOKEN_KEY, token)
  },
  clear(): void {
    memToken = null
    localStorage.removeItem(TOKEN_KEY)
  },
}

export const orgStore = {
  get(): string | null {
    return localStorage.getItem(ORG_KEY)
  },
  set(id: number | string | null): void {
    if (id == null) localStorage.removeItem(ORG_KEY)
    else localStorage.setItem(ORG_KEY, String(id))
  },
}

/** Stable id for this browser, used for device fingerprinting + referral fraud guards. */
export function deviceId(): string {
  let id = localStorage.getItem(DEVICE_KEY)
  if (!id) {
    id =
      typeof crypto !== 'undefined' && 'randomUUID' in crypto
        ? crypto.randomUUID()
        : `web-${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}`
    localStorage.setItem(DEVICE_KEY, id)
  }
  return id
}

/** Human-friendly device label sent as `device_name` when minting a token. */
export function deviceName(): string {
  if (typeof navigator === 'undefined') return 'Web'
  const ua = navigator.userAgent
  const browser =
    /Edg\//.test(ua) ? 'Edge'
    : /OPR\//.test(ua) ? 'Opera'
    : /Chrome\//.test(ua) ? 'Chrome'
    : /Firefox\//.test(ua) ? 'Firefox'
    : /Safari\//.test(ua) ? 'Safari'
    : 'Browser'
  const os =
    /Windows/.test(ua) ? 'Windows'
    : /Mac OS X/.test(ua) ? 'macOS'
    : /Android/.test(ua) ? 'Android'
    : /(iPhone|iPad|iOS)/.test(ua) ? 'iOS'
    : /Linux/.test(ua) ? 'Linux'
    : 'Web'
  return `${browser} on ${os}`
}
