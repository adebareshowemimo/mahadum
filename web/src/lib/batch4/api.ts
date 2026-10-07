import { api } from '@/lib/api/client'

export interface StudentRow {
  id: number
  name: string
  email: string | null
  classes: { id: number; name: string }[]
}
export interface TeacherDirectory {
  teachers: {
    id: number
    name: string
    email: string
    status: string
    account_status: string
  }[]
  invitations: {
    id: number
    name: string
    email: string
    status: string
    expires_at: string
  }[]
}
export interface TeacherInvite {
  name: string
  email: string
  organization_name: string
  expires_at: string
  status: string
}
export interface AlertPreferences {
  low_balance_coins: number | null
  inactive_days: number | null
  review_alerts: boolean
}
export interface FamilyGoals {
  pools: {
    id: number
    name: string
    goal_coins: number
    coin_balance: number
    transactions: {
      id: number
      type: string
      source: string
      amount: number
      balance_after: number
      created_at: string
    }[]
  }[]
  challenges: {
    id: number
    title: string
    target_lessons: number
    completed_lessons: number
    learner_ids: number[]
    starts_at: string
    ends_at: string
    status: string
  }[]
  alerts: AlertPreferences
}
export const batch4Api = {
  students: async (org: number): Promise<StudentRow[]> =>
    (await api.get(`/schools/${org}/students`)).data.data,
  teachers: async (org: number): Promise<TeacherDirectory> =>
    (await api.get(`/schools/${org}/teacher-directory`)).data.data,
  invite: async (
    org: number,
    input: { name: string; email: string },
  ): Promise<{ delivery_status: string }> =>
    (await api.post(`/schools/${org}/teacher-invitations`, input)).data.data,
  revoke: async (org: number, id: number) => {
    await api.post(`/schools/${org}/teacher-invitations/${id}/revoke`)
  },
  invitation: async (token: string): Promise<TeacherInvite> =>
    (await api.get(`/teacher-invitations/${token}`)).data.data,
  accept: async (token: string): Promise<{ organization_id: number }> =>
    (await api.post(`/teacher-invitations/${token}/accept`)).data.data,
  goals: async (): Promise<FamilyGoals> =>
    (await api.get('/family/goals')).data.data,
  challenge: async (input: {
    title: string
    target_lessons: number
    learner_ids: number[]
    ends_at: string
  }) => {
    await api.post('/family/challenges', input)
  },
  pool: async (input: { name: string; goal_coins: number }) => {
    await api.post('/family/pools', input)
  },
  move: async (
    pool: number,
    input: { direction: string; coins: number; learner_id?: number },
    key: string,
  ) => {
    await api.post(`/family/pools/${pool}/movements`, input, {
      headers: { 'Idempotency-Key': key },
    })
  },
  alerts: async (input: AlertPreferences) => {
    await api.put('/family/alerts', input)
  },
  cheer: async (id: number, message: string): Promise<{ message: string }> =>
    (await api.post(`/family/learners/${id}/cheers`, { message })).data.data,
  cheers: async (id: number): Promise<{ id: number; message: string }[]> =>
    (await api.get(`/learners/${id}/cheers`)).data.data,
}
