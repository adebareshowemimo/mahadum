import { useMemo, useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { Alert, Button, Input } from '@/components/ui'
import { AuthLayout } from '@/components/auth/AuthLayout'
import { GoogleButton, OrDivider } from '@/components/auth/GoogleButton'
import { cn } from '@/lib/cn'
import { ApiError, type RegisterInput } from '@/lib/api'
import { useAuth } from '@/lib/auth/AuthProvider'
import { useDigitalAge } from '@/lib/config/useConfig'

// Everyone registers as the family/account owner (a "parent"). Whether they
// learn themselves or add children is handled afterwards via learner profiles,
// so there's no account-type choice at sign-up.
type Step = 'age' | 'guardian' | 'form'

function ageFromDob(dob: string): number | null {
  if (!dob) return null
  const d = new Date(dob)
  if (Number.isNaN(d.getTime())) return null
  const now = new Date()
  let age = now.getFullYear() - d.getFullYear()
  const m = now.getMonth() - d.getMonth()
  if (m < 0 || (m === 0 && now.getDate() < d.getDate())) age--
  return age
}

export function RegisterPage() {
  const { register } = useAuth()
  const navigate = useNavigate()
  const digitalAge = useDigitalAge()

  const [step, setStep] = useState<Step>('age')
  const [dob, setDob] = useState('')
  const [isGuardianFlow, setIsGuardianFlow] = useState(false)

  const [values, setValues] = useState({
    first_name: '',
    last_name: '',
    email: '',
    password: '',
    password_confirmation: '',
  })
  const [consent, setConsent] = useState(false)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [ageError, setAgeError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  const age = useMemo(() => ageFromDob(dob), [dob])

  function update(field: keyof typeof values) {
    return (e: React.ChangeEvent<HTMLInputElement>) =>
      setValues((v) => ({ ...v, [field]: e.target.value }))
  }

  const stepLabels = isGuardianFlow ? ['Age', 'Guardian', 'Details'] : ['Age', 'Details']
  const stepIndex = step === 'age' ? 0 : step === 'form' ? stepLabels.length - 1 : 1

  function continueFromAge(e: FormEvent) {
    e.preventDefault()
    setAgeError(null)
    if (age == null) {
      setAgeError('Please enter your date of birth.')
      return
    }
    if (age < 0 || age > 120) {
      setAgeError('That date doesn’t look right — please check it.')
      return
    }
    if (age < digitalAge) {
      setIsGuardianFlow(true)
      setStep('guardian')
    } else {
      setIsGuardianFlow(false)
      setStep('form')
    }
  }

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    if (isGuardianFlow && !consent) {
      setFormError('Please confirm you are the parent or guardian and consent to continue.')
      return
    }
    setSubmitting(true)
    setFormError(null)
    setFieldErrors({})
    try {
      const payload: RegisterInput = {
        first_name: values.first_name,
        last_name: values.last_name,
        email: values.email,
        password: values.password,
        password_confirmation: values.password_confirmation,
        account_type: 'parent',
        // Adult owner: send their own DOB. Guardian flow's DOB belongs to the
        // child, so it's captured later when the child profile is added.
        ...(!isGuardianFlow && dob ? { date_of_birth: dob } : {}),
      }
      await register(payload)
      navigate('/home', { replace: true })
    } catch (err) {
      if (err instanceof ApiError) {
        setFieldErrors(err.fieldErrors)
        if (!Object.keys(err.fieldErrors).length) setFormError(err.message)
      } else {
        setFormError('Something went wrong. Please try again.')
      }
    } finally {
      setSubmitting(false)
    }
  }

  const subtitle =
    step === 'age'
      ? 'First, how old are you?'
      : step === 'guardian'
        ? 'A grown-up needs to help'
        : 'Almost there!'

  return (
    <AuthLayout
      title="Create your account"
      subtitle={subtitle}
      footer={
        <>
          Already have an account?{' '}
          <Link to="/login" className="font-semibold text-primary hover:underline">
            Sign in
          </Link>
        </>
      }
    >
      <Stepper labels={stepLabels} index={stepIndex} />

      <div key={step} className="animate-step-in">
        {step === 'age' && (
          <form onSubmit={continueFromAge} className="flex flex-col gap-4" noValidate>
            <div className="flex flex-col items-center gap-1 py-2 text-center">
              <span className="text-4xl" aria-hidden="true">
                {age == null ? '🎂' : age < digitalAge ? '🧒' : '🎉'}
              </span>
              {age != null && (
                <p className="animate-pop-in text-sm font-semibold text-foreground">
                  You’re {age} year{age === 1 ? '' : 's'} old
                </p>
              )}
            </div>
            <Input
              label="Date of birth"
              type="date"
              value={dob}
              max={new Date().toISOString().slice(0, 10)}
              onChange={(e) => {
                setDob(e.target.value)
                setAgeError(null)
              }}
              error={ageError ?? undefined}
              hint="We use this to keep younger learners safe."
              autoFocus
              required
            />
            <Button type="submit" fullWidth size="lg">
              Continue
            </Button>
          </form>
        )}

        {step === 'guardian' && (
          <div className="flex flex-col gap-4">
            <div className="flex flex-col items-center gap-3 rounded-2xl bg-primary-soft p-5 text-center">
              <span className="animate-pop-in text-4xl" aria-hidden="true">
                🧑‍🍼
              </span>
              <p className="text-sm text-foreground">
                Because you’re under <strong>{digitalAge}</strong>, a parent or guardian needs to
                create the account and give permission. They’ll add your learner profile in a moment —
                then you can dive in! 🚀
              </p>
            </div>
            <Button size="lg" fullWidth onClick={() => setStep('form')}>
              I’m a parent / guardian — continue
            </Button>
            <Button variant="ghost" fullWidth onClick={() => setStep('age')}>
              ← Change date of birth
            </Button>
          </div>
        )}

        {step === 'form' && (
          <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
            {formError && <Alert variant="danger">{formError}</Alert>}

            {isGuardianFlow && (
              <Alert variant="info" title="Parent / guardian details">
                You’re setting up the account. Add your child’s profile from the dashboard once you’re in.
              </Alert>
            )}

            <div className="grid grid-cols-2 gap-3">
              <Input
                label="First name"
                autoComplete="given-name"
                value={values.first_name}
                onChange={update('first_name')}
                error={fieldErrors.first_name}
                autoFocus
                required
              />
              <Input
                label="Last name"
                autoComplete="family-name"
                value={values.last_name}
                onChange={update('last_name')}
                error={fieldErrors.last_name}
                required
              />
            </div>

            <Input
              label="Email"
              type="email"
              autoComplete="email"
              value={values.email}
              onChange={update('email')}
              error={fieldErrors.email}
              required
            />

            <Input
              label="Password"
              type="password"
              autoComplete="new-password"
              hint="At least 8 characters."
              value={values.password}
              onChange={update('password')}
              error={fieldErrors.password}
              required
            />

            <Input
              label="Confirm password"
              type="password"
              autoComplete="new-password"
              value={values.password_confirmation}
              onChange={update('password_confirmation')}
              error={fieldErrors.password_confirmation}
              required
            />

            {isGuardianFlow && (
              <label className="flex items-start gap-2.5 text-sm text-foreground">
                <input
                  type="checkbox"
                  checked={consent}
                  onChange={(e) => setConsent(e.target.checked)}
                  className="mt-0.5 size-4 rounded border-border-strong text-primary focus:ring-ring"
                />
                <span>
                  I am the parent or legal guardian and I consent to creating and managing this child’s
                  account.
                </span>
              </label>
            )}

            <Button type="submit" fullWidth size="lg" loading={submitting}>
              Create account
            </Button>

            <OrDivider />
            <GoogleButton
              label="Sign up with Google"
              onSuccess={() => navigate('/home', { replace: true })}
              onError={(msg) => setFormError(msg)}
            />

            <Button
              type="button"
              variant="ghost"
              fullWidth
              onClick={() => setStep(isGuardianFlow ? 'guardian' : 'age')}
            >
              ← Back
            </Button>
          </form>
        )}
      </div>
    </AuthLayout>
  )
}

function Stepper({ labels, index }: { labels: string[]; index: number }) {
  return (
    <div className="mb-6 flex items-center gap-2" aria-label={`Step ${index + 1} of ${labels.length}`}>
      {labels.map((label, i) => (
        <div key={label} className="flex flex-1 flex-col gap-1.5">
          <div
            className={cn(
              'h-1.5 rounded-full transition-colors duration-300',
              i <= index ? 'bg-primary' : 'bg-surface-muted',
            )}
          />
          <span
            className={cn(
              'text-[11px] font-medium transition-colors',
              i === index ? 'text-primary' : 'text-subtle',
            )}
          >
            {label}
          </span>
        </div>
      ))}
    </div>
  )
}
