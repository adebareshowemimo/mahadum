import { useEffect, useRef, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { ApiError, billingApi, type DataBundle } from '@/lib/api'
import { useDataBillers, useDataBundles } from './queries'
import { useAuth } from '@/lib/auth/AuthProvider'

export function dataStoreError(error: unknown): string {
  return error instanceof ApiError ? error.message : 'Could not reach the mobile data service. Please try again.'
}

export function useDataStore() {
  const { user } = useAuth()
  const storageKey = `mahadum:data-purchase:${user?.user.id}`
  const billers = useDataBillers()
  const [biller, setBiller] = useState('')
  const billerCode = biller || billers.data?.find((b) => b.code === 'MTN')?.code || billers.data?.[0]?.code || ''
  const bundles = useDataBundles(billerCode)
  const [selected, setSelected] = useState<DataBundle | null>(null)
  const [search, setSearch] = useState('')
  const [phone, setPhone] = useState('')
  const [consent, setConsent] = useState(false)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [fields, setFields] = useState<Record<string, string>>({})
  const [purchaseId, setPurchaseId] = useState<number | null>(null)
  const activitySession = useRef(crypto.randomUUID())
  function track(event: string, details: { value?: string; count?: number; product_code?: string } = {}) {
    void billingApi.dataPurchaseEvent({ event, session_id: activitySession.current,
      purchase_id: purchaseId ?? undefined, biller_code: billerCode || undefined,
      product_code: selected?.product_code, ...details }).catch(() => {})
  }
  const purchaseKey = useRef<string | null>(null)
  const purchase = useQuery({
    queryKey: ['data-purchase', user?.user.id, purchaseId],
    queryFn: () => billingApi.dataBundlePurchase(purchaseId!),
    enabled: purchaseId !== null, retry: false,
    refetchInterval: (query) => ['success', 'failed', 'payment_failed', 'needs_review'].includes(query.state.data?.status ?? '') ? false : 5000,
  })
  useEffect(() => {
    const saved = Number(sessionStorage.getItem(storageKey))
    setPurchaseId(Number.isSafeInteger(saved) && saved > 0 ? saved : null)
  }, [storageKey])

  useEffect(() => { track('page_viewed') }, [])
  useEffect(() => {
    if (bundles.data) track('catalogue_loaded', { count: bundles.data.length })
  }, [bundles.data, billerCode])
  useEffect(() => {
    if (billers.error || bundles.error) track('catalogue_failed')
  }, [billers.error, bundles.error])
  useEffect(() => {
    if (purchase.data?.status) track('status_viewed', { value: purchase.data.status })
  }, [purchase.data?.status])
  useEffect(() => {
    if (!search) return
    const timer = setTimeout(() => track('search_changed', { count: search.length }), 700)
    return () => clearTimeout(timer)
  }, [search])

  function changed() {
    purchaseKey.current = null; setConsent(false); setError(null); setFields({})
  }
  function reset() {
    track('reset_clicked')
    sessionStorage.removeItem(storageKey); setPurchaseId(null); setSelected(null); changed()
  }
  async function buy() {
    if (!selected || selected.amount_minor === null || !consent || busy) return
    track('checkout_clicked')
    setBusy(true); setError(null); setFields({})
    purchaseKey.current ??= crypto.randomUUID()
    try {
      const result = await billingApi.purchaseDataBundle({
        biller_code: billerCode, product_code: selected.product_code, phone_number: phone,
        amount_minor: selected.amount_minor, consent,
      }, purchaseKey.current)
      sessionStorage.setItem(storageKey, String(result.purchase_id)); setPurchaseId(result.purchase_id)
    } catch (err) {
      track('checkout_failed', { value: err instanceof ApiError ? String(err.status) : 'connection' })
      setError(dataStoreError(err))
      if (err instanceof ApiError) {
        setFields(err.fieldErrors)
        if (err.status === 422) {
          purchaseKey.current = null
          setSelected(null)
          setConsent(false)
          void bundles.refetch()
        }
      }
    } finally { setBusy(false) }
  }
  const results = bundles.data?.filter((b) => b.name.toLowerCase().includes(search.toLowerCase())) ?? []
  const status = purchase.data?.status
  const catalogueError = billers.error || bundles.error
  function chooseNetwork(code: string) {
    track('network_selected', { value: code })
    setBiller(code)
    setSelected(null)
    setSearch('')
    changed()
  }
  return { billers, billerCode, bundles, selected, setSelected, search, setSearch, phone, setPhone,
    consent, setConsent, busy, error, fields, purchaseId, purchase, status, catalogueError, results,
    changed, reset, buy, chooseNetwork, track }
}
