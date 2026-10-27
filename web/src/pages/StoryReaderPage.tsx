import { useEffect, useState } from 'react'
import { useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Alert, Badge, Button, Skeleton } from '@/components/ui'
import { ApiError, billingApi, learningApi } from '@/lib/api'
import { useActiveProfile } from '@/lib/profile/ActiveProfile'
import { StoryContent } from '@/components/story/StoryContent'

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

  if (!activeLearner) return <div className="mx-auto max-w-4xl p-6"><Alert variant="info">Choose a learner profile to open this story.</Alert></div>
  if (story.isLoading) return <div className="mx-auto max-w-4xl p-6"><Skeleton className="h-64" /></div>
  if (story.isError || !story.data) return <div className="mx-auto max-w-4xl p-6"><Alert variant="danger">Could not load this story for this learner.</Alert></div>
  const item = story.data
  return <div className="min-h-dvh bg-[#f3eee3] text-[#372d24]">
    <header className="sticky top-0 z-20 border-b border-amber-900/15 bg-[#fffaf0]/95 backdrop-blur"><div className="mx-auto flex max-w-4xl items-center justify-between gap-4 px-4 py-3"><div className="min-w-0"><p className="text-[11px] font-bold uppercase tracking-widest text-amber-800">{item.purchased ? 'Your story' : 'Free preview'}</p><p className="truncate font-display font-bold">{item.title}</p></div><Button size="sm" variant="secondary" onClick={() => navigate('/learn/stories')}>Close</Button></div></header>
    <main className="mx-auto flex max-w-4xl flex-col gap-6 px-4 py-8">
    <div className="text-center"><p className="text-xs font-bold uppercase tracking-[.2em] text-amber-800">{item.course_title} · {item.language.toUpperCase()}</p><div className="mt-3 flex flex-wrap items-center justify-center gap-3"><h1 className="font-serif text-3xl font-bold sm:text-4xl">{item.title}</h1><Badge variant={item.purchased ? 'success' : 'neutral'}>{item.purchased ? 'Owned' : 'Preview'}</Badge></div>{item.summary && <p className="mx-auto mt-3 max-w-2xl text-[#6b5b4a]">{item.summary}</p>}</div>
    {error && <Alert variant="danger">{error}</Alert>}{notice && <Alert variant="info">{notice}</Alert>}
    <StoryContent format={item.format} pages={item.pages} previewVideoUrl={item.preview_video_url} fullVideoUrl={item.full_video_url} />
    {!item.purchased && <section className="rounded-2xl border border-amber-700/30 bg-[#fffaf0] p-6 text-center shadow-[0_12px_40px_rgba(48,32,11,.08)]">
      <span className="text-2xl" aria-hidden="true">✦</span>
      <h2 className="font-display text-xl font-bold">Unlock the full story for ₦500</h2>
      <p className="mx-auto mt-2 max-w-2xl text-sm text-[#6b5b4a]">One purchase covers this story item for your family. It stays available after your subscription ends and restores when you sign in again. A refunded or charged-back purchase returns to the free preview.</p>
      {gateways.data && gateways.data.available.length > 1 && <label className="mt-4 block text-sm font-semibold">Payment provider<select className="mt-1 block min-h-11 rounded-xl border border-border bg-surface px-3" value={selectedGateway ?? ''} onChange={(event) => setGateway(event.target.value as Gateway)}>{gateways.data.available.map((value) => <option key={value} value={value}>{value === 'flutterwave' ? 'Flutterwave' : value === 'paystack' ? 'Paystack' : 'Monnify'}</option>)}</select></label>}
      {gateways.data && gateways.data.available.length === 0 && <Alert variant="warning">Payments are unavailable right now.</Alert>}
      <Button className="mt-4" loading={busy} disabled={!selectedGateway} onClick={purchase}>Buy this story · ₦500</Button>
      {pendingPurchaseId && <Button className="mt-4 ml-2" variant="secondary" loading={busy} onClick={checkPending}>Check payment</Button>}
    </section>}
    </main>
  </div>
}
