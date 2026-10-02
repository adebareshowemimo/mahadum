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

  function changed() {
    purchaseKey.current = null; setConsent(false); setError(null); setFields({})
  }
  function reset() {
    sessionStorage.removeItem(storageKey); setPurchaseId(null); setSelected(null); changed()
  }
  async function buy() {
    if (!selected || selected.amount_minor === null || !consent || busy) return
    setBusy(true); setError(null); setFields({})
    purchaseKey.current ??= crypto.randomUUID()
    try {
      const result = await billingApi.purchaseDataBundle({
        biller_code: billerCode, product_code: selected.product_code, phone_number: phone,
        amount_minor: selected.amount_minor, consent,
      }, purchaseKey.current)
      sessionStorage.setItem(storageKey, String(result.purchase_id)); setPurchaseId(result.purchase_id)
    } catch (err) {
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
    setBiller(code)
    setSelected(null)
    setSearch('')
    changed()
  }
  return { billers, billerCode, bundles, selected, setSelected, search, setSearch, phone, setPhone,
    consent, setConsent, busy, error, fields, purchaseId, purchase, status, catalogueError, results,
    changed, reset, buy, chooseNetwork }
}
