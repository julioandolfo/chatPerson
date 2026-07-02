import {
  useInfiniteQuery,
  useMutation,
  useQueryClient,
  type InfiniteData,
  type QueryClient,
} from '@tanstack/react-query';
import { useMemo } from 'react';

import { listMessages, sendMessage } from '@/api/messages';
import { MESSAGES_PAGE_SIZE } from '@/config';
import { updateConversationInCaches } from '@/hooks/useConversations';
import { useAuthStore } from '@/stores/auth';
import type { Message, SendMessageInput } from '@/types';

export interface MessagesPage {
  items: Message[];
}

export type MessagesCache = InfiniteData<MessagesPage, number | null>;

export const MESSAGES_KEY = 'messages';

export const messagesQueryKey = (conversationId: number) =>
  [MESSAGES_KEY, conversationId] as const;

function sortDesc(items: Message[]): Message[] {
  return [...items].sort(
    (a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime(),
  );
}

/**
 * Mensagens de uma conversa, mais recentes primeiro (para FlashList invertida),
 * com paginação para trás via before_id.
 */
export function useMessages(conversationId: number) {
  const query = useInfiniteQuery({
    queryKey: messagesQueryKey(conversationId),
    queryFn: async ({ pageParam }) => {
      const data = await listMessages(conversationId, {
        limit: MESSAGES_PAGE_SIZE,
        before_id: pageParam ?? undefined,
      });
      return { items: sortDesc(data.items) } satisfies MessagesPage;
    },
    initialPageParam: null as number | null,
    getNextPageParam: (lastPage) => {
      if (lastPage.items.length < MESSAGES_PAGE_SIZE) return null;
      // Ignora mensagens otimistas (id negativo) ao buscar o cursor.
      for (let i = lastPage.items.length - 1; i >= 0; i -= 1) {
        if (lastPage.items[i].id > 0) return lastPage.items[i].id;
      }
      return null;
    },
    enabled: Number.isFinite(conversationId) && conversationId > 0,
    staleTime: 5_000,
  });

  const messages = useMemo(() => {
    const items = query.data?.pages.flatMap((page) => page.items) ?? [];
    const seen = new Set<number>();
    return items.filter((m) => {
      if (m.id > 0 && seen.has(m.id)) return false;
      if (m.id > 0) seen.add(m.id);
      return true;
    });
  }, [query.data]);

  return { ...query, messages };
}

// ---------------------------------------------------------------------------
// Helpers de cache (usados pelo envio otimista e pelo realtime)
// ---------------------------------------------------------------------------

export function prependMessage(
  queryClient: QueryClient,
  conversationId: number,
  message: Message,
): void {
  queryClient.setQueryData<MessagesCache>(messagesQueryKey(conversationId), (old) => {
    if (!old || old.pages.length === 0) return old;
    const exists = old.pages.some((page) =>
      page.items.some(
        (m) =>
          (message.id > 0 && m.id === message.id) ||
          (message.local_id != null && m.local_id === message.local_id),
      ),
    );
    if (exists) return old;
    const pages = old.pages.slice();
    pages[0] = { ...pages[0], items: [message, ...pages[0].items] };
    return { ...old, pages };
  });
}

export function replaceLocalMessage(
  queryClient: QueryClient,
  conversationId: number,
  localId: string,
  serverMessage: Message,
): void {
  queryClient.setQueryData<MessagesCache>(messagesQueryKey(conversationId), (old) => {
    if (!old) return old;
    const pages = old.pages.map((page) => ({
      ...page,
      items: page.items
        // Remove eco do realtime que possa ter chegado antes da resposta.
        .filter((m) => !(m.id === serverMessage.id && m.local_id !== localId))
        .map((m) => (m.local_id === localId ? { ...serverMessage, local_id: localId } : m)),
    }));
    return { ...old, pages };
  });
}

export function markLocalMessageError(
  queryClient: QueryClient,
  conversationId: number,
  localId: string,
): void {
  queryClient.setQueryData<MessagesCache>(messagesQueryKey(conversationId), (old) => {
    if (!old) return old;
    const pages = old.pages.map((page) => ({
      ...page,
      items: page.items.map((m) =>
        m.local_id === localId ? { ...m, status: 'error' as const } : m,
      ),
    }));
    return { ...old, pages };
  });
}

export function removeLocalMessage(
  queryClient: QueryClient,
  conversationId: number,
  localId: string,
): void {
  queryClient.setQueryData<MessagesCache>(messagesQueryKey(conversationId), (old) => {
    if (!old) return old;
    const pages = old.pages.map((page) => ({
      ...page,
      items: page.items.filter((m) => m.local_id !== localId),
    }));
    return { ...old, pages };
  });
}

// ---------------------------------------------------------------------------
// Envio otimista
// ---------------------------------------------------------------------------

function guessMessageType(input: SendMessageInput): string {
  const first = input.attachments?.[0];
  if (!first) return 'text';
  if (first.type.startsWith('image/')) return 'image';
  if (first.type.startsWith('audio/')) return 'audio';
  if (first.type.startsWith('video/')) return 'video';
  return 'document';
}

export function useSendMessage(conversationId: number) {
  const queryClient = useQueryClient();
  const user = useAuthStore((s) => s.user);

  return useMutation({
    mutationFn: (input: SendMessageInput) => sendMessage(conversationId, input),

    onMutate: (input) => {
      const optimistic: Message = {
        id: -Date.now(),
        conversation_id: conversationId,
        sender_type: 'agent',
        sender_id: user?.id ?? null,
        sender_name: user?.name ?? null,
        content: input.content?.trim() || null,
        message_type: guessMessageType(input),
        attachments: (input.attachments ?? []).map((a) => ({
          url: a.uri,
          type: a.type,
          name: a.name,
          size: a.size ?? null,
        })),
        status: 'pending',
        created_at: new Date().toISOString(),
        quoted_message_id: input.quoted_message_id ?? null,
        quoted_text: input.quoted_text ?? null,
        quoted_sender_name: input.quoted_sender_name ?? null,
        is_note: Boolean(input.is_note),
        local_id: input.local_id,
      };
      prependMessage(queryClient, conversationId, optimistic);
    },

    onSuccess: (message, input) => {
      replaceLocalMessage(queryClient, conversationId, input.local_id, message);
      if (!input.is_note) {
        updateConversationInCaches(queryClient, conversationId, (c) => ({
          ...c,
          last_message_preview: message.content ?? '📎 Anexo',
          last_message_at: message.created_at,
        }));
      }
    },

    onError: (_error, input) => {
      markLocalMessageError(queryClient, conversationId, input.local_id);
    },
  });
}
