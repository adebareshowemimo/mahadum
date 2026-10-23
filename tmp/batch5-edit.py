from pathlib import Path
root = Path(__file__).resolve().parents[1]
def edit(name, old, new):
    p = root / name
    s = p.read_text(encoding='utf-8')
    if old not in s: raise RuntimeError(f'Missing target: {name}: {old[:65]}')
    p.write_text(s.replace(old, new, 1), encoding='utf-8')

edit('app/Http/Controllers/Learning/LessonPlayController.php', "'components.video.sourceAsset',", "'components.video.sourceAsset',\n            'components.video.renditions',\n            'components.video.posterAsset',\n            'components.video.captions',")
edit('app/Http/Controllers/Learning/LessonPlayController.php', "'resume_position' =>", "'watched_ranges' => data_get($cp?->data, 'watched_ranges', []),\n                'resume_position' =>")
edit('app/Http/Controllers/Learning/LessonPlayController.php', "'poster' => null,\n                'captions' => [],", """'poster' => $this->assetUrl($component->video->posterAsset),
                'renditions' => $component->video->renditions
                    ->where('ready', true)->where('protocol', 'mp4')
                    ->map(fn ($r) => ['quality' => $r->quality, 'src' => $r->manifest_url])->values(),
                'captions' => $component->video->captions->where('format', 'vtt')
                    ->map(fn ($c) => ['language' => $c->language_code, 'src' => $c->url, 'default' => $c->is_default])->values(),""")
edit('web/src/lib/api/types.ts', '  captions: unknown[]', "  captions: { language: string; src: string; default: boolean }[]\n  renditions?: { quality: string; src: string }[]")
edit('web/src/lib/api/types.ts', '  resume_position?: number', '  resume_position?: number\n  watched_ranges?: [number, number][]')
edit('web/src/lib/api/endpoints.ts', '    watchedSeconds?: number', '    watchedSeconds?: number\n    watchedRanges?: [number, number][]')
edit('web/src/lib/api/endpoints.ts', '    if (input.watchedSeconds != null)', '    if (input.watchedRanges) body.watched_ranges = input.watchedRanges\n    if (input.watchedSeconds != null)')
edit('web/src/components/learning/player/types.ts', '  alreadyCompleted: boolean', '  alreadyCompleted: boolean\n  watchedRanges?: [number, number][]\n  renditions?: { quality: string; src: string }[]\n  captions?: { language: string; src: string; default: boolean }[]')
edit('web/src/components/learning/player/types.ts', '      requireWatch: !!c.require_watch,', '      watchedRanges: c.watched_ranges ?? [], renditions: c.video?.renditions ?? [], captions: c.video?.captions ?? [],\n      requireWatch: !!c.require_watch,')
edit('web/src/components/learning/player/types.ts', '  watchedDelta: number', '  watchedDelta: number\n  watchedRanges?: [number, number][]')
edit('web/src/components/learning/player/types.ts', '        watchedDelta: d.watchedDelta,', '        watchedDelta: d.watchedDelta,\n        watchedRanges: d.watchedRanges,')

p = root / 'web/src/components/learning/player/slides.tsx'
s = p.read_text(encoding='utf-8')
s = s.replace("import { InviteToPractice } from './InviteToPractice'", "import { InviteToPractice } from './InviteToPractice'\nimport { mergeWatchedRanges, hasFullCoverage, type WatchedRange } from './videoCoverage'")
start = s.index('function VideoSlideView(')
end = s.index('// ---- Speaking ----', start)
v = s[start:end]
v = v.replace('  const [busy, setBusy] = useState(false)', """  const [busy, setBusy] = useState(false)
  const [saveError, setSaveError] = useState<string | null>(null)
  const [quality, setQuality] = useState('original')
  const renditions = (slide.renditions ?? []).filter(r => ['240p', '360p', '720p', '240', '360', '720'].includes(r.quality))
  const selectedSrc = renditions.find(r => r.quality === quality)?.src ?? slide.src
  const switchingRef = useRef<{ position: number; playing: boolean } | null>(null)
  const rangesRef = useRef<WatchedRange[]>(slide.watchedRanges ?? [])
  const queueRef = useRef<Promise<void>>(Promise.resolve())
  const playingRef = useRef(false)
  const seekingRef = useRef(false)""")
v = v.replace("      void service\n        .trackVideo(slide, {", "      const beat = {")
v = v.replace('          watchedDelta,', '          watchedDelta,\n          watchedRanges: rangesRef.current.map(r => [...r] as WatchedRange),')
v = v.replace("          event,", "          event,")
v = v.replace("        })\n        .catch(() => {})", """      }
      // Keep progress requests in order. Every beat carries the full coverage
      // snapshot so a failed heartbeat cannot lose an already-watched span.
      const pending = queueRef.current.catch(() => {}).then(() => service.trackVideo(slide, beat))
      queueRef.current = pending
      void pending.catch(() => {})
      return pending""")
v = v.replace('    if (!v || !slide.src) return', '    if (!v || !selectedSrc) return')
v = v.replace('      const resumeAt = slide.resumeAt', '      const switching = switchingRef.current\n      const resumeAt = switching?.position ?? slide.resumeAt')
v = v.replace("      }\n    }\n    const onPlay", """      }
      if (switching) {
        switchingRef.current = null
        if (switching.playing) void v.play().catch(() => {})
      }
    }
    const onPlay""", 1)
v = v.replace('      playDeltaRef.current += 1', '      playingRef.current = true\n      playDeltaRef.current += 1')
v = v.replace('      if (dt > 0 && dt < 2) watchedDeltaRef.current += dt', """      if (playingRef.current && !seekingRef.current && !v.seeking && dt > 0 && dt < 2) {
        watchedDeltaRef.current += dt
        rangesRef.current = mergeWatchedRanges([...rangesRef.current, [now - dt, now]])
      }""")
v = v.replace('    const onPause = () => {', '    const onPause = () => {\n      onTimeUpdate()\n      playingRef.current = false')
v = v.replace('    const onSeeked = () => {', '    const onSeeking = () => { seekingRef.current = true }\n    const onSeeked = () => {\n      seekingRef.current = false')
v = v.replace("      setWatchedToEnd(true)\n      flush('completed')", """      onTimeUpdate()
      playingRef.current = false
      if (slide.requireWatch && !hasFullCoverage(rangesRef.current, durationRef.current)) {
        flush('paused')
        return
      }
      setBusy(true)
      setSaveError(null)
      void flush('completed')?.then(() => setWatchedToEnd(true))
        .catch(() => setSaveError('Your progress could not be saved. Press Continue to retry.'))
        .finally(() => setBusy(false))""")
v = v.replace("    v.addEventListener('seeked', onSeeked)", "    v.addEventListener('seeking', onSeeking)\n    v.addEventListener('seeked', onSeeked)")
v = v.replace("      v.removeEventListener('seeked', onSeeked)", "      v.removeEventListener('seeking', onSeeking)\n      v.removeEventListener('seeked', onSeeked)")
v = v.replace('  }, [slide.src, flush])', '  }, [selectedSrc, flush])')
v = v.replace('    try {\n      await service.completeStep(slide)', "    setSaveError(null)\n    try {\n      if (saveError) await flush('completed')\n      await queueRef.current\n      await service.completeStep(slide)")
v = v.replace('      onAdvance()\n    } finally', "      onAdvance()\n    } catch {\n      setSaveError('Your progress could not be saved. Press Continue to retry.')\n    } finally")
v = v.replace("  const locked = !isYoutube && slide.requireWatch && hasVideo && !watchedToEnd", "  const locked = !isYoutube && slide.requireWatch && !watchedToEnd && !(saveError && hasFullCoverage(rangesRef.current, durationRef.current))")
v = v.replace('            src={slide.src ?? undefined}', '            src={selectedSrc ?? undefined}')
v = v.replace('          />\n        ) : (\n          <div', """          >
            {(slide.captions ?? []).map(c => <track key={c.language} kind="subtitles" srcLang={c.language} label={c.language} src={c.src} default={c.default} />)}
          </video>
        ) : (
          <div""")
v = v.replace('        <p className="text-sm text-muted">', """        {renditions.length > 0 && !isYoutube && <label className="text-sm text-muted">Video quality
          <select aria-label="Video quality" value={quality} onChange={e => {
            const v = videoRef.current
            if (v) { switchingRef.current = { position: v.currentTime, playing: !v.paused }; flush('paused') }
            setQuality(e.target.value)
          }} className="ml-2 rounded border border-gold-500/20 bg-charcoal-900 p-2">
            <option value="original">Original</option>
            {renditions.map(r => <option key={r.quality} value={r.quality}>{r.quality.replace(/p$/, '')}p</option>)}
          </select>
        </label>}
        {saveError && <Alert variant="error">{saveError}</Alert>}
        <p className="text-sm text-muted">""")
s = s[:start] + v + s[end:]
p.write_text(s, encoding='utf-8')
