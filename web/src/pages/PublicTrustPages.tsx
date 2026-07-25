import { useMemo, useState, type FormEvent } from 'react'
import { useSearchParams } from 'react-router-dom'
import {
  CapabilityFlow,
  PolicyPage,
  PublicClosing,
  PublicPageShell,
} from '@/components/landing/PublicPagePrimitives'
import { Reveal } from '@/components/landing/Reveal'
import { Button, Icon, Input, LinkButton, Textarea, type IconName } from '@/components/ui'

const UPDATED = '25 July 2026'
const SUPPORT_EMAIL = 'support@mahadum360.app'

const PRIVACY_SECTIONS = [
  {
    id: 'scope',
    title: 'Who this notice covers',
    paragraphs: [
      'This notice covers visitors, adult learners, parents and guardians, learner profiles, teachers, school staff, institutional partners and other people who use or interact with MAHADUM.360.',
      'A child under 13 does not need a separate login. A parent or authorised organisation creates and supervises the learner profile and controls the associated account.',
    ],
  },
  {
    id: 'data',
    title: 'Information we collect',
    paragraphs: ['The information we collect depends on how you use the service.'],
    bullets: [
      'Account and contact information, such as name, email address, phone number and sign-in details.',
      'Learner-profile information, such as display name, age band, selected language and learning preferences.',
      'Learning activity, including lesson progress, quiz responses, speaking submissions, assignments, badges and approved rewards.',
      'Family, school and organisation information needed to provide supervised learning, rosters, reporting and billing.',
      'Payment, subscription and transaction references. Payment-card details are handled by the relevant payment provider, not stored as ordinary profile data.',
      'Device, connection, security and usage information needed to operate, protect and improve the service.',
      'Messages and support information you choose to send to us.',
    ],
  },
  {
    id: 'use',
    title: 'How we use information',
    paragraphs: ['We use information to provide the service people asked for and to operate it responsibly.'],
    bullets: [
      'Create accounts and supervised learner profiles.',
      'Deliver lessons, audio, speaking practice, assignments, progress and rewards.',
      'Give parents, teachers and authorised organisation staff the appropriate view of learner activity.',
      'Process subscriptions, invoices, refunds, commissions and other authorised transactions.',
      'Prevent abuse, investigate reported concerns, secure accounts and maintain audit records.',
      'Provide support, service messages and communications you have requested.',
      'Understand performance and improve accessibility, reliability and learning experiences.',
    ],
  },
  {
    id: 'sharing',
    title: 'When information is shared',
    paragraphs: [
      'We do not sell personal information. We share information only when it is needed to provide the service, follow an authorised relationship, protect people and the platform, or meet a legal obligation.',
    ],
    bullets: [
      'With a parent or guardian supervising a learner profile.',
      'With authorised teachers, school administrators or institutional staff where the learner participates through that organisation.',
      'With service providers that support hosting, communications, payments, analytics, security or customer support under appropriate obligations.',
      'With regulators, law enforcement or other parties where disclosure is legally required or necessary to protect rights and safety.',
      'As part of a properly managed business transaction, subject to applicable safeguards.',
    ],
  },
  {
    id: 'retention',
    title: 'Retention and security',
    paragraphs: [
      'We keep information only for as long as it is needed for the purposes described here, to provide an active service, resolve disputes, maintain financial and audit records, or meet legal requirements. Retention periods differ by record type and relationship.',
      'We use administrative, technical and organisational safeguards designed to protect information. No online service can promise absolute security, so please use a strong password and contact us promptly if you believe an account or learner profile is at risk.',
    ],
  },
  {
    id: 'rights',
    title: 'Choices and privacy rights',
    paragraphs: [
      'Depending on where you live and the nature of our relationship, you may be able to request access, correction, deletion, restriction, objection, portability or withdrawal of consent. Parents and guardians may make appropriate requests for supervised learner profiles.',
      'We may need to verify identity and authority before completing a request. Some records may need to be retained where the law or an active contractual relationship requires it.',
    ],
  },
  {
    id: 'international',
    title: 'International use',
    paragraphs: [
      'MAHADUM.360 serves people in Nigeria and the diaspora. Information may therefore be processed in countries other than the one where you live. Where required, we use contractual and organisational measures intended to protect information across borders.',
    ],
  },
  {
    id: 'contact',
    title: 'Questions and complaints',
    paragraphs: [
      `Email ${SUPPORT_EMAIL} with “Privacy” in the subject line to ask a question, exercise a privacy right or raise a concern. Please do not include passwords, full payment details or unnecessary information in your message.`,
      'You may also have the right to contact the relevant data-protection authority in your country. This notice may be updated as the service, law or our practices change.',
    ],
  },
]

const TERMS_SECTIONS = [
  {
    id: 'agreement',
    title: 'Using MAHADUM.360',
    paragraphs: [
      'These terms govern use of the MAHADUM.360 website, learning service and related features. By creating an account, purchasing a plan or using an organisation-provided account, you agree to follow these terms and any plan or organisation terms that apply.',
      'If you use the service for a school or institution, you confirm that you have authority to act for that organisation.',
    ],
  },
  {
    id: 'accounts',
    title: 'Accounts and learner profiles',
    paragraphs: [
      'Provide accurate information, protect account credentials and tell us promptly about suspected unauthorised access. You are responsible for activity carried out through your account unless the law says otherwise.',
      'Learners under 13 use supervised profiles rather than independent logins. The parent, guardian or authorised organisation is responsible for creating and supervising that profile and for providing any required consent.',
    ],
  },
  {
    id: 'learning',
    title: 'Learning access',
    paragraphs: [
      'The Free plan includes complete learning content. Paid plans add convenience or additional services and do not remove a learner’s basic ability to continue learning when a paid plan ends.',
      'Courses, language availability, lesson order and features may change as content is corrected, improved or expanded. We aim to communicate material service changes appropriately.',
    ],
  },
  {
    id: 'conduct',
    title: 'Acceptable use',
    paragraphs: ['Use the service for lawful learning, teaching, family and programme purposes.'],
    bullets: [
      'Do not harass, exploit, impersonate or endanger another person.',
      'Do not attempt to bypass access controls, probe security or interfere with service operation.',
      'Do not upload unlawful, abusive, infringing or malicious material.',
      'Do not scrape, resell or reproduce protected content except where a written agreement permits it.',
      'Do not manipulate rewards, competitions, referrals, payments or reporting.',
    ],
  },
  {
    id: 'payments',
    title: 'Plans, payments and cancellation',
    paragraphs: [
      'Prices, billing frequency, included features and applicable taxes are shown before purchase or set out in an organisation agreement. Payment providers may apply their own terms.',
      'You may cancel a recurring consumer plan through the available account or support route. Access to paid conveniences normally continues until the end of the paid billing period unless law or the purchase terms require otherwise. Refund rights depend on the transaction, service delivery and applicable law.',
    ],
  },
  {
    id: 'rewards',
    title: 'Coins, rewards and competitions',
    paragraphs: [
      'Coins, XP, badges and similar learning rewards are platform features, not cash or stored value, unless a specific promotion clearly states otherwise. Chore coins are released only after parent approval.',
      'Competition or promotional rules may add eligibility, timing, judging, prize and conduct terms. Those specific rules apply alongside these terms.',
    ],
  },
  {
    id: 'organisations',
    title: 'Schools and institutions',
    paragraphs: [
      'Organisation administrators control authorised seats, roles, rosters and programme access. A separate order, proposal or service agreement may define pricing, term, data responsibilities, support and programme requirements.',
      'Where an organisation supplies or controls an account, its authorised administrators may access information and learning activity according to their role and the applicable agreement.',
    ],
  },
  {
    id: 'content',
    title: 'Content and intellectual property',
    paragraphs: [
      'MAHADUM.360 and its licensors retain rights in the platform, curriculum, audio, artwork, software and brand materials. We grant users a limited, revocable, non-transferable right to use the service for its intended purpose.',
      'You retain rights in content you submit. You give us the limited permission needed to host, process and display it for service delivery, safety, support and the authorised learning relationship.',
    ],
  },
  {
    id: 'changes',
    title: 'Suspension, changes and responsibility',
    paragraphs: [
      'We may restrict or suspend access where reasonably necessary for safety, security, non-payment, legal compliance or serious breach of these terms. Where appropriate, we will provide notice and a way to contact us.',
      'We work to keep the service available and accurate, but online services can experience interruptions and learning outcomes vary. Nothing in these terms excludes rights or responsibilities that cannot legally be excluded.',
    ],
  },
  {
    id: 'law',
    title: 'Governing terms and contact',
    paragraphs: [
      'These terms are intended to operate under applicable Nigerian law, while preserving mandatory consumer and privacy rights that may apply where a user lives. Organisation agreements may specify additional governing-law and dispute terms.',
      `Questions about these terms can be sent to ${SUPPORT_EMAIL}. We may update these terms when the service or legal requirements change; the current version and update date will remain available on this page.`,
    ],
  },
]

export function PrivacyPage() {
  return (
    <PolicyPage
      title="Privacy notice"
      summary="How MAHADUM.360 handles account, learner, family, school and institutional information—including the additional care required for supervised child profiles."
      updated={UPDATED}
      sections={PRIVACY_SECTIONS}
    />
  )
}

export function TermsPage() {
  return (
    <PolicyPage
      title="Terms and conditions"
      summary="The ground rules for accounts, supervised learner profiles, learning access, subscriptions, rewards, organisations and responsible use of MAHADUM.360."
      updated={UPDATED}
      sections={TERMS_SECTIONS}
    />
  )
}

const SAFETY_FLOW = [
  { title: 'Parent creates', body: 'The adult creates the account and adds an age-appropriate learner profile.', icon: 'users' as const },
  { title: 'Child learns', body: 'The learner uses lessons and activities without managing payments or an adult account.', icon: 'book' as const },
  { title: 'Adult reviews', body: 'The parent sees progress, hears submitted practice and manages consent.', icon: 'clipboard' as const },
  { title: 'Rewards release', body: 'Chore coins stay pending until the parent approves the completed task.', icon: 'shield' as const },
]

export function ChildSafetyPage() {
  return (
    <PublicPageShell mainId="child-safety-main">
      <section className="bg-[#eef6ff]">
        <div className="mx-auto grid max-w-[90rem] lg:grid-cols-[0.92fr_1.08fr] lg:items-stretch">
          <div className="motion-hero-copy flex flex-col justify-center px-4 py-16 sm:px-6 lg:px-10 lg:py-24">
            <p className="font-display text-xl font-extrabold text-[#a93403]">Kids get the adventure. Parents keep the controls.</p>
            <h1 className="mt-4 max-w-[12ch] font-display text-[clamp(3rem,6vw,5.5rem)] font-extrabold leading-[0.98] tracking-[-0.035em]">
              Child safety is part of the learning design.
            </h1>
            <p className="mt-6 max-w-2xl text-lg leading-relaxed text-navy-700 sm:text-xl">
              Under-13 learners use supervised profiles—not independent logins. Parents and authorised schools control the account, consent and sensitive actions.
            </p>
            <LinkButton to="/register" variant="parent" size="lg" className="mt-8 self-start">Create a parent account</LinkButton>
          </div>
          <img
            src="/images/archive/campaign-family-anywhere.webp"
            alt="A parent and two teenagers sharing a supervised language lesson with their grandmother"
            className="motion-hero-media min-h-[28rem] size-full object-cover"
          />
        </div>
      </section>

      <CapabilityFlow
        heading="A clear line between learning and adult controls."
        intro="Children can concentrate on language. The adult around them manages identity, consent, review and rewards."
        items={SAFETY_FLOW}
      />

      <section className="bg-navy-950 py-16 text-white sm:py-24">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <Reveal className="max-w-4xl">
            <h2 className="max-w-[16ch] font-display text-4xl font-extrabold leading-tight sm:text-5xl">What parents and guardians can expect.</h2>
          </Reveal>
          <div className="mt-10 divide-y divide-white/18 border-y border-white/18">
            {[
              ['Supervised identity', 'A child profile can learn without an email address, public profile or independent payment access.'],
              ['Appropriate visibility', 'Parents see the information needed to supervise progress and sensitive activity.'],
              ['Reporting and response', 'People can report safety concerns. Reports are reviewed and action may include content removal, access restrictions or escalation.'],
              ['No learning lockout', 'Learning is not blocked by hearts or a paywall. Paid plans add convenience rather than permission to learn.'],
              ['School responsibility', 'Schools and institutions receive role-based access and must use learner information only for the authorised programme.'],
            ].map(([title, body]) => (
              <Reveal key={title} className="grid gap-3 py-6 sm:grid-cols-[15rem_1fr]">
                <h3 className="font-display text-2xl font-extrabold text-[#ffb277]">{title}</h3>
                <p className="max-w-3xl leading-relaxed text-white/80">{body}</p>
              </Reveal>
            ))}
          </div>
        </div>
      </section>

      <PublicClosing
        heading="A safety question should never be hard to ask."
        body="Contact us to report a concern, ask about a learner profile or understand the controls available to your family or organisation."
        primary={{ label: 'Contact the safety team', to: '/contact?topic=safety' }}
        secondary={{ label: 'Read the privacy notice', to: '/privacy' }}
      />
    </PublicPageShell>
  )
}

export function AccessibilityPage() {
  return (
    <PublicPageShell mainId="accessibility-main">
      <section className="bg-navy-950 py-16 text-white sm:py-24">
        <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
          <p className="font-display text-xl font-extrabold text-[#ffb277]">Access is part of quality</p>
          <h1 className="mt-4 max-w-[13ch] font-display text-[clamp(3rem,6vw,5.5rem)] font-extrabold leading-[0.98] tracking-[-0.035em]">
            Learning should work for more people, in more situations.
          </h1>
          <p className="mt-6 max-w-3xl text-lg leading-relaxed text-white/80 sm:text-xl">
            We design MAHADUM.360 for keyboard access, readable contrast, meaningful structure, reduced motion and connections that are not always fast.
          </p>
        </div>
      </section>

      <section className="bg-white py-16 sm:py-24">
        <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
          <Reveal className="grid gap-8 lg:grid-cols-[0.7fr_1.3fr]">
            <h2 className="max-w-[12ch] font-display text-4xl font-extrabold leading-tight sm:text-5xl">How accessibility shapes the product.</h2>
            <div className="divide-y divide-chore-200 border-y border-chore-200">
              {[
                ['Perceivable', 'Images carry useful alternative text, text maintains readable contrast, and information is not communicated by colour alone.'],
                ['Operable', 'Core journeys support keyboards, visible focus, meaningful controls and touch targets sized for real use.'],
                ['Understandable', 'Navigation, labels, actions and errors use consistent language and explain what happens next.'],
                ['Robust', 'Semantic HTML and progressive enhancement help the service work with assistive technologies and when optional motion is unavailable.'],
                ['Connection-aware', 'We consider image size, deferred loading and graceful states for people using older devices or weaker connections.'],
              ].map(([title, body]) => (
                <div key={title} className="grid gap-2 py-6 sm:grid-cols-[11rem_1fr]">
                  <h3 className="font-display text-2xl font-extrabold text-chore-700">{title}</h3>
                  <p className="leading-relaxed text-navy-700">{body}</p>
                </div>
              ))}
            </div>
          </Reveal>
        </div>
      </section>

      <section className="bg-[#55c8eb] py-14 sm:py-18">
        <div className="mx-auto grid max-w-6xl gap-8 px-4 sm:px-6 lg:grid-cols-[1fr_auto] lg:items-center lg:px-8">
          <div>
            <h2 className="font-display text-3xl font-extrabold">Tell us where the experience creates a barrier.</h2>
            <p className="mt-3 max-w-3xl leading-relaxed text-navy-800">
              Include the page, task, device or assistive technology involved and what you expected to happen. Please avoid sending passwords or sensitive learner details.
            </p>
          </div>
          <LinkButton to="/contact?topic=accessibility" variant="parent">Report an accessibility barrier</LinkButton>
        </div>
      </section>

      <PublicClosing
        heading="Accessibility improves through specific feedback."
        body="We will review reported barriers, prioritise material issues and communicate when we need more information."
        primary={{ label: 'Contact accessibility support', to: '/contact?topic=accessibility' }}
        secondary={{ label: 'Read the privacy notice', to: '/privacy' }}
      />
    </PublicPageShell>
  )
}

const TOPIC_EMAILS: Record<string, string> = {
  family: SUPPORT_EMAIL,
  support: SUPPORT_EMAIL,
  safety: 'safety@mahadum360.app',
  accessibility: 'accessibility@mahadum360.app',
  school: 'schools@mahadum360.app',
  partnership: 'partnerships@mahadum360.app',
  university: 'partnerships@mahadum360.app',
  culture: 'partnerships@mahadum360.app',
  government: 'partnerships@mahadum360.app',
  telecom: 'partnerships@mahadum360.app',
}

const CONTACT_TOPICS: { value: string; label: string; detail: string; icon: IconName }[] = [
  { value: 'family', label: 'Family & learning', detail: 'Accounts, lessons and subscriptions', icon: 'users' },
  { value: 'school', label: 'Schools', detail: 'Curriculum, seats and school plans', icon: 'cap' },
  { value: 'partnership', label: 'Partnerships', detail: 'Institutions, culture and distribution', icon: 'building' },
  { value: 'support', label: 'Technical support', detail: 'Access, devices and troubleshooting', icon: 'layers' },
  { value: 'safety', label: 'Child safety', detail: 'A concern about a learner or profile', icon: 'shield' },
  { value: 'accessibility', label: 'Accessibility', detail: 'Report a barrier to using Mahadum', icon: 'sparkles' },
]

export function ContactPage() {
  const [searchParams] = useSearchParams()
  const requestedTopic = searchParams.get('topic') ?? 'family'
  const initialTopic = TOPIC_EMAILS[requestedTopic] ? requestedTopic : 'family'
  const [topic, setTopic] = useState(initialTopic)
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [message, setMessage] = useState('')
  const [opened, setOpened] = useState(false)
  const destination = useMemo(() => TOPIC_EMAILS[topic] ?? SUPPORT_EMAIL, [topic])

  function openEmail(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const subject = `Mahadum ${topic} enquiry from ${name || 'website visitor'}`
    const body = [`Name: ${name}`, `Reply email: ${email}`, '', message].join('\n')
    window.location.href = `mailto:${destination}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`
    setOpened(true)
  }

  return (
    <PublicPageShell mainId="contact-main">
      <section className="bg-[#eef6ff] pb-16 pt-12 sm:pb-24 sm:pt-16">
        <Reveal className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <p className="font-display text-lg font-extrabold text-[#a93403]">Talk to the right Mahadum team</p>
          <div className="mt-3 grid gap-5 lg:grid-cols-[1fr_0.72fr] lg:items-end">
            <h1 className="max-w-[14ch] text-wrap-balance font-display text-[clamp(3rem,6vw,5.5rem)] font-extrabold leading-[0.98] tracking-[-0.035em]">
              Let’s start with what you need.
            </h1>
            <p className="max-w-2xl text-lg leading-relaxed text-navy-700 sm:text-xl">
              Tell us what brings you here. We will prepare your message for the team best placed to help.
            </p>
          </div>
        </Reveal>

        <div className="mx-auto mt-10 grid max-w-7xl gap-0 px-4 sm:px-6 lg:grid-cols-[0.76fr_1.24fr] lg:px-8">
          <Reveal className="relative min-h-[25rem] overflow-hidden rounded-t-[1rem] bg-navy-950 lg:min-h-full lg:rounded-l-[1rem] lg:rounded-tr-none">
            <img
              src="/images/community-multigenerational.webp"
              alt="A Nigerian family sharing language and stories across generations"
              className="absolute inset-0 size-full object-cover opacity-75"
            />
            <div className="absolute inset-0 bg-[linear-gradient(180deg,rgba(6,26,60,0.08)_15%,rgba(6,26,60,0.96)_100%)]" />
            <div className="relative flex min-h-full flex-col justify-end p-6 text-white sm:p-9">
              <p className="max-w-[18ch] text-wrap-balance font-display text-3xl font-extrabold leading-tight sm:text-4xl">
                Real people. The right team. A clear next step.
              </p>
              <p className="mt-4 max-w-md leading-relaxed text-white/80">
                Family questions, school planning, partnerships and sensitive concerns each go to a dedicated inbox.
              </p>
              <div className="mt-7 border-t border-white/20 pt-5">
                <p className="text-sm font-bold text-white/72">Prefer to email directly?</p>
                <a className="mt-1 inline-flex min-h-11 items-center font-display font-extrabold text-[#ffb277] hover:underline" href={`mailto:${destination}`}>
                  {destination} <span aria-hidden="true" className="ml-2">→</span>
                </a>
              </div>
            </div>
          </Reveal>

          <Reveal delay={80}>
            <form onSubmit={openEmail} className="rounded-b-[1rem] bg-white p-6 sm:p-9 lg:rounded-r-[1rem] lg:rounded-bl-none lg:p-12">
              <div className="flex items-start gap-4">
                <span className="flex size-12 shrink-0 items-center justify-center rounded-full bg-[#e7f7ff] text-chore-700">
                  <Icon name="clipboard" className="size-6" />
                </span>
                <div>
                  <h2 className="font-display text-3xl font-extrabold">Send us the details</h2>
                  <p className="mt-1 leading-relaxed text-navy-700">All fields are required. We’ll route the message based on your selection.</p>
                </div>
              </div>

              <fieldset className="mt-8">
                <legend className="font-display text-base font-extrabold text-navy-950">What can we help with?</legend>
                <div className="mt-3 grid gap-2 sm:grid-cols-2">
                  {CONTACT_TOPICS.map((item) => {
                    const selected = topic === item.value
                    return (
                      <label
                        key={item.value}
                        className={`flex min-h-20 cursor-pointer items-start gap-3 rounded-xl border p-3.5 transition-colors focus-within:outline-none focus-within:ring-2 focus-within:ring-chore-500 focus-within:ring-offset-2 ${
                          selected
                            ? 'border-chore-600 bg-[#e7f7ff] text-navy-950'
                            : 'border-chore-100 bg-white text-navy-800 hover:border-chore-300 hover:bg-[#f7fbff]'
                        }`}
                      >
                        <input
                          type="radio"
                          name="contact-topic"
                          value={item.value}
                          checked={selected}
                          onChange={(event) => setTopic(event.target.value)}
                          className="sr-only"
                        />
                        <span className={`mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full ${selected ? 'bg-chore-600 text-white' : 'bg-chore-50 text-chore-700'}`}>
                          <Icon name={item.icon} className="size-5" />
                        </span>
                        <span>
                          <strong className="block font-display text-sm">{item.label}</strong>
                          <span className="mt-0.5 block text-xs leading-relaxed text-navy-600">{item.detail}</span>
                        </span>
                      </label>
                    )
                  })}
                </div>
              </fieldset>

              <div className="mt-7 grid gap-5 sm:grid-cols-2">
                <Input
                  label="Your name"
                  value={name}
                  onChange={(event) => setName(event.target.value)}
                  required
                  autoComplete="name"
                  placeholder="How should we address you?"
                  className="h-12 text-base"
                />
                <Input
                  label="Email address"
                  type="email"
                  value={email}
                  onChange={(event) => setEmail(event.target.value)}
                  required
                  autoComplete="email"
                  placeholder="you@example.com"
                  className="h-12 text-base"
                />
              </div>
              <div className="mt-6">
                <Textarea
                  label="Tell us what happened or what you are planning"
                  hint={`${message.length}/1200 characters. Please do not include passwords, card details or unnecessary learner information.`}
                  value={message}
                  onChange={(event) => setMessage(event.target.value)}
                  required
                  maxLength={1200}
                  rows={6}
                  placeholder="Include the page, programme or goal involved and what a helpful outcome would look like."
                  className="min-h-40 resize-y text-base leading-relaxed"
                />
              </div>
              <Button type="submit" variant="accent" size="lg" fullWidth className="mt-7">
                Prepare email for the {CONTACT_TOPICS.find((item) => item.value === topic)?.label ?? 'Mahadum'} team
              </Button>
              <div className="mt-4 flex items-start gap-2 text-sm leading-relaxed text-navy-600">
                <Icon name="shield" className="mt-0.5 size-4 text-chore-700" />
                <p>This opens your email app with the message prepared. You can review it before sending.</p>
              </div>
              {opened && (
                <p role="status" className="mt-4 rounded-xl bg-[#e7f7ff] p-4 text-sm font-bold text-navy-800">
                  Your email app should now be open. If it did not open, send your message to <a className="text-chore-700 underline" href={`mailto:${destination}`}>{destination}</a>.
                </p>
              )}
            </form>
          </Reveal>
        </div>
      </section>
    </PublicPageShell>
  )
}
