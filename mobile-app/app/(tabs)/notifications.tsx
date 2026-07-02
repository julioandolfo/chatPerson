import { Ionicons } from '@expo/vector-icons';
import { FlashList } from '@shopify/flash-list';
import {
  useInfiniteQuery,
  useMutation,
  useQueryClient,
} from '@tanstack/react-query';
import { useRouter } from 'expo-router';
import React, { useCallback } from 'react';
import {
  ActivityIndicator,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { getErrorMessage } from '@/api/client';
import {
  listNotifications,
  markAllNotificationsRead,
  markNotificationRead,
} from '@/api/notifications';
import { EmptyState } from '@/components/EmptyState';
import { useTheme } from '@/theme';
import type { AppNotification } from '@/types';
import { formatConversationTime } from '@/utils/format';

export default function NotificationsScreen() {
  const { colors, typography } = useTheme();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const queryClient = useQueryClient();

  const query = useInfiniteQuery({
    queryKey: ['notifications', 'list'],
    queryFn: ({ pageParam }) => listNotifications(pageParam),
    initialPageParam: 1,
    getNextPageParam: (lastPage) =>
      lastPage.pagination.has_next ? lastPage.pagination.page + 1 : undefined,
  });

  const notifications = query.data?.pages.flatMap((page) => page.items) ?? [];

  const invalidateAll = useCallback(() => {
    void queryClient.invalidateQueries({ queryKey: ['notifications'] });
  }, [queryClient]);

  const markAll = useMutation({
    mutationFn: markAllNotificationsRead,
    onSuccess: invalidateAll,
  });

  const handlePress = useCallback(
    (notification: AppNotification) => {
      if (!notification.is_read) {
        markNotificationRead(notification.id).then(invalidateAll).catch(() => {});
      }
      const conversationId = notification.data?.conversation_id;
      if (conversationId != null) {
        router.push(`/conversations/${conversationId}`);
      }
    },
    [invalidateAll, router],
  );

  const renderItem = useCallback(
    ({ item }: { item: AppNotification }) => (
      <Pressable
        onPress={() => handlePress(item)}
        style={({ pressed }) => [
          styles.item,
          {
            backgroundColor: pressed
              ? colors.surfaceAlt
              : item.is_read
                ? colors.surface
                : `${colors.primary}11`,
            borderBottomColor: colors.border,
          },
        ]}
      >
        {!item.is_read ? <View style={[styles.dot, { backgroundColor: colors.primary }]} /> : null}
        <View style={styles.itemBody}>
          <Text
            style={[
              typography.body,
              { color: colors.textPrimary, fontWeight: item.is_read ? '400' : '600' },
            ]}
            numberOfLines={1}
          >
            {item.title}
          </Text>
          <Text style={[typography.caption, { color: colors.textSecondary }]} numberOfLines={2}>
            {item.body}
          </Text>
        </View>
        <Text style={[typography.caption, { color: colors.textSecondary }]}>
          {formatConversationTime(item.created_at)}
        </Text>
      </Pressable>
    ),
    [colors, handlePress, typography],
  );

  return (
    <View style={[styles.container, { backgroundColor: colors.background, paddingTop: insets.top }]}>
      <View style={styles.header}>
        <Text style={[typography.title, { color: colors.textPrimary }]}>Notificações</Text>
        <Pressable onPress={() => markAll.mutate()} hitSlop={8} disabled={markAll.isPending}>
          {markAll.isPending ? (
            <ActivityIndicator size="small" color={colors.primary} />
          ) : (
            <Ionicons name="checkmark-done" size={22} color={colors.primary} />
          )}
        </Pressable>
      </View>

      {query.isLoading ? (
        <ActivityIndicator color={colors.primary} style={styles.loader} />
      ) : query.isError ? (
        <EmptyState
          icon="cloud-offline-outline"
          title="Erro ao carregar notificações"
          subtitle={getErrorMessage(query.error)}
          actionLabel="Tentar novamente"
          onAction={() => void query.refetch()}
        />
      ) : notifications.length === 0 ? (
        <EmptyState
          icon="notifications-off-outline"
          title="Sem notificações"
          subtitle="Você está em dia! Nenhuma notificação por aqui."
        />
      ) : (
        <FlashList
          data={notifications}
          renderItem={renderItem}
          keyExtractor={(item) => String(item.id)}
          estimatedItemSize={72}
          onEndReached={() => {
            if (query.hasNextPage && !query.isFetchingNextPage) void query.fetchNextPage();
          }}
          onEndReachedThreshold={0.4}
          refreshControl={
            <RefreshControl
              refreshing={query.isRefetching && !query.isFetchingNextPage}
              onRefresh={() => void query.refetch()}
              tintColor={colors.primary}
            />
          }
          ListFooterComponent={
            query.isFetchingNextPage ? (
              <ActivityIndicator color={colors.primary} style={styles.loader} />
            ) : null
          }
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingVertical: 10,
  },
  loader: {
    marginVertical: 24,
  },
  item: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  dot: {
    width: 8,
    height: 8,
    borderRadius: 4,
  },
  itemBody: {
    flex: 1,
    gap: 2,
  },
});
