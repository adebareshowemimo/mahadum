import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { Alert, Button, Card, CardBody, Skeleton } from '@/components/ui'
import { api } from '@/lib/api/client'
import { useAuth } from '@/lib/auth/AuthProvider'
import {
  currentPush,
  disablePush,
  enablePush,
  pushApi,
  pushSupported,
} from '@/lib/notifications/browserPush'

interface NotificationPage {
  data: {
    id: string
    data: {
      title?: string
      message?: string
      url?: string
      badge_name?: string
    }
    read_at: string | null
    created_at: string
  }[]
  unread: number
  meta: { last_page: number }
}
export function NotificationsPage() {
  const { user } = useAuth(),
    cache = useQueryClient()
  const [page, setPage] = useState(1),
    [subscribed, setSubscribed] = useState(false),
    [notice, setNotice] = useState('')
  const key = ['notifications', user?.user.id]
  const query = useQuery({
    queryKey: [...key, page],
    queryFn: async (): Promise<NotificationPage> =>
      (await api.get('/me/notifications', { params: { page } })).data,
  })
  const config = useQuery({
    queryKey: ['push-config', user?.user.id],
    queryFn: pushApi.config,
  })
  useEffect(() => {
    if (pushSupported())
      void currentPush()
        .then((s) => setSubscribed(!!s))
        .catch(() => {})
  }, [])
  const read = useMutation({
    mutationFn: async (id: string) => {
      await api.post(
        id === 'all'
          ? '/me/notifications/read-all'
          : `/me/notifications/${id}/read`,
      )
    },
    onSuccess: () => cache.invalidateQueries({ queryKey: key }),
  })
  const push = useMutation({
    mutationFn: async () => {
      if (subscribed) await disablePush()
      else if (config.data?.public_key) await enablePush(config.data.public_key)
      else throw new Error('Browser notifications are not configured.')
    },
    onSuccess: () => {
      setSubscribed(!subscribed)
      setNotice(
        subscribed
          ? 'Browser notifications disabled.'
          : 'Browser notifications enabled for this browser.',
      )
    },
  })
  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="font-display text-2xl font-bold">Notifications</h1>
          <p className="mt-1 text-muted">
            {query.data?.unread ?? 0} unread updates
          </p>
        </div>
        <Button
          variant="outline"
          disabled={!query.data?.unread}
          loading={read.isPending}
          onClick={() => read.mutate('all')}
        >
          Mark all read
        </Button>
      </div>
      <Card>
        <CardBody>
          <h2 className="text-lg font-semibold">Browser notifications</h2>
          <p className="my-3 text-sm text-muted">
            Opt in on this browser. Lock-screen alerts show a general message;
            open the app for details. Signing out disconnects this browser.
          </p>
          {!pushSupported() ? (
            <p className="text-sm text-muted">
              This browser does not support notifications here.
            </p>
          ) : config.isLoading ? (
            <Skeleton className="h-10" />
          ) : config.isError ? (
            <Alert variant="danger">
              Couldn’t check notification settings.
            </Alert>
          ) : !config.data?.enabled && !subscribed ? (
            <p className="text-sm text-muted">
              Browser notifications are unavailable for this account or have not
              been configured yet.
            </p>
          ) : (
            <Button
              variant="outline"
              loading={push.isPending}
              onClick={() => push.mutate()}
            >
              {subscribed
                ? 'Disable browser notifications'
                : 'Enable browser notifications'}
            </Button>
          )}
          {notice && (
            <p role="status" className="mt-3 text-sm">
              {notice}
            </p>
          )}
          {push.error && (
            <Alert variant="danger" className="mt-3">
              {push.error.message}
            </Alert>
          )}
        </CardBody>
      </Card>
      {read.error && <Alert variant="danger">{read.error.message}</Alert>}
      {query.isLoading ? (
        <Skeleton className="h-48" />
      ) : query.isError ? (
        <Alert variant="danger">
          Couldn’t load notifications. Verify your email to view your updates.
        </Alert>
      ) : (
        <>
          <ul className="space-y-3">
            {query.data?.data.map((n) => (
              <li key={n.id}>
                <Card>
                  <CardBody>
                    <div className="flex flex-wrap items-start justify-between gap-3">
                      <div className="min-w-0">
                        <h2 className="font-semibold">
                          {n.data.title ??
                            n.data.badge_name ??
                            'Account update'}
                          {!n.read_at && (
                            <span className="ml-2 text-xs text-primary">
                              Unread
                            </span>
                          )}
                        </h2>
                        <p className="mt-2 text-sm text-muted">
                          {n.data.message ??
                            'Open the related page to see your update.'}
                        </p>
                        <p className="mt-2 text-xs text-muted">
                          {new Date(n.created_at).toLocaleString()}
                        </p>
                        {n.data.url?.startsWith('/') &&
                          !n.data.url.startsWith('//') && (
                            <Link
                              className="mt-3 inline-block text-sm text-primary underline"
                              to={n.data.url}
                            >
                              View update
                            </Link>
                          )}
                      </div>
                      {!n.read_at && (
                        <Button
                          variant="ghost"
                          size="sm"
                          disabled={read.isPending}
                          onClick={() => read.mutate(n.id)}
                        >
                          Mark read
                        </Button>
                      )}
                    </div>
                  </CardBody>
                </Card>
              </li>
            ))}
          </ul>
          {!query.data?.data.length && (
            <p className="text-muted">You’re all caught up.</p>
          )}
          <div className="flex items-center justify-between gap-3">
            <Button
              variant="outline"
              disabled={page === 1}
              onClick={() => setPage((p) => p - 1)}
            >
              Previous
            </Button>
            <span className="text-sm">
              Page {page} of {query.data?.meta.last_page ?? 1}
            </span>
            <Button
              variant="outline"
              disabled={page >= (query.data?.meta.last_page ?? 1)}
              onClick={() => setPage((p) => p + 1)}
            >
              Next
            </Button>
          </div>
        </>
      )}
    </div>
  )
}
