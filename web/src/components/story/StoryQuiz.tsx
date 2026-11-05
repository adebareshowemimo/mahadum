import type { ReactNode } from 'react'
import type { StoryDetail } from '@/lib/api'
import { SlideDeck } from '@/components/learning/player'
import type { PlayerService, QuizSlide, Verdict } from '@/components/learning/player/types'

type Question = StoryDetail['questions'][number]
type Result = { correct: boolean; correct_index: number; explanation?: string | null }

export function StoryQuiz({ title, questions, onAnswer, endContent, onExit }: {
  title: string
  questions: Question[]
  onAnswer: (question: Question, optionIndex: number) => Promise<Result>
  endContent?: ReactNode
  onExit: () => void
}) {
  const slides: QuizSlide[] = questions.map((question) => ({
    id: `story-question-${question.id}`,
    componentId: 1,
    completed: false,
    kind: 'quiz',
    questionId: question.id,
    qtype: 'mcq_single',
    prompt: question.prompt,
    promptAudio: null,
    promptImage: null,
    options: question.options.map((label, id) => ({ id, label })),
    matchPool: [],
    passThreshold: 0,
    heartsEnabled: false,
    wasCorrect: false,
  }))
  const service: PlayerService = {
    isPreview: true,
    async gradeQuiz(slide, answer): Promise<Verdict> {
      const question = questions.find((item) => item.id === slide.questionId)
      if (!question || !('optionId' in answer) || answer.optionId === null) throw new Error('Choose an answer.')
      const result = await onAnswer(question, answer.optionId)
      return {
        correct: result.correct,
        correctOptionIds: [result.correct_index],
        correctText: question.options[result.correct_index],
        correctPairs: [],
        explanation: result.explanation ?? null,
        xpAwarded: 0,
        heartsRemaining: null,
        attemptsExhausted: false,
        practiceMode: false,
        competitivePausedUntil: null,
      }
    },
    async completeStep() {},
    async submitSpeaking() {},
    async inviteTonePractice() {},
    async practiceContacts() { return [] },
    async submitAssignment() {},
    async trackVideo() {},
  }

  return <SlideDeck title={title} subtitle="Story quiz" startIcon="❓" startCta="Start quiz" slides={slides} service={service} initialHearts={null} onExit={onExit} renderComplete={() => <div className="space-y-5 text-center"><h2 className="font-display text-2xl font-bold">Quiz complete</h2>{endContent ?? <p>You finished this story quiz.</p>}</div>} />
}