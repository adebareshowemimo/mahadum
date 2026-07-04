import { Card, CardBody, type IconName, Icon } from '@/components/ui'

/** Generic placeholder for nav destinations whose screens haven't shipped yet. */
export function ComingSoon({ title, icon = 'sparkles' }: { title: string; icon?: IconName }) {
  return (
    <div className="mx-auto max-w-md">
      <Card>
        <CardBody className="flex flex-col items-center gap-3 py-12 text-center">
          <span className="flex size-14 items-center justify-center rounded-2xl bg-primary-soft text-primary">
            <Icon name={icon} className="size-7" />
          </span>
          <h1 className="font-display text-xl font-bold text-foreground">{title}</h1>
          <p className="max-w-xs text-sm text-muted">
            This area is on the way. It’ll light up as we build out the {title.toLowerCase()} screens.
          </p>
        </CardBody>
      </Card>
    </div>
  )
}
