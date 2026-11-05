import { useEffect, useState } from 'react'
import { useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Alert, Badge, Button, Skeleton } from '@/components/ui'
import { ApiError, billingApi, learningApi } from '@/lib/api'
import { useActiveProfile } from '@/lib/profile/ActiveProfile'
import { StoryContent } from '@/components/story/StoryContent'
import { StoryQuiz } from '@/components/story/StoryQuiz'

type Gateway = 'monnify' | 'paystack' | 'flutterwave'

export function StoryReaderPage() {
  const id = Number(useParams().storyId)
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const qc = useQueryClient()
  const { activeLearner } = useActiveProfile()
  const [gateway, setGateway] = useState<Gateway | null>(null)
  const [busy, setBusy] = useState(false)
  const [pendingPurchaseId, setPendingPurchaseId] = useState<number | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const story = useQuery({ queryKey: ['learning', 'story', id, activeLearner?.id], queryFn: () => learningApi.story(id, activeLearner!.id), enabled: id > 0 && !!activeLearner })
  const gateways = useQuery({ queryKey: ['billing', 'subscription-gateways'], queryFn: billingApi.subscriptionGateways, enabled: !!story.data?.can_purchase })
  const selectedGateway = gateway && gateways.data?.available.includes(gateway) ? gateway : gateways.data?.default

  useEffect(() => {
    const purchaseId = Number(params.get('purchase_id')?.match(/^([1-9]\d*)(?:\?.*)?$/)?.[1])
    if (Number.isSafeInteger(purchaseId) && purchaseId > 0) navigate(`/payment/story/${purchaseId}`, { replace: true })
  }, [navigate, params])

  useEffect(() => {
    if (!story.data || !activeLearner) return
    void learningApi.saveStoryProgress(id, activeLearner.id, story.data.resume_position ?? 1)
      .then(() => qc.invalidateQueries({ queryKey: ['learning', 'stories', 'continue', activeLearner.id] }))
      .catch(() => undefined)
  }, [activeLearner, id, qc, story.data])

  async function purchase() {
    if (!activeLearner || !selectedGateway) return
    setBusy(true); setError(null)
    try {
      const result = await learningApi.purchaseStory(id, activeLearner.id, selectedGateway)
      if (result.status === 'paid') { await qc.invalidateQueries({ queryKey: ['learning', 'story', id, activeLearner.id] }); navigate(`/payment/story/${result.id}`) }
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
  const previewUnit = item.format === 'quiz' ? 'question' : item.format === 'text' || item.format === 'reading' ? 'chapter' : 'page'
  const paywall = !item.purchased ? <section className="rounded-2xl border border-amber-700/30 bg-[#fffaf0] p-6 text-center shadow-[0_12px_40px_rgba(48,32,11,.08)]">
      <span className="text-2xl" aria-hidden="true">✦</span>
      <h2 className="font-display text-xl font-bold">Unlock · ₦500</h2>
      <p className="mx-auto mt-2 max-w-2xl text-sm text-[#6b5b4a]">A parent’s purchase covers this story item for their family. An adult learner can buy it for their own account. It remains available after a subscription ends. A full refund or chargeback returns it to the free preview.</p>
      {!item.can_purchase && <p className="mx-auto mt-4 max-w-xl text-sm font-semibold text-amber-900">{activeLearner.is_child ? 'Ask a grown-up to unlock this story.' : 'Ask the learner’s parent to purchase this story. It will appear here when their payment is confirmed.'}</p>}
      {item.can_purchase && <>
      {gateways.data && gateways.data.available.length > 1 && <label className="mt-4 block text-sm font-semibold">Payment provider<select className="mx-auto mt-1 block min-h-11 rounded-xl border border-amber-900/30 bg-[#fffaf0] px-3 text-[#372d24]" value={selectedGateway ?? ''} onChange={(event) => setGateway(event.target.value as Gateway)}>{gateways.data.available.map((value) => <option key={value} value={value}>{value === 'flutterwave' ? 'Flutterwave' : value === 'paystack' ? 'Paystack' : 'Monnify'}</option>)}</select></label>}
      {gateways.data && gateways.data.available.length === 0 && <Alert variant="warning">Payments are unavailable right now.</Alert>}
      <Button className="mt-4" loading={busy} disabled={!selectedGateway} onClick={purchase}>Buy this story · ₦500</Button>
      {pendingPurchaseId && <Button className="mt-4 ml-2" variant="secondary" loading={busy} onClick={checkPending}>Check payment</Button>}
      </>}
    </section> : undefined
  return <div className="min-h-dvh bg-[#f3eee3] text-[#372d24]">
    <header className="sticky top-0 z-20 border-b border-amber-900/15 bg-[#fffaf0]/95 backdrop-blur"><div className="mx-auto flex max-w-4xl items-center justify-between gap-4 px-4 py-3"><div className="min-w-0"><p className="text-[11px] font-bold uppercase tracking-widest text-amber-800">{item.purchased ? 'Your story' : 'Free preview'}</p><p className="truncate font-display font-bold">{item.title}</p></div><Button size="sm" variant="secondary" onClick={() => navigate('/learn/stories')}>Close</Button></div></header>
    <main className="mx-auto flex max-w-4xl flex-col gap-6 px-4 py-8">
    <div className="text-center"><p className="text-xs font-bold uppercase tracking-[.2em] text-amber-800">{item.language.toUpperCase()} · Level {item.level}</p><div className="mt-3 flex flex-wrap items-center justify-center gap-3"><h1 className="font-serif text-3xl font-bold sm:text-4xl">{item.title}</h1><Badge variant={item.purchased ? 'success' : 'neutral'}>{item.purchased ? 'Owned' : 'Preview'}</Badge></div>{item.summary && <p className="mx-auto mt-3 max-w-2xl text-[#6b5b4a]">{item.summary}</p>}</div>
    {!item.purchased && <div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-900/20 bg-[#fffaf0] px-5 py-4">
      <p className="text-sm font-medium">{item.format === 'video' || item.format === 'animated' ? 'Watch the free preview clip.' : `Try ${item.preview_count} free ${previewUnit}${item.preview_count === 1 ? '' : 's'}.`}</p>
      {item.can_purchase ? <Button loading={busy} disabled={!selectedGateway} onClick={purchase}>Buy full story · ₦500</Button> : <span className="text-sm font-semibold">{activeLearner.is_child ? 'Ask a grown-up to buy this story.' : 'Purchase unavailable for this learner.'}</span>}
    </div>}
    {error && <Alert variant="danger">{error}</Alert>}{notice && <Alert variant="info">{notice}</Alert>}
    <p className="text-center text-sm font-bold uppercase tracking-widest text-amber-900">{item.format === 'quiz' ? 'Quiz' : item.format === 'video' || item.format === 'animated' ? 'Video' : 'Text'} format</p>
    {item.format === 'quiz' ? <StoryQuiz key={`${item.id}-${item.purchased}`} title={item.title} questions={item.questions} onAnswer={(question, optionIndex) => learningApi.answerStoryQuestion(id, activeLearner.id, question.id, optionIndex)} endContent={paywall} onExit={() => navigate('/learn/stories')} />
      : <StoryContent key={`${item.id}-${item.purchased}`} format={item.format} pages={item.pages} previewVideoUrl={item.preview_video_url} fullVideoUrl={item.full_video_url} initialIndex={Math.min((item.resume_position ?? 1) - 1, Math.max(item.pages.length - 1, 0))} onPositionChange={(position) => { void learningApi.saveStoryProgress(item.id, activeLearner.id, position).then(() => qc.invalidateQueries({ queryKey: ['learning', 'stories', 'continue', activeLearner.id] })).catch(() => undefined) }} endLabel={item.can_purchase ? 'Unlock story' : 'Story access'} tone="light" endContent={paywall} />}
    </main>
  </div>
}
