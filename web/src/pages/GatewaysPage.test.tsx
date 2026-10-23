import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { GatewaysPage } from './GatewaysPage'

const mocks = vi.hoisted(() => ({
  usePaymentGateways: vi.fn(),
  useTestGateway: vi.fn(),
  useUpdateMonnify: vi.fn(),
  useUpdateFlutterwave: vi.fn(),
  useUpdateDataDefault: vi.fn(),
  useUpdateSubscriptionDefault: vi.fn(),
}))

vi.mock('@/lib/admin/queries', () => mocks)

describe('GatewaysPage', () => {
  beforeEach(() => {
    mocks.usePaymentGateways.mockReturnValue({
      isLoading: false,
      isError: false,
      data: {
        live: false,
        default: 'monnify',
        providers: [{
          key: 'monnify', label: 'Monnify', configured: false, is_default: true,
          environment: 'sandbox', webhook_url: 'https://example.test/api/v1/webhooks/monnify', requirements: [],
        }],
        telco: { label: 'Operator SDP', live: false, configured: false, webhook_url: 'https://example.test/telco', requirements: [] },
      },
    })
    mocks.useTestGateway.mockReturnValue({ isPending: false, mutateAsync: vi.fn() })
    mocks.useUpdateMonnify.mockReturnValue({ isPending: false, mutateAsync: vi.fn().mockResolvedValue({}) })
    mocks.useUpdateFlutterwave.mockReturnValue({ isPending: false, mutateAsync: vi.fn().mockResolvedValue({}) })
    mocks.useUpdateDataDefault.mockReturnValue({ isPending: false, mutate: vi.fn(), isSuccess: false, isError: false })
    mocks.useUpdateSubscriptionDefault.mockReturnValue({ isPending: false, mutate: vi.fn(), isSuccess: false, isError: false })
  })

  it('saves the preferred mobile data provider separately', () => {
    const mutate = vi.fn()
    mocks.useUpdateDataDefault.mockReturnValue({ isPending: false, mutate, isSuccess: false, isError: false })
    render(<GatewaysPage />)
    fireEvent.change(screen.getByLabelText('Default mobile data provider'), { target: { value: 'flutterwave' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save mobile data default' }))
    expect(mutate).toHaveBeenCalledWith('flutterwave')
  })

  it('saves the preferred subscription provider separately', () => {
    const mutate = vi.fn()
    mocks.useUpdateSubscriptionDefault.mockReturnValue({ isPending: false, mutate, isSuccess: false, isError: false })
    render(<GatewaysPage />)
    fireEvent.change(screen.getByLabelText('Default subscription provider'), { target: { value: 'paystack' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save subscription default' }))
    expect(mutate).toHaveBeenCalledWith('paystack')
  })

  it('lets a super admin securely configure Monnify', async () => {
    const mutateAsync = vi.fn().mockResolvedValue({})
    mocks.useUpdateMonnify.mockReturnValue({ isPending: false, mutateAsync })

    render(<GatewaysPage />)
    fireEvent.change(screen.getByLabelText('Contract code'), { target: { value: '123456' } })
    fireEvent.change(screen.getByLabelText('API key'), { target: { value: 'MK_TEST' } })
    fireEvent.change(screen.getByLabelText('Secret key'), { target: { value: 'secret' } })
    fireEvent.click(screen.getByRole('button', { name: /save monnify/i }))

    await waitFor(() => expect(mutateAsync).toHaveBeenCalledWith({
      live: false,
      environment: 'sandbox',
      contract_code: '123456',
      api_key: 'MK_TEST',
      secret: 'secret',
    }))
  })

  it('saves Flutterwave independently with data purchases disabled', async () => {
    const mutateAsync = vi.fn().mockResolvedValue({})
    mocks.useUpdateFlutterwave.mockReturnValue({ isPending: false, mutateAsync })
    mocks.usePaymentGateways.mockReturnValue({
      isLoading: false,
      isError: false,
      data: {
        live: true,
        default: 'monnify',
        providers: [{
          key: 'flutterwave', label: 'Flutterwave', configured: false, is_default: false,
          enabled: false, bills_enabled: false, webhook_url: 'https://example.test/api/v1/webhooks/flutterwave',
          requirements: [
            { label: 'Public key', env: 'FLUTTERWAVE_PUBLIC_KEY', set: false },
            { label: 'Secret key', env: 'FLUTTERWAVE_SECRET', set: false },
            { label: 'Encryption key', env: 'FLUTTERWAVE_ENCRYPTION_KEY', set: false },
            { label: 'Webhook hash', env: 'FLUTTERWAVE_SECRET_HASH', set: false },
          ],
        }],
        telco: { label: 'Operator SDP', live: false, configured: false, webhook_url: 'https://example.test/telco', requirements: [] },
      },
    })

    render(<GatewaysPage />)
    fireEvent.change(screen.getByLabelText('Flutterwave public key'), { target: { value: 'FLWPUBK_TEST-example' } })
    fireEvent.change(screen.getByLabelText('Flutterwave secret key'), { target: { value: 'FLWSECK_TEST-example' } })
    fireEvent.change(screen.getByLabelText('Flutterwave encryption key'), { target: { value: 'FLWSECK_TESTencryption' } })
    fireEvent.change(screen.getByLabelText('Flutterwave webhook hash'), { target: { value: 'separate-hash' } })
    fireEvent.click(screen.getByRole('button', { name: /save flutterwave/i }))

    await waitFor(() => expect(mutateAsync).toHaveBeenCalledWith({
      enabled: false,
      bills_enabled: false,
      public_key: 'FLWPUBK_TEST-example',
      secret: 'FLWSECK_TEST-example',
      encryption_key: 'FLWSECK_TESTencryption',
      secret_hash: 'separate-hash',
    }))
  })
})
