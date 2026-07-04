/** Format a minor-unit amount (e.g. kobo) as a currency string. */
export function formatMoney(minor: number, currency: string): string {
  const amount = (minor ?? 0) / 100
  const formatted = amount.toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
  return currency === 'NGN' ? `₦${formatted}` : `${formatted} ${currency}`
}
