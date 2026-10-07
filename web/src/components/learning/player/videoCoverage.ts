export type WatchedRange = [number, number]

/** Merge actual playback spans; replays never fill an unwatched gap. */
export function mergeWatchedRanges(ranges: WatchedRange[]): WatchedRange[] {
  const result: WatchedRange[] = []
  for (const [start, end] of ranges.filter(([a, b]) => Number.isFinite(a) && Number.isFinite(b) && a >= 0 && b > a).sort((a, b) => a[0] - b[0])) {
    const last = result[result.length - 1]
    if (last && start <= last[1] + 0.05) last[1] = Math.max(last[1], end)
    else result.push([start, end])
  }
  return result
}

export function hasFullCoverage(ranges: WatchedRange[], duration: number | null): boolean {
  if (!duration || !Number.isFinite(duration)) return false
  const merged = mergeWatchedRanges(ranges)
  return merged.length === 1 && merged[0][0] <= 0.25 && merged[0][1] >= duration - 0.25
}
