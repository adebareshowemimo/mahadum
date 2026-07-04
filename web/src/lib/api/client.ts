import axios, { type AxiosInstance } from 'axios'
import { deviceId, orgStore, tokenStore } from './storage'
import { toApiError } from './errors'

// Single shared axios instance for the whole app.
//
// Auth model: bearer token (the API's /auth/login mints one). We also set
// `withCredentials` so that if/when the SPA switches to Sanctum cookie+session
// auth, first-party requests already carry cookies — at that point, drop the
// Authorization header below and call `ensureCsrfCookie()` before mutations.
const baseURL = import.meta.env.VITE_API_BASE_URL ?? '/api/v1'

export const api: AxiosInstance = axios.create({
  baseURL,
  withCredentials: true,
  headers: { Accept: 'application/json' },
})

// Request: attach bearer token, active org (tenant), and device id.
api.interceptors.request.use((config) => {
  const token = tokenStore.get()
  if (token) config.headers.Authorization = `Bearer ${token}`

  const org = orgStore.get()
  if (org) config.headers['X-Organization-Id'] = org

  config.headers['X-Device-Id'] = deviceId()
  return config
})

// On a 401 while we believed we were authenticated, the token is dead — clear it
// and let the app react (redirect to /login, reset query cache). We expose a
// settable handler instead of importing router/query here to avoid a cycle.
let onUnauthorized: (() => void) | null = null
export function setUnauthorizedHandler(handler: (() => void) | null): void {
  onUnauthorized = handler
}

// Response: normalize every error into an ApiError.
api.interceptors.response.use(
  (response) => response,
  (error) => {
    const apiError = toApiError(error)
    if (apiError.isUnauthorized && tokenStore.get()) {
      tokenStore.clear()
      onUnauthorized?.()
    }
    return Promise.reject(apiError)
  },
)

/**
 * Prime the Sanctum CSRF cookie. No-op for bearer auth, but kept ready for the
 * cookie/session path: call once before the first state-changing request.
 */
export async function ensureCsrfCookie(): Promise<void> {
  const sanctumBase = import.meta.env.VITE_SANCTUM_URL ?? ''
  await axios.get(`${sanctumBase}/sanctum/csrf-cookie`, { withCredentials: true })
}
