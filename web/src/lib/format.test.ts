import { describe, expect, it } from 'vitest'
import { formatMoney } from './format'

describe('formatMoney', () => {
  it('formats NGN minor units with the naira symbol', () => {
    expect(formatMoney(150000, 'NGN')).toBe('₦1,500.00')
    expect(formatMoney(0, 'NGN')).toBe('₦0.00')
    expect(formatMoney(108000_00, 'NGN')).toBe('₦108,000.00')
  })

  it('falls back to a code suffix for other currencies', () => {
    expect(formatMoney(100, 'USD')).toBe('1.00 USD')
  })

  it('treats missing amounts as zero', () => {
    expect(formatMoney(undefined as unknown as number, 'NGN')).toBe('₦0.00')
  })
})
