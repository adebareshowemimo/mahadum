import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { Icon } from '@/components/ui'

interface AdminPageHeaderProps {
  title: string
  description?: string
  /** Optional "back" link (e.g. from a detail page to its list). */
  backTo?: string
  backLabel?: string
  /** Right-aligned actions (buttons). */
  actions?: ReactNode
}

/** Consistent header for admin portal pages: optional back link, title, actions. */
export function AdminPageHeader({ title, description, backTo, backLabel = 'Back', actions }: AdminPageHeaderProps) {
  return (
    <div className="flex flex-col gap-2">
      {backTo && (
        <Link
          to={backTo}
          className="inline-flex w-fit items-center gap-1.5 text-sm font-semibold text-muted hover:text-foreground"
        >
          <Icon name="arrow-left" className="size-4" />
          {backLabel}
        </Link>
      )}
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="font-display text-2xl font-bold text-foreground">{title}</h1>
          {description && <p className="mt-0.5 text-sm text-muted">{description}</p>}
        </div>
        {actions && <div className="flex items-center gap-2">{actions}</div>}
      </div>
    </div>
  )
}
