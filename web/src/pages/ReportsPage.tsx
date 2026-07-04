import { Link } from 'react-router-dom'
import { AdminPageHeader } from '@/components/admin'
import { Card, CardBody, Icon, type IconName } from '@/components/ui'

const REPORTS: { to: string; title: string; description: string; icon: IconName }[] = [
  {
    to: '/admin/reports/income',
    title: 'Income',
    description: 'Revenue by channel (card, telco, invoices) month by month, gross vs net.',
    icon: 'wallet',
  },
  {
    to: '/admin/reports/growth',
    title: 'Growth',
    description: 'New users and organizations per month, with all-time totals.',
    icon: 'users',
  },
  {
    to: '/admin/reports/subscriptions',
    title: 'Subscriptions',
    description: 'New subscriptions per month, status breakdown, and active count.',
    icon: 'card',
  },
  {
    to: '/admin/reports/referrals',
    title: 'Referrals & commissions',
    description: 'New referrals over time, referral status mix, and commission totals.',
    icon: 'gift',
  },
  {
    to: '/admin/reports/org-activity',
    title: 'Organizations & schools',
    description: 'New organizations per month, status mix, and class/student totals.',
    icon: 'building',
  },
  {
    to: '/admin/reports/renewals',
    title: 'Upcoming renewals',
    description: 'Subscriptions due to renew by month, expected revenue, and reminder coverage.',
    icon: 'card',
  },
]

export function ReportsPage() {
  return (
    <div className="flex flex-col gap-6">
      <AdminPageHeader title="Reports" description="Platform analytics. Each report supports a custom date range and export where useful." />

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {REPORTS.map((r) => (
          <Link key={r.to} to={r.to} className="group">
            <Card className="h-full transition-colors group-hover:border-border-strong">
              <CardBody className="flex flex-col gap-3">
                <span className="flex size-10 items-center justify-center rounded-xl bg-surface-muted text-primary">
                  <Icon name={r.icon} />
                </span>
                <div>
                  <p className="font-display text-lg font-bold text-foreground">{r.title}</p>
                  <p className="mt-0.5 text-sm text-muted">{r.description}</p>
                </div>
              </CardBody>
            </Card>
          </Link>
        ))}
      </div>
    </div>
  )
}
