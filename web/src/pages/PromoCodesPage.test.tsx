import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { PromoCodesPage } from './PromoCodesPage'

const mocks = vi.hoisted(() => ({ remove: vi.fn(), create: vi.fn(), target: 'all' }))
vi.mock('@/lib/admin/queries', () => ({
  useCreatePromo: () => ({ mutateAsync: mocks.create, isPending: false }),
  useDeletePromo: () => ({ mutateAsync: mocks.remove, isPending: false }),
  usePromos: () => ({ data: { data: [{ id: 1, code: 'FAMILY5', target: mocks.target, discount_type: 'percent', value: 5, status: 'active', redemptions_count: 0 }] }, isLoading: false }),
}))

describe('Promo deletion confirmation', () => {
  beforeEach(() => { mocks.target = 'all'; mocks.create.mockReset(); mocks.create.mockResolvedValue({ code: 'REG500' }); mocks.remove.mockReset(); mocks.remove.mockResolvedValue({}) })
  it('saves the fee target and converts fixed naira amounts to minor units', async () => {
    render(<PromoCodesPage />)
    fireEvent.change(screen.getByLabelText('Code'), { target: { value: 'REG500' } })
    fireEvent.change(screen.getByLabelText('Applies to'), { target: { value: 'school_registration' } })
    fireEvent.change(screen.getByLabelText('Discount type'), { target: { value: 'fixed' } })
    fireEvent.change(screen.getByLabelText('Value (₦)'), { target: { value: '500' } })
    fireEvent.click(screen.getByRole('button', { name: 'Create promo code' }))
    await waitFor(() => expect(mocks.create).toHaveBeenCalledWith(expect.objectContaining({ target: 'school_registration', value: 50000, applicable_tier: undefined })))
  })
  it('shares school codes to invoices without needing a plan restriction', async () => {
    mocks.target = 'school_subscription'
    const writeText = vi.fn().mockResolvedValue(undefined)
    Object.defineProperty(navigator, 'clipboard', { configurable: true, value: { writeText } })
    render(<PromoCodesPage />)
    fireEvent.click(screen.getByRole('button', { name: 'Share' }))
    await waitFor(() => expect(writeText).toHaveBeenCalledWith(`${window.location.origin}/invoices?promo=FAMILY5`))
  })
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
