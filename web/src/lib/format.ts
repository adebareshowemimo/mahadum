/** Format a minor-unit amount (e.g. kobo) as a currency string. */
export function formatMoney(minor: number, currency: string): string {
  const amount = (minor ?? 0) / 100
  const formatted = amount.toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
  return currency === 'NGN' ? `₦${formatted}` : `${formatted} ${currency}`
}

/** Format a byte count for compact transfer-status copy. */
export function formatFileSize(bytes: number): string {
  if (!Number.isFinite(bytes) || bytes <= 0) return '0 B'

  const units = ['B', 'KB', 'MB', 'GB'] as const
  const unit = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1)
  const value = bytes / 1024 ** unit
  const digits = unit === 0 || value >= 100 ? 0 : value >= 10 ? 1 : 2

  return `${value.toFixed(digits)} ${units[unit]}`
}
