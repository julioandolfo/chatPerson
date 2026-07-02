import {
  useInfiniteQuery,
  type InfiniteData,
  type QueryClient,
} from '@tanstack/react-query';
import { useMemo } from 'react';

import { listConversations } from '@/api/conversations';
import { CONVERSATIONS_PAGE_SIZE } from '@/config';
import { useUiStore } from '@/stores/ui';
import type { Conversation, Paginated } from '@/types';

export type ConversationsCache = InfiniteData<Paginated<Conversation>, number>;

export const CONVERSATIONS_KEY = 'conversations';

/**
 * Lista de conversas com scroll infinito, reagindo aos filtros da UI store.
 */
export function useConversations() {
  const filter = useUiStore((s) => s.filter);
  const search = useUiStore((s) => s.search);
  const advanced = useUiStore((s) => s.advanced);

  const query = useInfiniteQuery({
    queryKey: [
      CONVERSATIONS_KEY,
      filter,
      search,
      advanced.status,
      advanced.channel,
      advanced.department_id,
      advanced.funnel_id,
    ],
    queryFn: ({ pageParam }) =>
      listConversations({
        page: pageParam,
        per_page: CONVERSATIONS_PAGE_SIZE,
        filter,
        search: search || undefined,
        status: advanced.status ?? undefined,
        channel: advanced.channel ?? undefined,
        department_id: advanced.department_id ?? undefined,
        funnel_id: advanced.funnel_id ?? undefined,
      }),
    initialPageParam: 1,
    getNextPageParam: (lastPage) =>
      lastPage.pagination.has_next ? lastPage.pagination.page + 1 : undefined,
    staleTime: 10_000,
  });

  const conversations = useMemo(() => {
    const items = query.data?.pages.flatMap((page) => page.items) ?? [];
    const seen = new Set<number>();
    const unique = items.filter((c) => {
      if (seen.has(c.id)) return false;
      seen.add(c.id);
      return true;
    });
    // Fixadas primeiro, depois por última mensagem (desc).
    return unique.sort((a, b) => {
      if (a.pinned !== b.pinned) return a.pinned ? -1 : 1;
      const ta = a.last_message_at ? new Date(a.last_message_at).getTime() : 0;
      const tb = b.last_message_at ? new Date(b.last_message_at).getTime() : 0;
      return tb - ta;
    });
  }, [query.data]);

  return { ...query, conversations };
}

/**
 * Aplica `updater` à conversa `id` em todos os caches de lista (todas as
 * combinações de filtros). Retorna true se a conversa foi encontrada.
 */
export function updateConversationInCaches(
  queryClient: QueryClient,
  id: number,
  updater: (conversation: Conversation) => Conversation,
): boolean {
  let found = false;
  const entries = queryClient.getQueriesData<ConversationsCache>({
    queryKey: [CONVERSATIONS_KEY],
  });

  for (const [key, cache] of entries) {
    if (!cache) continue;
    let changed = false;
    const pages = cache.pages.map((page) => {
      if (!page.items.some((c) => c.id === id)) return page;
      changed = true;
      found = true;
      return { ...page, items: page.items.map((c) => (c.id === id ? updater(c) : c)) };
    });
    if (changed) {
      queryClient.setQueryData<ConversationsCache>(key, { ...cache, pages });
    }
  }

  return found;
}
