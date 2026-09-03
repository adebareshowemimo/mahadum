import { render, screen } from '@testing-library/react'
import type { ReactNode } from 'react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { InvoicesPage } from './InvoicesPage'

const mocks = vi.hoisted(() => ({
  useInvoices: vi.fn(),
  usePayInvoice: vi.fn(),
}))

vi.mock('@/components/school/SchoolGate', () => ({
  SchoolGate: ({ children }: { children: (orgId: number) => ReactNode }) => children(9),
}))

vi.mock('@/lib/school/queries', () => ({
  useInvoices: mocks.useInvoices,
  usePayInvoice: mocks.usePayInvoice,
}))

describe('InvoicesPage', () => {
  beforeEach(() => {
    mocks.usePayInvoice.mockReturnValue({ isPending: false, mutateAsync: vi.fn() })
    mocks.useInvoices.mockReturnValue({
      isLoading: false,
      isError: false,
      data: [{
        id: 27,
        type: 'proforma',
        amount_minor: 43_000_000,
        lines: [
          { description: 'Student School Fees', amount_minor: 35_000_000 },
          { description: 'Registration Fees', amount_minor: 5_000_000 },
          { description: 'VAT (7.5%)', amount_minor: 3_000_000 },
        ],
        status: 'unpaid',
        issued_at: '2026-08-20T00:00:00.000Z',
        paid_at: null,
        has_pdf: false,
      }],
    })
  })

  it('shows the two fee categories before tax and total', () => {
    render(<InvoicesPage />)

    expect(screen.getByText('Student School Fees')).toBeInTheDocument()
    expect(screen.getByText('Registration Fees')).toBeInTheDocument()
    expect(screen.getByText('VAT (7.5%)')).toBeInTheDocument()
    expect(screen.getByText('Total due')).toBeInTheDocument()
    expect(screen.getByText('₦350,000.00')).toBeInTheDocument()
    expect(screen.getByText('₦50,000.00')).toBeInTheDocument()
    expect(screen.getByText('₦430,000.00')).toBeInTheDocument()
  })

  it('shows waived registration fees as a zero-value line', () => {
    mocks.useInvoices.mockReturnValue({
      isLoading: false,
      isError: false,
      data: [{
        id: 28,
        type: 'proforma',
        amount_minor: 37_625_000,
        lines: [
          { description: 'Student School Fees', amount_minor: 35_000_000 },
          { description: 'Registration Fees', amount_minor: 0 },
          { description: 'VAT (7.5%)', amount_minor: 2_625_000 },
        ],
        status: 'unpaid',
        issued_at: null,
        paid_at: null,
        has_pdf: false,
      }],
    })

    render(<InvoicesPage />)

    expect(screen.getByText('Registration Fees')).toBeInTheDocument()
    expect(screen.getByText('₦0.00')).toBeInTheDocument()
  })
})
