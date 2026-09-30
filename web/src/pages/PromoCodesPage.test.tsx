import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { PromoCodesPage } from './PromoCodesPage'

const mocks = vi.hoisted(() => ({ remove: vi.fn() }))
vi.mock('@/lib/admin/queries', () => ({
  useCreatePromo: () => ({ mutateAsync: vi.fn(), isPending: false }),
  useDeletePromo: () => ({ mutateAsync: mocks.remove, isPending: false }),
  usePromos: () => ({ data: { data: [{ id: 1, code: 'FAMILY5', discount_type: 'percent', value: 5, status: 'active', redemptions_count: 0 }] }, isLoading: false }),
}))

describe('Promo deletion confirmation', () => {
  beforeEach(() => { mocks.remove.mockReset(); mocks.remove.mockResolvedValue({}) })
  it('cancels without deleting and deletes only after confirmation', async () => {
    render(<PromoCodesPage />)
    fireEvent.click(screen.getByRole('button', { name: 'Delete' }))
    const dialog = screen.getByRole('dialog', { name: 'Delete promo code?' })
    expect(within(dialog).getByText(/FAMILY5/)).toBeInTheDocument()
    expect(mocks.remove).not.toHaveBeenCalled()
    fireEvent.click(within(dialog).getByRole('button', { name: 'Cancel' }))
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Delete' }))
    fireEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Delete promo code' }))
    await waitFor(() => expect(mocks.remove).toHaveBeenCalledWith(1))
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
  })
  it('keeps the modal open with an error when deletion fails', async () => {
    mocks.remove.mockRejectedValue(new Error('failed'))
    render(<PromoCodesPage />)
    fireEvent.click(screen.getByRole('button', { name: 'Delete' }))
    fireEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Delete promo code' }))
    expect(await screen.findByText('Could not delete this promo code. Please try again.')).toBeInTheDocument()
    expect(screen.getByRole('dialog')).toBeInTheDocument()
  })
})
