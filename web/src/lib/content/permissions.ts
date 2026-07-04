import { useAuth } from '@/lib/auth/AuthProvider'

/**
 * Who can author content. super_admin (global) + content_owner manage courses;
 * other roles (e.g. school_admin) may view the catalogue read-only.
 */
export function useCanManageContent(): boolean {
  return useAuth().hasRole('super_admin', 'content_owner')
}
