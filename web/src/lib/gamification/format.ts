export function formatDayStreak(count: number): string {
  return `${count.toLocaleString()} Day Streak${Math.abs(count) === 1 || count === 0 ? '' : 's'}`
}
