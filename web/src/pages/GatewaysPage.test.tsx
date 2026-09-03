import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { GatewaysPage } from './GatewaysPage'

const mocks = vi.hoisted(() => ({
  usePaymentGateways: vi.fn(),
  useTestGateway: vi.fn(),
  useUpdateMonnify: vi.fn(),
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
})
