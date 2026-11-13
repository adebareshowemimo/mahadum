import { useQuery, useQueryClient } from '@tanstack/react-query'
import { contentApi } from '@/lib/api'

export const libraryKeys = {
  lists: ['content', 'library-sets'] as const,
  details: ['content', 'library-set'] as const,
  detail: (id: number) => ['content', 'library-set', id] as const,
}

export function useLibrarySet(id: number) {
  return useQuery({ queryKey: libraryKeys.detail(id), queryFn: () => contentApi.librarySet(id), enabled: id > 0 })
}

export function useRefreshLibrary() {
  const client = useQueryClient()
  return async () => {
    await Promise.all([
      client.invalidateQueries({ queryKey: libraryKeys.lists }),
      client.invalidateQueries({ queryKey: libraryKeys.details }),
      client.invalidateQueries({ queryKey: ['content', 'lesson'] }),
    ])
  }
}
