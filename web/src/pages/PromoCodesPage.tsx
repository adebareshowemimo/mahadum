import { useState, type FormEvent } from 'react'
import { Alert, Button, Card, CardBody, CardHeader, CardTitle, Input } from '@/components/ui'
import { ApiError, type CreatePromoInput } from '@/lib/api'
import { useCreatePromo } from '@/lib/admin/queries'

export function PromoCodesPage() {
  const createPromo = useCreatePromo()
  const [values, setValues] = useState({
    code: '',
    discount_type: 'percent' as 'percent' | 'fixed',
    value: '',
    applicable_tier: '',
    valid_to: '',
    max_redemptions: '',
  })
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [created, setCreated] = useState<string | null>(null)

  function update<K extends keyof typeof values>(key: K, val: (typeof values)[K]) {
    setValues((v) => ({ ...v, [key]: val }))
  }

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setFieldErrors({})
    setFormError(null)
    setCreated(null)
    try {
      const payload: CreatePromoInput = {
        code: values.code.trim(),
        discount_type: values.discount_type,
        value: Number(values.value),
        applicable_tier: values.applicable_tier || undefined,
        valid_to: values.valid_to || undefined,
        max_redemptions: values.max_redemptions ? Number(values.max_redemptions) : undefined,
      }
      const res = await createPromo.mutateAsync(payload)
      setCreated(res.code)
      setValues({ code: '', discount_type: 'percent', value: '', applicable_tier: '', valid_to: '', max_redemptions: '' })
    } catch (err) {
      if (err instanceof ApiError) {
        setFieldErrors(err.fieldErrors)
        if (!Object.keys(err.fieldErrors).length) setFormError(err.message)
      } else {
        setFormError('Something went wrong. Please try again.')
      }
    }
  }

  return (
    <div className="mx-auto flex max-w-lg flex-col gap-6">
      <h1 className="font-display text-2xl font-bold text-foreground">Promo codes</h1>

      {created && <Alert variant="success" title="Promo code created">Code <strong>{created}</strong> is now active.</Alert>}

      <Card>
        <CardHeader>
          <CardTitle>Create a code</CardTitle>
        </CardHeader>
        <CardBody>
          <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
            {formError && <Alert variant="danger">{formError}</Alert>}
            <Input
              label="Code"
              value={values.code}
              onChange={(e) => update('code', e.target.value.toUpperCase())}
              error={fieldErrors.code}
              autoFocus
              required
            />
            <div className="grid grid-cols-2 gap-3">
              <label className="flex flex-col gap-1.5">
                <span className="text-sm font-semibold text-foreground">Discount type</span>
                <select
                  value={values.discount_type}
                  onChange={(e) => update('discount_type', e.target.value as 'percent' | 'fixed')}
                  className="h-11 rounded-xl border border-border-strong bg-surface px-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                >
                  <option value="percent">Percent (%)</option>
                  <option value="fixed">Fixed (₦)</option>
                </select>
              </label>
              <Input
                label={values.discount_type === 'percent' ? 'Value (%)' : 'Value (₦)'}
                type="number"
                min={1}
                value={values.value}
                onChange={(e) => update('value', e.target.value)}
                error={fieldErrors.value}
                required
              />
            </div>
            <Input
              label="Applicable tier (optional)"
              value={values.applicable_tier}
              onChange={(e) => update('applicable_tier', e.target.value)}
              error={fieldErrors.applicable_tier}
              placeholder="e.g. school"
            />
            <div className="grid grid-cols-2 gap-3">
              <Input
                label="Valid until (optional)"
                type="date"
                value={values.valid_to}
                onChange={(e) => update('valid_to', e.target.value)}
                error={fieldErrors.valid_to}
              />
              <Input
                label="Max redemptions"
                type="number"
                min={1}
                value={values.max_redemptions}
                onChange={(e) => update('max_redemptions', e.target.value)}
                error={fieldErrors.max_redemptions}
              />
            </div>
            <Button type="submit" loading={createPromo.isPending}>
              Create promo code
            </Button>
          </form>
        </CardBody>
      </Card>
    </div>
  )
}
