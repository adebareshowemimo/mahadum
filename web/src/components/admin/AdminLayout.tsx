import { Outlet } from 'react-router-dom'
import { AdminSubNav } from './AdminSubNav'

/** Shared chrome for the admin portal: the grouped sub-nav above each page. */
export function AdminLayout() {
  return (
    <div>
      <AdminSubNav />
      <Outlet />
    </div>
  )
}
