/// <reference types="vite/client" />

interface ImportMetaEnv {
  /** Base URL of the Laravel API, e.g. `/api/v1` (dev) or `https://api.mahadum.app/api/v1`. */
  readonly VITE_API_BASE_URL?: string
  /** Origin of the API for the Sanctum CSRF-cookie call when not same-origin. Empty = same origin (proxied). */
  readonly VITE_SANCTUM_URL?: string
  /** Google OAuth client id for "Continue with Google". Empty = button shown disabled. */
  readonly VITE_GOOGLE_CLIENT_ID?: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}
