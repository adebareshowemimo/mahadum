import { useMemo, useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { Alert, Button, Icon, Input } from '@/components/ui'
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
    phone: '',
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
        phone: values.phone,
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
      eyebrow={step === 'age' ? 'A safe start for every learner' : step === 'guardian' ? 'Designed with guardians in control' : 'Your learning circle starts here'}
      title="Create your family account."
      subtitle={subtitle}
      image="/images/landing-v1-family-call.webp"
      imageAlt="Amara practising a family greeting with Iya while relatives join by video call"
      imagePosition="object-[68%_center]"
      visualTitle="Bring your family language into everyday life."
      visualBody="Start free, learn at your own pace, and give every learner a safe profile connected to the people guiding them."
      phrases={['Nnọọ', 'Báwo ni?', 'Sannu']}
      footer={
        <>
          Already have an account?{' '}
          <Link to="/login" className="inline-flex min-h-11 items-center font-bold text-chore-700 hover:underline">
            Sign in
          </Link>
        </>
      }
    >
      <Stepper labels={stepLabels} index={stepIndex} />

      <div key={step} className="animate-step-in">
        {step === 'age' && (
          <form onSubmit={continueFromAge} className="flex flex-col gap-4" noValidate>
            <div className="flex items-center gap-4 rounded-xl bg-chore-50 p-4">
              <span
                aria-hidden="true"
                className="flex size-12 shrink-0 items-center justify-center rounded-full bg-white font-display text-lg font-extrabold text-chore-700 shadow-sm"
              >
                {age == null ? '✓' : age}
              </span>
              <div>
                <p className="font-display font-extrabold text-navy-950">
                  {age == null ? 'We begin with safety.' : `You’re ${age} year${age === 1 ? '' : 's'} old.`}
                </p>
                <p className="mt-1 text-sm font-semibold text-navy-600">
                  Your age helps us choose the right account setup.
                </p>
              </div>
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
            <Button type="submit" fullWidth size="lg" variant="parent">
              Continue
            </Button>
          </form>
        )}

        {step === 'guardian' && (
          <div className="flex flex-col gap-4">
            <div className="flex flex-col items-center gap-3 rounded-xl bg-chore-50 p-6 text-center">
              <span className="animate-pop-in flex size-12 items-center justify-center rounded-full bg-white text-chore-700 shadow-sm">
                <Icon name="shield" className="size-6" />
              </span>
              <p className="text-sm font-semibold leading-relaxed text-navy-700">
                Because you’re under <strong>{digitalAge}</strong>, a parent or guardian needs to
                create the account and give permission. They’ll add your learner profile in a moment —
                then learning can begin.
              </p>
            </div>
            <Button size="lg" fullWidth variant="parent" onClick={() => setStep('form')}>
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

            <div className="grid gap-3 sm:grid-cols-2">
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
              label="Phone number"
              type="tel"
              autoComplete="tel"
              hint="Used for airtime billing and account recovery."
              value={values.phone}
              onChange={update('phone')}
              error={fieldErrors.phone}
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
              <label className="flex min-h-11 items-start gap-3 text-sm font-semibold leading-relaxed text-navy-700">
                <input
                  type="checkbox"
                  checked={consent}
                  onChange={(e) => setConsent(e.target.checked)}
                  className="mt-0.5 size-5 shrink-0 rounded border-border-strong text-primary focus:ring-ring"
                />
                <span>
                  I am the parent or legal guardian and I consent to creating and managing this child’s
                  account.
                </span>
              </label>
            )}

            <Button type="submit" fullWidth size="lg" variant="parent" loading={submitting}>
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
              'text-sm font-semibold transition-colors',
              i === index ? 'text-chore-700' : 'text-subtle',
            )}
          >
            {label}
          </span>
        </div>
      ))}
    </div>
  )
}
