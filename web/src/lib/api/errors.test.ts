import { AxiosError } from 'axios'
import { describe, expect, it } from 'vitest'
import { ApiError, toApiError } from './errors'

function axiosWith(status: number, data: unknown): AxiosError {
  const err = new AxiosError('request failed')
  // @ts-expect-error minimal response shape for the test
  err.response = { status, data, statusText: '', headers: {}, config: {} }
  return err
}

describe('toApiError', () => {
  it('flattens Laravel 422 validation errors into fieldErrors', () => {
    const err = toApiError(axiosWith(422, { message: 'Invalid', errors: { email: ['Email is taken'], password: ['Too short'] } }))
    expect(err).toBeInstanceOf(ApiError)
    expect(err.isValidation).toBe(true)
    expect(err.code).toBe('validation')
    expect(err.fieldError('email')).toBe('Email is taken')
    expect(err.fieldError('password')).toBe('Too short')
  })

  it('reads the custom { error: { code, message } } dialect', () => {
    const err = toApiError(axiosWith(401, { error: { code: 'invalid_credentials', message: 'Wrong password', status: 401 } }))
    expect(err.code).toBe('invalid_credentials')
    expect(err.message).toBe('Wrong password')
    expect(err.isUnauthorized).toBe(true)
  })

  it('maps a missing response to a network error', () => {
    const err = toApiError(new AxiosError('Network Error'))
    expect(err.code).toBe('network')
    expect(err.status).toBe(0)
  })

  it('provides a friendly default for 5xx', () => {
    const err = toApiError(axiosWith(500, {}))
    expect(err.status).toBe(500)
    expect(err.message).toMatch(/server ran into a problem/i)
  })

  it('passes through an existing ApiError unchanged', () => {
    const original = new ApiError('boom', 'x', 400)
    expect(toApiError(original)).toBe(original)
  })
})
