import { useQueryClient, type QueryClient } from '@tanstack/react-query';
import * as Haptics from 'expo-haptics';
import * as SecureStore from 'expo-secure-store';
import { useEffect, useRef } from 'react';
import { AppState } from 'react-native';

import { poll } from '@/api/realtime';
import { POLL_INTERVAL_MS } from '@/config';
import { CONVERSATIONS_KEY, updateConversationInCaches } from '@/hooks/useConversations';
import { prependMessage, type MessagesCache, MESSAGES_KEY } from '@/hooks/useMessages';
import { useSettingsStore } from '@/stores/settings';
import { useUiStore } from '@/stores/ui';
import type {
  Conversation,
  MessageStatusUpdate,
  RealtimePollResponse,
} from '@/types';

const LAST_UPDATE_KEY = 'cp_last_update_time';

function applyStatusUpdate(queryClient: QueryClient, update: MessageStatusUpdate): void {
  const entries = queryClient.getQueriesData<MessagesCache>({ queryKey: [MESSAGES_KEY] });
  for (const [key, cache] of entries) {
    if (!cache) continue;
    let changed = false;
    const pages = cache.pages.map((page) => {
      if (!page.items.some((m) => m.id === update.message_id)) return page;
      changed = true;
      return {
        ...page,
        items: page.items.map((m) =>
          m.id === update.message_id ? { ...m, status: update.status } : m,
        ),
      };
    });
    if (changed) {
      queryClient.setQueryData<MessagesCache>(key, { ...cache, pages });
    }
  }
}

function handlePollResult(
  queryClient: QueryClient,
  data: RealtimePollResponse,
  activeConversationId: number | null,
  soundEnabled: boolean,
): void {
  let shouldInvalidateLists = false;
  let shouldNotify = false;

  for (const message of data.new_messages ?? []) {
    prependMessage(queryClient, message.conversation_id, message);

    const isIncoming = message.sender_type === 'contact';
    const isActive = message.conversation_id === activeConversationId;

    const found = updateConversationInCaches(queryClient, message.conversation_id, (c) => ({
      ...c,
      last_message_preview:
        message.content ?? (message.attachments.length > 0 ? '📎 Anexo' : c.last_message_preview),
      last_message_at: message.created_at,
      unread_count: isIncoming && !isActive ? c.unread_count + 1 : c.unread_count,
    }));

    if (!found) shouldInvalidateLists = true;
    if (isIncoming && !isActive) shouldNotify = true;
  }

  for (const update of data.conversation_updates ?? []) {
    const found = updateConversationInCaches(queryClient, update.id, (c) => ({ ...c, ...update }));
    if (!found) shouldInvalidateLists = true;
    queryClient.setQueryData<Conversation>(['conversation', update.id], (old) =>
      old ? { ...old, ...update } : old,
    );
  }

  if ((data.new_conversations ?? []).length > 0) {
    shouldInvalidateLists = true;
  }

  for (const statusUpdate of data.message_status_updates ?? []) {
    applyStatusUpdate(queryClient, statusUpdate);
  }

  if (shouldInvalidateLists) {
    void queryClient.invalidateQueries({ queryKey: [CONVERSATIONS_KEY] });
  }
  if (shouldNotify && soundEnabled) {
    void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
  }
}

/**
 * Polling de tempo real: a cada 5s consulta /realtime/poll, mescla novidades
 * nos caches do React Query e pausa quando o app vai para background.
 * Deve ser montado uma única vez (root layout, quando autenticado).
 */
export function useRealtime(): void {
  const queryClient = useQueryClient();
  const activeConversationId = useUiStore((s) => s.activeConversationId);
  const soundEnabled = useSettingsStore((s) => s.soundEnabled);

  const activeRef = useRef<number | null>(activeConversationId);
  activeRef.current = activeConversationId;
  const soundRef = useRef(soundEnabled);
  soundRef.current = soundEnabled;

  const lastUpdateRef = useRef<number>(Date.now());

  useEffect(() => {
    let disposed = false;
    let interval: ReturnType<typeof setInterval> | null = null;
    let polling = false;

    void SecureStore.getItemAsync(LAST_UPDATE_KEY).then((value) => {
      const parsed = value ? Number(value) : NaN;
      if (!disposed && Number.isFinite(parsed) && parsed > 0) {
        lastUpdateRef.current = parsed;
      }
    });

    const tick = async () => {
      if (polling) return;
      polling = true;
      try {
        const data = await poll({
          subscribed_conversations: activeRef.current != null ? [activeRef.current] : [],
          last_update_time: lastUpdateRef.current,
          activity_type: 'active',
        });
        lastUpdateRef.current = data.timestamp || Date.now();
        void SecureStore.setItemAsync(LAST_UPDATE_KEY, String(lastUpdateRef.current));
        if (!disposed) {
          handlePollResult(queryClient, data, activeRef.current, soundRef.current);
        }
      } catch {
        // falha silenciosa: tenta de novo no próximo tick
      } finally {
        polling = false;
      }
    };

    const start = () => {
      if (interval) return;
      void tick();
      interval = setInterval(() => void tick(), POLL_INTERVAL_MS);
    };
    const stop = () => {
      if (interval) {
        clearInterval(interval);
        interval = null;
      }
    };

    start();
    const subscription = AppState.addEventListener('change', (state) => {
      if (state === 'active') start();
      else stop();
    });

    return () => {
      disposed = true;
      stop();
      subscription.remove();
    };
  }, [queryClient]);
}
