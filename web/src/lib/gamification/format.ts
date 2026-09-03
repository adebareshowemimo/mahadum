export function formatDayStreak(count: number): string {
  return `${count.toLocaleString()} Day Streak${count === 1 ? '' : 's'}`
}
