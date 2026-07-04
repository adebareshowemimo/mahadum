import { useState, type ReactNode } from 'react'
import { cn } from '@/lib/cn'
import { useTheme } from '@/lib/theme'
import { Logo } from '@/components/Logo'
import {
  Alert, Avatar, Badge, Button, Button3D, Card, CardBody, CardDescription, CardHeader, CardTitle,
  CodeInput, FileUpload, Input, PhoneInput, Progress, Skeleton, Spinner, Switch,
} from '@/components/ui'
import {
  CoinPill, CulturalBadgeCard, CurrentLessonCard, HeartsCounter, LessonNode, LessonStepProgress,
  RewardCelebrationModal, SpeakingScoreGauge, XpCounter,
} from '@/components/learning'
import { ChildProfileSwitcher, ChoreCard, FamilyLeaderboardRow, WalletBalanceCard } from '@/components/family'
import { CulturalVideoCard, LanguageSelectionCard, NativeSpeakerCard, ProverbCard, WordOfDayCard } from '@/components/cultural'
import { AirtimeRechargeCTA, CommissionBadge, ReferralCodeCard, RemoveAdsCTA, SubscriptionStatusBadge, TelcoGraceBanner } from '@/components/billing'

type Mode = 'foundations' | 'learner' | 'family' | 'school'

function Section({ title, hint, children }: { title: string; hint?: string; children: ReactNode }) {
  return (
    <section className="flex flex-col gap-4">
      <div>
        <h2 className="text-xl text-foreground">{title}</h2>
        {hint && <p className="text-sm text-muted">{hint}</p>}
      </div>
      <div className="flex flex-wrap items-start gap-3">{children}</div>
    </section>
  )
}

const PALETTE = [
  { name: 'heritage', className: 'bg-heritage-500', use: 'Primary · correct' },
  { name: 'gold', className: 'bg-gold-400', use: 'Reward · coins' },
  { name: 'navy', className: 'bg-navy-900', use: 'Premium · dark' },
  { name: 'ivory', className: 'bg-ivory-100 border border-border', use: 'Base' },
  { name: 'charcoal', className: 'bg-charcoal-900', use: 'Text' },
  { name: 'chore', className: 'bg-chore-500', use: 'Parent · school' },
  { name: 'clay', className: 'bg-clay-500', use: 'Cultural' },
  { name: 'leaf', className: 'bg-leaf-500', use: 'Progress' },
  { name: 'ai', className: 'bg-ai-500', use: 'AI · speaking' },
  { name: 'red', className: 'bg-red-500', use: 'Wrong · danger' },
]

export function ComponentsPage() {
  const { theme, toggle } = useTheme()
  const [mode, setMode] = useState<Mode>('foundations')
  const [notify, setNotify] = useState(true)
  const [otp, setOtp] = useState('')
  const [pin, setPin] = useState('')
  const [phone, setPhone] = useState('')
  const [reward, setReward] = useState(false)

  return (
    <div className="min-h-dvh bg-background">
      <header className="sticky top-0 z-20 border-b border-border bg-surface/85 backdrop-blur">
        <div className="mx-auto flex max-w-5xl items-center justify-between px-6 py-4">
          <div className="flex items-center gap-3">
            <Logo className="h-8" />
            <span className="hidden border-l border-border pl-3 text-xs text-muted sm:inline">Design system · Baloo 2 + 3D</span>
          </div>
          <Button variant="outline" size="sm" onClick={toggle}>{theme === 'dark' ? '☀️ Light' : '🌙 Dark'}</Button>
        </div>
        <nav className="mx-auto -mb-px flex max-w-5xl gap-1 px-4">
          {(['foundations', 'learner', 'family', 'school'] as Mode[]).map((m) => (
            <button
              key={m}
              onClick={() => setMode(m)}
              className={cn(
                'rounded-t-lg px-4 py-2.5 text-sm font-semibold capitalize transition-colors',
                mode === m ? 'border-b-2 border-primary text-primary' : 'text-muted hover:text-foreground',
              )}
            >
              {m}
            </button>
          ))}
        </nav>
      </header>

      <main className="mx-auto flex max-w-5xl flex-col gap-12 px-6 py-10">
        {mode === 'foundations' && (
          <>
            <Section title="Colour" hint="Tokens are named by brand meaning, not by hue. They re-theme in dark mode.">
              <div className="grid w-full grid-cols-2 gap-3 sm:grid-cols-5">
                {PALETTE.map((c) => (
                  <div key={c.name} className="flex flex-col gap-1.5">
                    <div className={`h-14 rounded-xl shadow-sm ${c.className}`} />
                    <div><p className="text-xs font-bold text-foreground">{c.name}</p><p className="text-[11px] text-muted">{c.use}</p></div>
                  </div>
                ))}
              </div>
            </Section>

            <Section title="Buttons · primary CTAs" hint="Chunky 3D press — click to feel it sink. African Gold keeps charcoal text.">
              <Button3D variant="primary" leftIcon={<span>📖</span>}>Start lesson</Button3D>
              <Button3D variant="reward" leftIcon={<span>🪙</span>}>Claim coins</Button3D>
              <Button3D variant="premium" leftIcon={<span>✨</span>}>Upgrade</Button3D>
              <Button3D variant="parent">Approve</Button3D>
              <Button3D variant="billing" leftIcon={<span>📲</span>}>Recharge</Button3D>
              <Button3D variant="danger">Cancel</Button3D>
            </Section>

            <Section title="Buttons · utility & secondary" hint="Flat buttons for admin, secondary and inline actions.">
              <Button variant="outline">Outline</Button>
              <Button variant="ghost">Ghost</Button>
              <Button variant="secondary">Secondary</Button>
              <Button variant="soft">Soft</Button>
              <Button variant="primary" size="sm">Small primary</Button>
              <Button loading>Saving…</Button>
            </Section>

            <Section title="Badges" hint="Streaks, mastery, family & billing states.">
              <Badge variant="gold" dot>7-day streak 🔥</Badge>
              <Badge variant="gold">30-day streak</Badge>
              <Badge variant="clay">Culture Master</Badge>
              <Badge variant="ai">Speaking Expert</Badge>
              <Badge variant="primary">Family Hero</Badge>
              <Badge variant="warning">Needs tone practice</Badge>
              <Badge variant="info" dot>Parent review</Badge>
              <Badge variant="premium">Premium</Badge>
              <Badge variant="neutral">Free tier</Badge>
              <Badge variant="success">School linked</Badge>
              <CommissionBadge state="pending" amountMinor={250000} />
              <CommissionBadge state="cleared" amountMinor={500000} />
            </Section>

            <Section title="Inputs" hint="Phone-first: OTP, PIN lock, WhatsApp fallback, CSV roster.">
              <div className="grid w-full max-w-2xl gap-5 sm:grid-cols-2">
                <Input label="Language search" placeholder="Search Yoruba, Igbo…" leftIcon={<span>🔎</span>} />
                <PhoneInput label="Phone number" value={phone} onChange={setPhone} />
                <div className="flex flex-col gap-1.5">
                  <span className="text-sm font-semibold text-foreground">Verification code (OTP)</span>
                  <CodeInput value={otp} onChange={setOtp} length={6} aria-label="One-time code" />
                  <button className="self-start text-xs font-semibold text-primary">Resend code · or get it on WhatsApp</button>
                </div>
                <div className="flex flex-col gap-1.5">
                  <span className="text-sm font-semibold text-foreground">Parent PIN</span>
                  <CodeInput value={pin} onChange={setPin} length={4} mask aria-label="Parental PIN" />
                </div>
                <div className="sm:col-span-2"><FileUpload hint="Student roster · CSV up to 2 MB" /></div>
              </div>
            </Section>

            <Section title="Alerts" hint="Warm, reassuring tone — billing issues frame as streak protection.">
              <div className="grid w-full max-w-2xl gap-3">
                <Alert variant="grace" title="Your streak is protected for 48 hours" icon={<span>🛡️</span>}>Recharge soon to keep learning.</Alert>
                <Alert variant="info" title="Chidi completed a speaking challenge" icon={<span>🎙️</span>}>Review to release 30 coins.</Alert>
                <Alert variant="success" title="Great work!" icon={<span>✅</span>}>Your wallet top-up was successful.</Alert>
                <Alert variant="warning" title="You're offline" icon={<span>📡</span>}>Your progress is safe and will sync later.</Alert>
              </div>
            </Section>

            <Section title="Avatars · progress · loaders">
              <div className="flex items-center gap-2"><Avatar name="Chidi" size="sm" /><Avatar name="Ngozi Bello" /><Avatar name="Sade Balogun" size="lg" /></div>
              <div className="flex w-full max-w-md flex-col gap-3 pt-2">
                <Progress value={35} tone="primary" showLabel /><Progress value={70} tone="accent" showLabel /><Progress value={100} tone="success" showLabel />
              </div>
              <div className="flex items-center gap-4 pt-2"><Spinner className="text-primary" /><div className="flex flex-col gap-2"><Skeleton className="h-3 w-40" /><Skeleton className="h-3 w-28" /></div></div>
              <Switch checked={notify} onChange={setNotify} label="Notifications" />
            </Section>
          </>
        )}

        {mode === 'learner' && (
          <>
            <Section title="Learning stats" hint="Bright, gamified, reward-heavy — child-safe.">
              <HeartsCounter current={4} /><XpCounter value={1240} /><CoinPill amount={320} />
              <SpeakingScoreGauge score={86} /><SpeakingScoreGauge score={62} label="Tone" />
            </Section>

            <Section title="Lesson pathway">
              <div className="flex w-full items-center gap-3">
                <LessonNode state="completed" label="Greetings" />
                <span className="h-0.5 flex-1 bg-gold-300" />
                <LessonNode state="completed" label="Numbers" />
                <span className="h-0.5 flex-1 bg-primary" />
                <LessonNode state="current" label="My family" />
                <span className="h-0.5 flex-1 bg-border" />
                <LessonNode state="locked" label="Market" />
              </div>
              <div className="w-full max-w-md pt-2"><LessonStepProgress steps={['Video', 'Quiz', 'Speak', 'Done']} current={2} /></div>
            </Section>

            <Section title="Current lesson & rewards">
              <CurrentLessonCard language="Yorùbá" unit="Unit 2" title="My family" hearts={4} xp={1240} speakingScore={86} progress={66} onContinue={() => setReward(true)} />
              <div className="flex flex-col gap-3">
                <CulturalBadgeCard emoji="🥁" title="Culture Master" subtitle="10 cultural lessons" />
                <Button variant="reward" onClick={() => setReward(true)}>Preview reward modal 🎉</Button>
              </div>
            </Section>

            <Section title="Cultural content" hint="Culturally grounded — proverbs, folklore, native speakers.">
              <ProverbCard proverb="Bí a bá ń gun igi ọ̀gẹ̀dẹ̀, a kì í wo ẹ̀yìn" translation="Focus on the task before you." language="Yorùbá" />
              <WordOfDayCard word="Ẹ̀kọ́" meaning="education / learning" pronunciation="eh-kaw" language="Yorùbá" />
              <NativeSpeakerCard name="Adunni Bello" region="Ibadan, Nigeria" language="Yorùbá" />
              <CulturalVideoCard title="The Tortoise & the Birds" kind="Folktale" duration="4:30" />
            </Section>

            <Section title="Language selection">
              <LanguageSelectionCard language="Yorùbá" flag="🪘" learners="12.4k" selected />
              <LanguageSelectionCard language="Igbo" flag="🌍" learners="8.1k" />
              <LanguageSelectionCard language="Hausa" flag="🐪" learners="9.7k" />
              <LanguageSelectionCard language="Pidgin" flag="🗣️" learners="15.2k" />
            </Section>
          </>
        )}

        {mode === 'family' && (
          <>
            <Section title="Profiles & wallet" hint="Warm, simple, wallet-driven, parent-friendly.">
              <WalletBalanceCard currencyMinor={4250000} coins={1820} />
              <div className="flex flex-col gap-3">
                <ChildProfileSwitcher profiles={[{ name: 'Chidi', level: 3 }, { name: 'Ngozi', level: 2 }]} />
                <Alert variant="warning" title="Low wallet balance" icon={<span>👛</span>}>Top up to keep chore rewards flowing.</Alert>
              </div>
            </Section>

            <Section title="Chores → coins" hint="Coins release only on approval (Rule 8).">
              <ChoreCard title="Practice Yoruba" assignee="Chidi · due today" reward={30} status="pending_review" onApprove={() => setReward(true)} />
              <ChoreCard title="Tidy your room" assignee="Ngozi" reward={20} status="active" />
              <ChoreCard title="Read for 20 min" assignee="Chidi" reward={25} status="approved" />
            </Section>

            <Section title="Family leaderboard">
              <Card className="w-96">
                <CardHeader><CardTitle>This week</CardTitle><CardDescription>Family cheer — warmth over pressure</CardDescription></CardHeader>
                <CardBody className="flex flex-col gap-1.5">
                  <FamilyLeaderboardRow rank={1} name="Ngozi" xp={1840} />
                  <FamilyLeaderboardRow rank={2} name="Chidi" xp={1240} you />
                  <FamilyLeaderboardRow rank={3} name="Dad" xp={620} />
                </CardBody>
              </Card>
            </Section>

            <Section title="Airtime & invites">
              <AirtimeRechargeCTA msisdn="0803 123 4567" dailyMinor={5000} />
              <ReferralCodeCard code="MAHADUM-4K2X" referrals={6} pendingMinor={250000} />
            </Section>
          </>
        )}

        {mode === 'school' && (
          <>
            <Section title="School & admin" hint="Professional, data-dense, trustworthy, dashboard-first.">
              <div className="grid w-full grid-cols-2 gap-3 sm:grid-cols-4">
                {[
                  { label: 'Students', value: '1,284', tone: 'text-foreground' },
                  { label: 'Active this wk', value: '78%', tone: 'text-leaf-600' },
                  { label: 'Seats filled', value: '240 / 300', tone: 'text-foreground' },
                  { label: 'Unpaid invoices', value: '₦180,000', tone: 'text-clay-600' },
                ].map((s) => (
                  <Card key={s.label}><CardBody><p className="text-xs font-semibold uppercase tracking-wide text-muted">{s.label}</p><p className={cn('mt-1 font-display text-2xl font-bold', s.tone)}>{s.value}</p></CardBody></Card>
                ))}
              </div>
            </Section>

            <Section title="Billing & subscriptions">
              <div className="flex flex-wrap items-center gap-3">
                <SubscriptionStatusBadge state="active" />
                <SubscriptionStatusBadge state="grace" />
                <SubscriptionStatusBadge state="pending" />
                <SubscriptionStatusBadge state="cancelled" />
                <SubscriptionStatusBadge state="free" />
              </div>
              <div className="w-full"><TelcoGraceBanner hoursLeft={48} /></div>
              <RemoveAdsCTA />
            </Section>

            <Section title="Roster import">
              <div className="w-full max-w-md"><FileUpload hint="Student roster · CSV up to 2 MB" /></div>
            </Section>
          </>
        )}

        <footer className="border-t border-border pt-6 text-sm text-muted">
          Built on <code className="rounded bg-surface-muted px-1.5 py-0.5 text-xs">web/tokens</code> · brand-meaning tokens + Tailwind v4.
        </footer>
      </main>

      <RewardCelebrationModal open={reward} coins={30} onClose={() => setReward(false)} />
    </div>
  )
}
