import { useEffect, useState } from 'react'
import { useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Alert, Badge, Button, Skeleton } from '@/components/ui'
import { ApiError, billingApi, learningApi, type StoryPage } from '@/lib/api'
import { useActiveProfile } from '@/lib/profile/ActiveProfile'

type Gateway = 'monnify' | 'paystack' | 'flutterwave'

export function StoryReaderPage() {
  const id = Number(useParams().storyId)
  const [params, setParams] = useSearchParams()
  const navigate = useNavigate()
  const qc = useQueryClient()
  const { activeLearner } = useActiveProfile()
  const [gateway, setGateway] = useState<Gateway | null>(null)
  const [busy, setBusy] = useState(false)
  const [pendingPurchaseId, setPendingPurchaseId] = useState<number | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const story = useQuery({ queryKey: ['learning', 'story', id, activeLearner?.id], queryFn: () => learningApi.story(id, activeLearner!.id), enabled: id > 0 && !!activeLearner })
  const gateways = useQuery({ queryKey: ['billing', 'subscription-gateways'], queryFn: billingApi.subscriptionGateways })
  const selectedGateway = gateway && gateways.data?.available.includes(gateway) ? gateway : gateways.data?.default

  useEffect(() => {
    const purchaseId = Number(params.get('purchase_id'))
    if (!purchaseId || !activeLearner) return
    let cancelled = false
    learningApi.confirmStoryPurchase(purchaseId).then(async (purchase) => {
      if (cancelled) return
      if (purchase.status === 'paid') {
        await qc.invalidateQueries({ queryKey: ['learning', 'story', id, activeLearner.id] })
        await qc.invalidateQueries({ queryKey: ['learning', 'stories'] })
        setNotice('Purchase restored. Your full story is ready.')
        const next = new URLSearchParams(params); next.delete('purchase_id'); setParams(next, { replace: true })
      } else { setPendingPurchaseId(purchaseId); setNotice('Payment is still being verified. You can check again shortly.') }
    }).catch(() => { if (!cancelled) setError('Could not verify the payment yet. Please try again.') })
    return () => { cancelled = true }
  }, [activeLearner, id, params, qc, setParams])

  async function purchase() {
    if (!activeLearner || !selectedGateway) return
    setBusy(true); setError(null)
    try {
      const result = await learningApi.purchaseStory(id, activeLearner.id, selectedGateway)
      if (result.status === 'paid') { await qc.invalidateQueries({ queryKey: ['learning', 'story', id] }); setNotice('This story is already yours.') }
      else if (result.checkout_url) window.location.assign(result.checkout_url)
      else { setPendingPurchaseId(result.id); setNotice('Checkout is being prepared. No new payment has been requested.') }
    } catch (err) { setError(err instanceof ApiError ? err.message : 'Could not start checkout.') }
    finally { setBusy(false) }
  }

  async function checkPending() {
    if (!pendingPurchaseId || !activeLearner) return
    setBusy(true); setError(null)
    try {
      const result = await learningApi.confirmStoryPurchase(pendingPurchaseId)
      if (result.status === 'paid') {
        await qc.invalidateQueries({ queryKey: ['learning', 'story', id, activeLearner.id] })
        setPendingPurchaseId(null); setNotice('Purchase verified. Your story is ready.')
      } else setNotice('Payment has not been confirmed yet. You can try checkout again with the same reference.')
    } catch (err) { setError(err instanceof ApiError ? err.message : 'Could not check this purchase.') }
    finally { setBusy(false) }
  }

  if (!activeLearner) return <Alert variant="info">Choose a learner profile to open this story.</Alert>
  if (story.isLoading) return <Skeleton className="h-64" />
  if (story.isError || !story.data) return <Alert variant="danger">Could not load this story for this learner.</Alert>
  const item = story.data
  return <div className="mx-auto flex max-w-4xl flex-col gap-6">
    <header>
      <button className="text-sm font-semibold text-primary hover:underline" onClick={() => navigate('/learn/stories')}>← Stories & Folklore</button>
      <div className="mt-3 flex flex-wrap items-center gap-3"><h1 className="font-display text-3xl font-bold">{item.title}</h1><Badge variant={item.purchased ? 'success' : 'neutral'}>{item.purchased ? 'Owned' : 'Free preview'}</Badge></div>
      <p className="mt-1 text-muted">{item.course_title} · {item.language.toUpperCase()} · {item.format}</p>
      {item.summary && <p className="mt-3">{item.summary}</p>}
    </header>
    {error && <Alert variant="danger">{error}</Alert>}{notice && <Alert variant="info">{notice}</Alert>}
    {item.preview_video_url && <section><h2 className="mb-2 font-display text-lg font-bold">Preview</h2><video controls preload="metadata" src={item.preview_video_url} className="w-full rounded-2xl bg-black" /></section>}
    {item.full_video_url && <section><h2 className="mb-2 font-display text-lg font-bold">Full story</h2><video controls preload="metadata" src={item.full_video_url} className="w-full rounded-2xl bg-black" /></section>}
    {item.pages.length > 0 && <div className="flex flex-col gap-5">{item.pages.map((page: StoryPage) => <article key={page.id} className="rounded-2xl border border-border bg-surface p-5">
      <p className="text-xs font-semibold uppercase text-primary">{item.format === 'reading' ? 'Chapter' : 'Page'} {page.position}</p>
      {page.title && <h2 className="mt-2 font-display text-xl font-bold">{page.title}</h2>}
      {page.image_url && <img src={page.image_url} alt={page.title ?? `Page ${page.position}`} className="mt-4 max-h-[32rem] w-full rounded-xl object-contain" />}
      {page.body && <p className="mt-4 whitespace-pre-wrap leading-relaxed">{page.body}</p>}
    </article>)}</div>}
    {!item.purchased && <section className="rounded-2xl border border-primary/30 bg-primary-soft p-5">
      <h2 className="font-display text-xl font-bold">Unlock the full story for ₦500</h2>
      <p className="mt-1 text-sm text-muted">One purchase covers this story item for your family. It stays available after your subscription ends and restores when you sign in again. A refunded or charged-back purchase returns to the free preview.</p>
      {gateways.data && gateways.data.available.length > 1 && <label className="mt-4 block text-sm font-semibold">Payment provider<select className="mt-1 block min-h-11 rounded-xl border border-border bg-surface px-3" value={selectedGateway ?? ''} onChange={(event) => setGateway(event.target.value as Gateway)}>{gateways.data.available.map((value) => <option key={value} value={value}>{value === 'flutterwave' ? 'Flutterwave' : value === 'paystack' ? 'Paystack' : 'Monnify'}</option>)}</select></label>}
      {gateways.data && gateways.data.available.length === 0 && <Alert variant="warning">Payments are unavailable right now.</Alert>}
      <Button className="mt-4" loading={busy} disabled={!selectedGateway} onClick={purchase}>Buy this story · ₦500</Button>
      {pendingPurchaseId && <Button className="mt-4 ml-2" variant="secondary" loading={busy} onClick={checkPending}>Check payment</Button>}
    </section>}
  </div>
}
