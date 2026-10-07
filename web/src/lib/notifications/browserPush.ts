import { api } from '@/lib/api/client'

export const pushApi = {
  config: async (): Promise<{
    enabled: boolean
    public_key: string | null
  }> => (await api.get('/me/push')).data.data,
  subscribe: async (subscription: PushSubscription) => {
    await api.post('/me/push', subscription.toJSON())
  },
  remove: async (endpoint: string) => {
    await api.delete('/me/push', { data: { endpoint } })
  },
}
export function pushSupported() {
  return (
    window.isSecureContext &&
    'serviceWorker' in navigator &&
    'PushManager' in window &&
    'Notification' in window
  )
}
async function registration() {
  return navigator.serviceWorker.register('/notification-sw.js', {
    scope: '/',
  })
}
export async function currentPush() {
  return (
    (
      await navigator.serviceWorker.getRegistration('/')
    )?.pushManager.getSubscription() ?? null
  )
}
export async function enablePush(publicKey: string) {
  if (!pushSupported())
    throw new Error('This browser does not support notifications here.')
  const permission = await Notification.requestPermission()
  if (permission !== 'granted')
    throw new Error(
      'Browser notification permission was not granted. You can change it in your browser settings.',
    )
  const worker = await registration()
  // Wait for activation before subscribing, including on the first visit.
  const ready = worker.active ? worker : await navigator.serviceWorker.ready
  const bytes = Uint8Array.from(
    atob(publicKey.replace(/-/g, '+').replace(/_/g, '/')),
    (c) => c.charCodeAt(0),
  )
  const subscription =
    (await ready.pushManager.getSubscription()) ??
    (await ready.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: bytes,
    }))
  try {
    await pushApi.subscribe(subscription)
  } catch (error) {
    await subscription.unsubscribe()
    throw error
  }
}
export async function disablePush() {
  if (!('serviceWorker' in navigator)) return
  const subscription = await currentPush()
  if (!subscription) return
  await pushApi.remove(subscription.endpoint)
  await subscription.unsubscribe()
}
export async function clearLocalPush() {
  if ('serviceWorker' in navigator) await (await currentPush())?.unsubscribe()
}
