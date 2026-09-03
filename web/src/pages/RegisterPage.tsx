import { useEffect, useMemo, useState, type FormEvent } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { Alert, Button, Icon, Input } from '@/components/ui'
import { AuthLayout } from '@/components/auth/AuthLayout'
import { GoogleButton } from '@/components/auth/GoogleButton'
import { cn } from '@/lib/cn'
import { ApiError, classInvitationApi, type RegisterInput } from '@/lib/api'
import { useAuth } from '@/lib/auth/AuthProvider'
import { useDigitalAge } from '@/lib/config/useConfig'

type Step = 'account' | 'age' | 'guardian' | 'form'
type SignupAccountType = 'individual' | 'family' | 'educator_school' | 'institution'
type SignupMethod = 'email' | 'google'

const ACCOUNT_TYPES: Array<{
  value: SignupAccountType
  label: string
  description: string
  icon: 'users' | 'cap' | 'building'
}> = [
  {
    value: 'individual',
    label: 'Individual',
    description: 'Learn at your own pace with one personal learner profile.',
    icon: 'users',
  },
  {
    value: 'family',
    label: 'Family',
    description: 'Create a household and safe learner profiles for children.',
    icon: 'users',
  },
  {
    value: 'educator_school',
    label: 'Educator/School',
    description: 'Teach learners, manage classes, or set up a school.',
    icon: 'cap',
  },
  {
    value: 'institution',
    label: 'Institution',
    description: 'Run a learning programme across an organisation.',
    icon: 'building',
  },
]

function isOrganizationAccount(type: SignupAccountType | null): boolean {
  return type === 'educator_school' || type === 'institution'
}

function postSignupPath(type: SignupAccountType | null): string {
  if (type === 'individual') return '/learn'
  if (type === 'family') return '/home'
  return '/school'
}

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
  const [searchParams] = useSearchParams()
  const invitationToken = searchParams.get('class_invitation') ?? ''
  const referralCode = (searchParams.get('ref') ?? '').trim().toUpperCase()
  const invitation = useQuery({
    queryKey: ['class-invitation', invitationToken],
    queryFn: () => classInvitationApi.show(invitationToken),
    enabled: invitationToken.length === 64,
    retry: false,
  })
  const digitalAge = useDigitalAge()

  const [step, setStep] = useState<Step>(invitationToken ? 'age' : 'account')
  const [accountType, setAccountType] = useState<SignupAccountType | null>(null)
  const [accountTypeError, setAccountTypeError] = useState<string | null>(null)
  const [dob, setDob] = useState('')
  const [isGuardianFlow, setIsGuardianFlow] = useState(false)
  const [signupMethod, setSignupMethod] = useState<SignupMethod>('email')

  const [values, setValues] = useState({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
    organization_name: '',
  })
  const [consent, setConsent] = useState(false)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [ageError, setAgeError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  const age = useMemo(() => ageFromDob(dob), [dob])

  useEffect(() => {
    if (!invitation.data) return
    const parts = invitation.data.name.trim().split(/\s+/)
    setValues((value) => ({
      ...value,
      first_name: value.first_name || parts[0] || '',
      last_name: value.last_name || parts.slice(1).join(' '),
      email: invitation.data.email,
    }))
  }, [invitation.data])

  function update(field: keyof typeof values) {
    return (e: React.ChangeEvent<HTMLInputElement>) =>
      setValues((v) => ({ ...v, [field]: e.target.value }))
  }

  const stepLabels = invitationToken
    ? ['Age', 'Details']
    : isGuardianFlow
      ? ['Account', 'Age', 'Guardian', 'Details']
      : ['Account', 'Age', 'Details']
  const stepIndex = step === 'account'
    ? 0
    : step === 'age'
      ? invitationToken ? 0 : 1
      : step === 'form'
        ? stepLabels.length - 1
        : 2

  function continueFromAccount(e: FormEvent) {
    e.preventDefault()
    if (!accountType) {
      setAccountTypeError('Choose the account type that best fits you.')
      return
    }
    setAccountTypeError(null)
    setStep('age')
  }

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
      if (accountType && accountType !== 'family') {
        setAgeError(`You must be at least ${digitalAge} to create this account.`)
        return
      }
      if (invitationToken) {
        setAgeError(`Learners under ${digitalAge} cannot create their own login. Ask a parent or school administrator to create a managed learner profile, then your teacher can add it.`)
        return
      }
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
        account_type: invitationToken ? 'learner' : accountType ?? 'family',
        ...(!invitationToken && isOrganizationAccount(accountType)
          ? { organization_name: values.organization_name }
          : {}),
        ...(invitationToken ? { class_invitation_token: invitationToken } : {}),
        ...(referralCode && !invitationToken ? { referral_code: referralCode } : {}),
        // Adult owner: send their own DOB. Guardian flow's DOB belongs to the
        // child, so it's captured later when the child profile is added.
        ...(!isGuardianFlow && dob ? { date_of_birth: dob } : {}),
      }
      await register(payload)
      navigate(invitationToken ? '/learn' : postSignupPath(accountType), { replace: true })
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

  function validateGoogleSignup(): boolean {
    const errors: Record<string, string> = {}
    if (!values.phone.trim()) errors.phone = 'Enter your phone number.'
    if (isOrganizationAccount(accountType) && !values.organization_name.trim()) {
      errors.organization_name = accountType === 'institution'
        ? 'Enter your institution name.'
        : 'Enter your educator or school name.'
    }

    setFieldErrors(errors)
    setFormError(
      isGuardianFlow && !consent
        ? 'Please confirm you are the parent or guardian and consent to continue.'
        : null,
    )

    return Object.keys(errors).length === 0 && (!isGuardianFlow || consent)
  }

  const subtitle =
    step === 'account'
      ? 'Choose the space that fits you'
      : step === 'age'
      ? 'First, how old are you?'
      : step === 'guardian'
        ? 'A grown-up needs to help'
        : 'Almost there!'

  return (
    <AuthLayout
      eyebrow={step === 'account' ? 'One platform, built around your role' : step === 'age' ? 'A safe start for every learner' : step === 'guardian' ? 'Designed with guardians in control' : 'Your learning circle starts here'}
      title={accountType === 'educator_school' ? 'Create your Educator/School account.' : accountType === 'institution' ? 'Create your Institution account.' : 'Create your account.'}
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
          <Link to={invitationToken ? `/login?class_invitation=${invitationToken}` : '/login'} className="inline-flex min-h-11 items-center font-bold text-chore-700 hover:underline">
            Sign in
          </Link>
        </>
      }
    >
      <Stepper labels={stepLabels} index={stepIndex} />

      {referralCode && !invitationToken && (
        <p className="mb-4 rounded-xl border border-chore-200 bg-chore-50 px-4 py-3 text-sm font-semibold text-navy-800">
          You’re joining with a friend’s referral code <span className="font-mono font-bold">{referralCode}</span>.
        </p>
      )}

      <div key={step} className="animate-step-in">
        {step === 'account' && (
          <form onSubmit={continueFromAccount} className="flex flex-col gap-5" noValidate>
            <fieldset>
              <legend className="font-display text-lg font-extrabold text-navy-950">
                What type of account do you need?
              </legend>
              <p className="mt-1 text-sm font-semibold leading-relaxed text-navy-600">
                This sets up the right workspace. You can invite other people after signup.
              </p>
              <div className="mt-4 flex flex-col gap-3">
                {ACCOUNT_TYPES.map((option) => {
                  const selected = accountType === option.value
                  return (
                    <label
                      key={option.value}
                      className={cn(
                        'flex min-h-20 cursor-pointer items-center gap-4 rounded-xl border-2 p-4 transition-colors',
                        'focus-within:outline-none focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2',
                        selected
                          ? 'border-primary bg-chore-50 text-navy-950'
                          : 'border-border bg-white text-navy-900 hover:border-chore-300 hover:bg-chore-50/40',
                      )}
                    >
                      <input
                        type="radio"
                        name="account_type"
                        value={option.value}
                        checked={selected}
                        onChange={() => {
                          setAccountType(option.value)
                          setAccountTypeError(null)
                        }}
                        className="sr-only"
                      />
                      <span
                        aria-hidden="true"
                        className={cn(
                          'flex size-11 shrink-0 items-center justify-center rounded-full',
                          selected ? 'bg-primary text-white' : 'bg-surface-muted text-chore-700',
                        )}
                      >
                        <Icon name={option.icon} className="size-5" />
                      </span>
                      <span className="min-w-0 flex-1">
                        <span className="block font-display text-base font-extrabold">{option.label}</span>
                        <span className="mt-0.5 block text-sm font-semibold leading-snug text-navy-600">
                          {option.description}
                        </span>
                      </span>
                      <span
                        aria-hidden="true"
                        className={cn(
                          'size-5 shrink-0 rounded-full border-2',
                          selected ? 'border-[6px] border-primary bg-white' : 'border-border-strong bg-white',
                        )}
                      />
                    </label>
                  )
                })}
              </div>
              {accountTypeError && <p className="mt-3 text-sm font-semibold text-danger" role="alert">{accountTypeError}</p>}
            </fieldset>
            <Button type="submit" fullWidth size="lg" variant="parent">
              Continue
            </Button>
          </form>
        )}

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
            {!invitationToken && (
              <Button type="button" variant="ghost" fullWidth onClick={() => setStep('account')}>
                ← Back to account type
              </Button>
            )}
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
          <form
            onSubmit={signupMethod === 'email' || invitationToken ? onSubmit : (e) => e.preventDefault()}
            className="flex flex-col gap-4"
            noValidate
          >
            {formError && <Alert variant="danger">{formError}</Alert>}

            {isGuardianFlow && (
              <Alert variant="info" title="Parent / guardian details">
                You’re setting up the account. Add your child’s profile from the dashboard once you’re in.
              </Alert>
            )}

            {!invitationToken && (
              <fieldset>
                <legend className="font-display text-base font-extrabold text-navy-950">
                  How would you like to sign up?
                </legend>
                <div className="mt-3 grid grid-cols-2 rounded-xl bg-surface-muted p-1">
                  {([
                    ['email', 'Email & password'],
                    ['google', 'Google'],
                  ] as const).map(([value, label]) => (
                    <label
                      key={value}
                      className={cn(
                        'flex min-h-11 cursor-pointer items-center justify-center rounded-lg px-3 text-center text-sm font-bold transition-colors',
                        'focus-within:outline-none focus-within:ring-2 focus-within:ring-ring',
                        signupMethod === value
                          ? 'bg-white text-chore-700 shadow-sm'
                          : 'text-navy-600 hover:text-navy-900',
                      )}
                    >
                      <input
                        type="radio"
                        name="signup_method"
                        value={value}
                        checked={signupMethod === value}
                        onChange={() => {
                          setSignupMethod(value)
                          setFormError(null)
                          setFieldErrors({})
                        }}
                        className="sr-only"
                      />
                      {label}
                    </label>
                  ))}
                </div>
              </fieldset>
            )}

            {signupMethod === 'google' && !invitationToken && (
              <Alert variant="info" title="Google provides your identity">
                Google will provide your verified name and email. We only need the details below to finish setting up your account.
              </Alert>
            )}

            {(signupMethod === 'email' || invitationToken) && (
              <>
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
                  readOnly={!!invitationToken}
                  error={fieldErrors.email}
                  required
                />
              </>
            )}

            {!invitationToken && isOrganizationAccount(accountType) && (
              <Input
                label={accountType === 'institution' ? 'Institution name' : 'Educator or school name'}
                autoComplete="organization"
                value={values.organization_name}
                onChange={update('organization_name')}
                error={fieldErrors.organization_name}
                hint="Your workspace will remain pending until platform verification is complete."
                required
              />
            )}

            <Input
              label="Phone number"
              type="tel"
              autoComplete="tel"
              hint="Used for airtime billing and account recovery."
              value={values.phone}
              onChange={update('phone')}
              error={fieldErrors.phone}
              autoFocus={signupMethod === 'google'}
              required
            />

            {(signupMethod === 'email' || invitationToken) && (
              <>
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
              </>
            )}

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

            {(signupMethod === 'email' || invitationToken) ? (
              <Button type="submit" fullWidth size="lg" variant="parent" loading={submitting}>
                Create account
              </Button>
            ) : (
              <GoogleButton
                label="Continue with Google"
                onBeforeStart={validateGoogleSignup}
                signupContext={{
                  account_type: accountType ?? 'family',
                  phone: values.phone.trim(),
                  ...(!isGuardianFlow && dob ? { date_of_birth: dob } : {}),
                  ...(isOrganizationAccount(accountType) ? { organization_name: values.organization_name.trim() } : {}),
                  ...(referralCode ? { referral_code: referralCode } : {}),
                }}
                onSuccess={() => navigate(postSignupPath(accountType), { replace: true })}
                onError={(msg) => setFormError(msg)}
              />
            )}

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
