import { AxiosError } from 'axios'

/**
 * Normalized API error. The backend speaks two error dialects:
 *   • custom:     { error: { code, message, status } }     (AuthController, etc.)
 *   • validation: { message, errors: { field: [msg, ...] } } (422 FormRequest)
 * `toApiError` flattens both into this single shape.
 */
export class ApiError extends Error {
  /** Machine code, e.g. 'invalid_credentials', 'validation', 'network', 'unknown'. */
  readonly code: string
  /** HTTP status, or 0 when the request never reached the server. */
  readonly status: number
  /** Per-field messages for 422 responses, keyed by field name. */
  readonly fieldErrors: Record<string, string>
  /** Extra structured detail from custom errors (e.g. publish-check failures). */
  readonly details?: unknown

  constructor(
    message: string,
    code: string,
    status: number,
    fieldErrors: Record<string, string> = {},
    details?: unknown,
  ) {
    super(message)
    this.name = 'ApiError'
    this.code = code
    this.status = status
    this.fieldErrors = fieldErrors
    this.details = details
  }

  /** First message for a given field, if any. Handy for `<Input error={...} />`. */
  fieldError(name: string): string | undefined {
    return this.fieldErrors[name]
  }

  get isValidation(): boolean {
    return this.status === 422
  }

  get isUnauthorized(): boolean {
    return this.status === 401
  }
}

interface CustomErrorBody {
  error?: { code?: string; message?: string; status?: number; details?: unknown }
}
interface ValidationBody {
  message?: string
  errors?: Record<string, string[]>
}

export function toApiError(err: unknown): ApiError {
  if (err instanceof ApiError) return err

  if (err instanceof AxiosError) {
    // No response → network/timeout/CORS.
    if (!err.response) {
      const offline = typeof navigator !== 'undefined' && !navigator.onLine
      return new ApiError(
        offline ? 'You appear to be offline.' : 'Could not reach the server. Please try again.',
        'network',
        0,
      )
    }

    const { status, data } = err.response

    // Laravel validation (422)
    const validation = data as ValidationBody
    if (status === 422 && validation?.errors) {
      const fieldErrors: Record<string, string> = {}
      for (const [field, messages] of Object.entries(validation.errors)) {
        if (messages?.length) fieldErrors[field] = messages[0]
      }
      return new ApiError(validation.message ?? 'Please check the highlighted fields.', 'validation', 422, fieldErrors)
    }

    // Custom { error: { code, message } }
    const custom = data as CustomErrorBody
    if (custom?.error?.message) {
      return new ApiError(custom.error.message, custom.error.code ?? 'error', status, {}, custom.error.details)
    }

    // Generic { message }
    if (validation?.message) {
      return new ApiError(validation.message, 'error', status)
    }

    return new ApiError(defaultMessageFor(status), 'http_error', status)
  }

  if (err instanceof Error) return new ApiError(err.message, 'unknown', 0)
  return new ApiError('Something went wrong.', 'unknown', 0)
}

function defaultMessageFor(status: number): string {
  if (status === 401) return 'Your session has expired. Please sign in again.'
  if (status === 403) return 'You do not have permission to do that.'
  if (status === 404) return 'Not found.'
  if (status === 429) return 'Too many attempts. Please wait a moment and try again.'
  if (status >= 500) return 'The server ran into a problem. Please try again shortly.'
  return 'Request failed.'
}
