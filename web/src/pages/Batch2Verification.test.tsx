import type { ReactNode } from 'react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { ApiError, familyApi, schoolApi, type LearnerTasks, type ReviewQueue, type SchoolClassDetail } from '@/lib/api'
import { familyKeys } from '@/lib/family/queries'
import { TasksPage } from './TasksPage'
import { ReviewsPage } from './ReviewsPage'
import { ClassPage } from './ClassPage'

vi.mock('@/lib/profile/ActiveProfile', () => ({ useActiveProfile: () => ({ activeLearnerId: 21 }) }))
vi.mock('@/lib/auth/AuthProvider', () => ({ useAuth: () => ({ activeOrgId: 3, user: { user: { id: 7, roles: ['teacher'] }, organizations: [{ id: 3 }] }, hasRole: (...roles: string[]) => roles.includes('teacher') }) }))
afterEach(() => vi.restoreAllMocks())

function show(page: ReactNode, entry = '/') {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false, staleTime: Infinity }, mutations: { retry: false } } })
  client.setQueryData(familyKeys.child(21), { coin_balance: 0 })
  client.setQueryData(['me'], {})
  render(<QueryClientProvider client={client}><MemoryRouter initialEntries={[entry]}>{page}</MemoryRouter></QueryClientProvider>)
  return client
}

const tasks: LearnerTasks = {
  chores: [{ id: 1, title: 'Tidy your desk', description: null, coin_reward: 15, status: 'active', due_at: null, review_decision: null }],
  assignments: [{ id: 2, title: 'Write a greeting', class_name: 'Yoruba Class', instructions: 'Greet someone in Yoruba', coin_reward: 50, status: null, due_at: null, feedback: null, parent_review_status: null }],
}

describe('Batch 2 family, learner and class flows', () => {
  it('submits checkbox chore evidence and prevents another submission while waiting', async () => {
    const user = userEvent.setup()
    vi.spyOn(familyApi, 'tasks').mockResolvedValueOnce(tasks).mockResolvedValue({ ...tasks, chores: [{ ...tasks.chores[0], status: 'pending_review' }] })
    vi.spyOn(familyApi, 'submitChore').mockResolvedValue({ status: 'pending_review' })
    show(<TasksPage />)
    await user.click(await screen.findByRole('button', { name: 'I finished this chore' }))
    await waitFor(() => expect(familyApi.submitChore).toHaveBeenCalledWith(1, 21))
    expect(await screen.findByText('Waiting for review')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'I finished this chore' })).not.toBeInTheDocument()
  })

  it('lets a learner see instructions and submit written class work', async () => {
    const user = userEvent.setup()
    vi.spyOn(familyApi, 'tasks').mockResolvedValueOnce(tasks).mockResolvedValue({ ...tasks, assignments: [{ ...tasks.assignments[0], status: 'submitted' }] })
    vi.spyOn(familyApi, 'submitClassAssignment').mockResolvedValue({ status: 'submitted' })
    show(<TasksPage />)
    const button = await screen.findByRole('button', { name: 'Submit assignment' })
    expect(button).toBeDisabled()
    expect(screen.getByText('Greet someone in Yoruba')).toBeInTheDocument()
    await user.type(screen.getByLabelText('Your answer for Write a greeting'), 'E kaaro')
    await user.click(button)
    await waitFor(() => expect(familyApi.submitClassAssignment).toHaveBeenCalledWith(2, 21, 'E kaaro'))
    expect(await screen.findByText('Submitted')).toBeInTheDocument()
  })

  it('keeps an unsuccessful chore visible and shows the server error', async () => {
    vi.spyOn(familyApi, 'tasks').mockResolvedValue(tasks)
    vi.spyOn(familyApi, 'submitChore').mockRejectedValue(new ApiError('This chore has already been approved.', 'chore_reviewed', 422))
    show(<TasksPage />)
    await userEvent.click(await screen.findByRole('button', { name: 'I finished this chore' }))
    expect(await screen.findByRole('alert')).toHaveTextContent('This chore has already been approved.')
  })

  it('shows teacher-passed work in parent Reviews and refreshes the child balance on approval', async () => {
    const queue: ReviewQueue = { chores: [], speaking: [], assignments: [], class_assignments: [{ id: 4, title: 'Greetings', learner: 'Ada', coin_reward: 50, text_body: 'E kaaro', feedback: 'Well done', media_type: 'audio', media_url: '/storage/assignments/existing-fixture.mp3' }] }
    vi.spyOn(familyApi, 'pendingReviews').mockResolvedValueOnce(queue).mockResolvedValue({ ...queue, class_assignments: [] })
    vi.spyOn(familyApi, 'overview').mockResolvedValue({ learners: [] } as unknown as Awaited<ReturnType<typeof familyApi.overview>>)
    vi.spyOn(familyApi, 'reviewClassAssignment').mockResolvedValue({ coins_released: 50 })
    const client = show(<ReviewsPage />)
    expect(await screen.findByText('E kaaro')).toBeInTheDocument()
    expect(screen.getByText('Teacher feedback: Well done')).toBeInTheDocument()
    expect(document.querySelector('audio')).toHaveAttribute('src', '/storage/assignments/existing-fixture.mp3')
    await userEvent.click(screen.getByRole('button', { name: 'Approve · 50 coins' }))
    await waitFor(() => expect(familyApi.reviewClassAssignment).toHaveBeenCalledWith(4, 'approve'))
    expect(await screen.findByText(/All caught up/)).toBeInTheDocument()
    expect(client.getQueryState(familyKeys.child(21))?.isInvalidated).toBe(true)
    expect(client.getQueryState(['me'])?.isInvalidated).toBe(true)
  })

  it('opens the selected student’s quiz, lesson and assignment drill-down', async () => {
    vi.spyOn(schoolApi, 'classDetail').mockResolvedValue({ id: 11, name: 'Yoruba Class', organization_id: 3, capabilities: { update: false, assign_teacher: false }, students: [] } as unknown as SchoolClassDetail)
    vi.spyOn(schoolApi, 'classAnalytics').mockResolvedValue({ class: { id: 11, name: 'Yoruba Class' }, students: [{ learner_id: 21, display_name: 'Ada', lessons_completed: 1, avg_score: 80, quiz_total: 2, quiz_correct: 1, quiz_accuracy: 50, speaking_count: 0, assignments_submitted: 1, assignments_passed: 1 }] })
    vi.spyOn(schoolApi, 'studentAnalytics').mockResolvedValue({ learner: { id: 21, display_name: 'Ada' }, lessons: [{ id: 1, title: 'Lesson A', status: 'completed', score: 80, completed_at: '2026-10-07' }], quizzes: [{ id: 1, quiz_id: 3, attempt_no: 1, score: 0.5, completed_at: '2026-10-07' }], speaking: [], assignments: [{ id: 1, title: 'Greetings', status: 'graded', score: 90, feedback: 'Well done' }] })
    show(<Routes><Route path="/classes/:classId" element={<ClassPage />} /></Routes>, '/classes/11?tab=analytics')
    await userEvent.click(await screen.findByRole('button', { name: 'Ada' }))
    const modal = await screen.findByRole('dialog', { name: 'Ada — learning activity' })
    expect(await within(modal).findByText(/Lesson A · completed · 80%/)).toBeInTheDocument()
    expect(within(modal).getByText(/Quiz 3 · Attempt 1 · Completed · 50%/)).toBeInTheDocument()
    expect(within(modal).getByText('No speaking submissions.')).toBeInTheDocument()
    expect(schoolApi.studentAnalytics).toHaveBeenCalledWith(11, 21)
  })
})
