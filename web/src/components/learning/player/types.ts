import {
  learningApi,
  type AnswerResult,
  type AuthorLesson,
  type LessonPlay,
  type PlayComponent,
} from '@/lib/api'

// ---- Normalized slide model ----
//
// Both the live learner play payload (`LessonPlay`) and the authoring detail
// (`AuthorLesson`) are flattened into a flat list of `Slide`s — one screen each,
// Duolingo-style. A quiz component expands to one slide per question.

/** The question types the player can render + grade. */
export type QType =
  | 'mcq_single'
  | 'mcq_multi'
  | 'true_false'
  | 'fill_blank'
  | 'listen_and_respond'
  | 'complete_the_chat'
  | 'word_bank'
  | 'match_pairs'
  | 'type_what_you_hear'

/** A pairing the learner submits for a match_pairs question. */
export interface Pair {
  option_id: number
  match_target: string
}

/** The shapes an answer can take, one per grading family. */
export type Answer =
  | { optionId: number | null }
  | { optionIds: number[] }
  | { text: string }
  | { pairs: Pair[] }

export interface Verdict {
  correct: boolean
  /** Correct option ids — a set (mcq) or an order (word_bank). */
  correctOptionIds: number[]
  correctText: string | null
  correctPairs: Pair[]
  explanation: string | null
  xpAwarded: number
  heartsRemaining: number | null
  /** Replay past the quiz's attempt cap — graded for practice, nothing scored. */
  attemptsExhausted: boolean
}

/** Only free-text questions take a typed answer; everything else is interactive. */
export function isTextInput(qtype: QType): boolean {
  return qtype === 'type_what_you_hear'
}

interface SlideBase {
  id: string
  componentId: number
  lessonTitle?: string
}

export interface VideoSlide extends SlideBase {
  kind: 'video'
  title: string | null
  src: string | null
  poster: string | null
  /** 'youtube' videos embed via iframe and are never watch-gated (no playback events to track). */
  sourceType: 'upload' | 'youtube' | string
  externalUrl: string | null
  /** When true, the learner must finish the clip before the continue button unlocks. */
  requireWatch: boolean
  /** Saved playhead (seconds) to resume from; 0 = start. */
  resumeAt: number
  /** The learner already finished this video before (gate starts unlocked). */
  alreadyCompleted: boolean
}

export interface SpeakingSlide extends SlideBase {
  kind: 'speaking'
  prompt: string
  target: string | null
}

export interface QuizSlide extends SlideBase {
  kind: 'quiz'
  questionId: number
  qtype: QType
  prompt: string
  /** Prompt media (listen_and_respond / illustrated questions). */
  promptAudio: string | null
  promptImage: string | null
  options: { id: number; label: string }[]
  /** Right-side choices for match_pairs (shuffled, no pairing revealed). */
  matchPool: string[]
}

export interface AssignmentSlide extends SlideBase {
  kind: 'assignment'
  prompt: string
  /** 'video' | 'audio' — what the learner records/uploads. */
  expectedMedia: string
  maxDuration: number | null
  /** Coins escrowed until a parent approves the clip. */
  coinReward: number
}

export interface ExerciseCard {
  id: number
  front: string
  back: string
  mnemonic: string | null
  audio: string | null
}

export interface ExerciseSlide extends SlideBase {
  kind: 'exercise'
  cards: ExerciseCard[]
}

export interface GameSlide extends SlideBase {
  kind: 'game'
  gameType: string
  pairs: { a: string; b: string }[]
}

export interface GenericSlide extends SlideBase {
  kind: 'generic'
  activity: string
}

export type Slide = VideoSlide | SpeakingSlide | QuizSlide | AssignmentSlide | ExerciseSlide | GameSlide | GenericSlide

export function countQuiz(slides: Slide[]): number {
  return slides.filter((s) => s.kind === 'quiz').length
}

/** Extracts an embeddable youtube-nocookie.com URL from a youtube.com/youtu.be link, or null if unparseable. */
export function youtubeEmbedUrl(url: string | null): string | null {
  if (!url) return null
  try {
    const u = new URL(url)
    let id: string | null = null
    if (u.hostname === 'youtu.be') {
      id = u.pathname.slice(1)
    } else if (u.hostname.endsWith('youtube.com')) {
      id = u.searchParams.get('v') ?? (u.pathname.startsWith('/embed/') ? u.pathname.slice(7) : null)
    }
    return id ? `https://www.youtube-nocookie.com/embed/${id}` : null
  } catch {
    return null
  }
}

// ---- Adapters ----

function playComponentToSlides(c: PlayComponent, lessonTitle?: string): Slide[] {
  const base = { componentId: c.id, lessonTitle }
  if (c.type === 'video') {
    return [{
      ...base, id: `v${c.id}`, kind: 'video', title: null, src: c.video?.src ?? null, poster: c.video?.poster ?? null,
      sourceType: c.video?.source_type ?? 'upload', externalUrl: c.video?.external_url ?? null,
      requireWatch: !!c.require_watch, resumeAt: c.resume_position ?? 0, alreadyCompleted: !!c.completed,
    }]
  }
  if (c.type === 'speaking') {
    return [{ ...base, id: `s${c.id}`, kind: 'speaking', prompt: c.speaking?.prompt ?? '', target: c.speaking?.target_text ?? null }]
  }
  if (c.type === 'assignment') {
    return [{ ...base, id: `a${c.id}`, kind: 'assignment', prompt: c.assignment?.prompt ?? '', expectedMedia: c.assignment?.expected_media ?? 'video', maxDuration: c.assignment?.max_duration_seconds ?? null, coinReward: c.assignment?.coin_reward ?? 0 }]
  }
  if (c.type === 'exercise') {
    return [{ ...base, id: `e${c.id}`, kind: 'exercise', cards: (c.exercise?.cards ?? []).map((f) => ({ id: f.id, front: f.front, back: f.back, mnemonic: f.mnemonic, audio: f.audio })) }]
  }
  if (c.type === 'game') {
    return [{ ...base, id: `gm${c.id}`, kind: 'game', gameType: c.game?.game_type ?? 'memory', pairs: c.game?.pairs ?? [] }]
  }
  if (c.type === 'quiz') {
    return (c.quiz?.questions ?? []).map((q) => ({
      ...base,
      id: `q${c.id}-${q.id}`,
      kind: 'quiz' as const,
      questionId: q.id,
      qtype: (q.type as QType) ?? 'mcq_single',
      prompt: q.prompt,
      promptAudio: q.prompt_audio ?? null,
      promptImage: q.prompt_image ?? null,
      options: q.options.map((o) => ({ id: o.id, label: o.label })),
      matchPool: q.match_pool ?? [],
    }))
  }
  return [{ ...base, id: `g${c.id}`, kind: 'generic', activity: c.type }]
}

/** Live learner payload → slides (correct answers come from the server). */
export function playToSlides(play: LessonPlay): Slide[] {
  return play.components.flatMap((c) => playComponentToSlides(c, play.lesson.title))
}

/** Per-question answer key the preview grader checks against locally. */
interface QuizKey {
  qtype: QType
  /** Correct option ids — a set (mcq) or an order (word_bank). */
  correctOptionIds: number[]
  correctPairs: Pair[]
  targetText: string | null
  explanation: string | null
}

/** Authoring detail → slides + a local answer key (preview grades client-side). */
export function authorToSlides(lesson: AuthorLesson): { slides: Slide[]; key: Map<number, QuizKey> } {
  const slides: Slide[] = []
  const key = new Map<number, QuizKey>()

  for (const c of lesson.components ?? []) {
    const d = (c.detail ?? {}) as Record<string, unknown>
    const base = { componentId: c.id, lessonTitle: lesson.title }

    if (c.type === 'video') {
      slides.push({
        ...base, id: `v${c.id}`, kind: 'video', title: (d.title as string) ?? null, src: (d.src as string) ?? null, poster: (d.poster as string) ?? null,
        sourceType: (d.source_type as string) ?? 'upload', externalUrl: (d.external_url as string) ?? null,
        requireWatch: !!(c.settings?.require_watch), resumeAt: 0, alreadyCompleted: false,
      })
    } else if (c.type === 'speaking') {
      slides.push({ ...base, id: `s${c.id}`, kind: 'speaking', prompt: (d.prompt_text as string) || (d.prompt as string) || '', target: (d.target_text as string) ?? null })
    } else if (c.type === 'assignment') {
      slides.push({ ...base, id: `a${c.id}`, kind: 'assignment', prompt: (d.prompt as string) ?? '', expectedMedia: (d.expected_media as string) ?? 'video', maxDuration: (d.max_duration_seconds as number) ?? null, coinReward: (d.coin_reward as number) ?? 0 })
    } else if (c.type === 'exercise') {
      const cards = (d.cards as Array<Record<string, unknown>>) ?? []
      slides.push({ ...base, id: `e${c.id}`, kind: 'exercise', cards: cards.map((f) => ({ id: f.id as number, front: (f.front_text as string) ?? '', back: (f.back_text as string) ?? '', mnemonic: (f.mnemonic as string) ?? null, audio: (f.audio as string) ?? null })) })
    } else if (c.type === 'game') {
      const cfg = (d.config as { pairs?: { a: string; b: string }[] }) ?? {}
      slides.push({ ...base, id: `gm${c.id}`, kind: 'game', gameType: (d.game_type as string) ?? 'memory', pairs: cfg.pairs ?? [] })
    } else if (c.type === 'quiz') {
      const questions = (d.questions as Array<Record<string, unknown>>) ?? []
      for (const q of questions) {
        const qid = q.id as number
        const qtype = (q.type as QType) ?? 'mcq_single'
        const options = (q.options as Array<{ id: number; label: string; is_correct: boolean; match_target?: string | null }>) ?? []
        slides.push({
          ...base,
          id: `q${c.id}-${qid}`,
          kind: 'quiz',
          questionId: qid,
          qtype,
          prompt: q.prompt as string,
          promptAudio: (q.prompt_audio as string) ?? null,
          promptImage: (q.prompt_image as string) ?? null,
          options: options.map((o) => ({ id: o.id, label: o.label })),
          matchPool: qtype === 'match_pairs' ? options.map((o) => o.match_target ?? '').filter(Boolean) : [],
        })
        key.set(qid, {
          qtype,
          // word_bank's "correct" is the authored order; option types use the flag.
          correctOptionIds: qtype === 'word_bank'
            ? options.map((o) => o.id)
            : options.filter((o) => o.is_correct).map((o) => o.id),
          correctPairs: options.map((o) => ({ option_id: o.id, match_target: o.match_target ?? '' })),
          targetText: (q.target_text as string) ?? null,
          explanation: (q.explanation as string) ?? null,
        })
      }
    } else {
      slides.push({ ...base, id: `g${c.id}`, kind: 'generic', activity: c.type })
    }
  }

  return { slides, key }
}

// ---- Services ----
//
// The deck is grading-agnostic: callers inject a service. The live player talks
// to the API; the preview grades against the local answer key and simulates
// hearts so authors can click through without persisting anything.

/** One video-tracking beat — deltas accumulate server-side, position is absolute. */
export interface VideoTrack {
  event: 'played' | 'paused' | 'seeked' | 'heartbeat' | 'completed'
  watchedDelta: number
  playDelta: number
  positionSeconds: number
  durationSeconds: number | null
  completed: boolean
}

export interface PlayerService {
  readonly isPreview: boolean
  gradeQuiz(slide: QuizSlide, answer: Answer): Promise<Verdict>
  completeStep(slide: Slide): Promise<void>
  submitSpeaking(slide: SpeakingSlide, audio: Blob | null): Promise<void>
  /** Submit a recorded assignment clip (coins escrow until a parent approves). */
  submitAssignment(slide: AssignmentSlide, media: Blob | null, filename?: string): Promise<void>
  /** Persist a video-watching beat (no-op in preview). */
  trackVideo(slide: VideoSlide, data: VideoTrack): Promise<void>
}

/** Serialize a client Answer into the API's snake_case answer payload. */
function answerPayload(answer: Answer): Record<string, unknown> {
  if ('optionId' in answer) return { option_id: answer.optionId }
  if ('optionIds' in answer) return { option_ids: answer.optionIds }
  if ('pairs' in answer) return { pairs: answer.pairs }
  return { text: answer.text }
}

export function createLiveService(lessonId: number, learnerId: number): PlayerService {
  return {
    isPreview: false,
    async gradeQuiz(slide, answer) {
      const res: AnswerResult = await learningApi.answer({
        componentId: slide.componentId,
        learnerId,
        questionId: slide.questionId,
        answer: answerPayload(answer),
      })
      return {
        correct: res.correct,
        correctOptionIds: res.correct_answer.option_ids ?? [],
        correctText: res.correct_answer.text ?? null,
        correctPairs: res.correct_answer.pairs ?? [],
        explanation: res.explanation,
        xpAwarded: res.xp_awarded,
        heartsRemaining: res.hearts_remaining,
        attemptsExhausted: res.attempts_exhausted ?? false,
      }
    },
    async completeStep(slide) {
      await learningApi.progress({ lessonId, learnerId, componentId: slide.componentId, completed: true })
    },
    async submitSpeaking(slide, audio) {
      await learningApi.submitSpeaking({ learnerId, componentId: slide.componentId, audio: audio ?? undefined })
    },
    async submitAssignment(slide, media, filename) {
      await learningApi.submitAssignment({ learnerId, componentId: slide.componentId, media: media ?? undefined, filename })
    },
    async trackVideo(slide, d) {
      await learningApi.progress({
        lessonId,
        learnerId,
        componentId: slide.componentId,
        event: d.event,
        watchedDelta: d.watchedDelta,
        playDelta: d.playDelta,
        positionSeconds: d.positionSeconds,
        durationSeconds: d.durationSeconds ?? undefined,
        completed: d.completed || undefined,
      })
    },
  }
}

/** Lower-case, trim, and strip diacritics — mirrors the server's fuzzy match. */
function norm(value: string): string {
  return value.trim().toLowerCase().normalize('NFD').replace(/\p{Diacritic}/gu, '')
}

function sameSet(a: number[], b: number[]): boolean {
  if (a.length !== b.length) return false
  const sa = [...a].sort((x, y) => x - y)
  const sb = [...b].sort((x, y) => x - y)
  return sa.every((v, i) => v === sb[i])
}

function sameOrder(a: number[], b: number[]): boolean {
  return a.length === b.length && a.every((v, i) => v === b[i])
}

/** Local grader for preview. `hearts` is a mutable holder so it persists across calls. */
export function createPreviewService(key: Map<number, QuizKey>, hearts: { value: number }): PlayerService {
  return {
    isPreview: true,
    async gradeQuiz(slide, answer) {
      const k = key.get(slide.questionId)
      let correct = false
      if (k) {
        if ('optionId' in answer) {
          correct = answer.optionId != null && k.correctOptionIds.includes(answer.optionId)
        } else if ('optionIds' in answer) {
          correct = k.qtype === 'word_bank'
            ? sameOrder(answer.optionIds, k.correctOptionIds)
            : k.correctOptionIds.length > 0 && sameSet(answer.optionIds, k.correctOptionIds)
        } else if ('pairs' in answer) {
          correct =
            k.correctPairs.length > 0 &&
            answer.pairs.length === k.correctPairs.length &&
            answer.pairs.every((p) =>
              k.correctPairs.some((c) => c.option_id === p.option_id && norm(c.match_target) === norm(p.match_target)),
            )
        } else {
          correct = !!k.targetText && norm(answer.text) === norm(k.targetText)
        }
      }
      if (!correct && hearts.value > 0) hearts.value -= 1
      return {
        correct,
        correctOptionIds: k?.correctOptionIds ?? [],
        correctText: k?.targetText ?? null,
        correctPairs: k?.correctPairs ?? [],
        explanation: k?.explanation ?? null,
        xpAwarded: correct ? 10 : 0,
        heartsRemaining: hearts.value,
        attemptsExhausted: false,
      }
    },
    async completeStep() {},
    async submitSpeaking() {},
    async submitAssignment() {},
    async trackVideo() {},
  }
}
