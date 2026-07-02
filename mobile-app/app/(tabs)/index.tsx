import { Ionicons } from '@expo/vector-icons';
import { FlashList } from '@shopify/flash-list';
import { useRouter } from 'expo-router';
import React, { useCallback, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { getErrorMessage } from '@/api/client';
import { ConversationTile } from '@/components/ConversationTile';
import { EmptyState } from '@/components/EmptyState';
import { FilterSheet } from '@/components/FilterSheet';
import { useConversations } from '@/hooks/useConversations';
import { hasActiveAdvancedFilters, useUiStore } from '@/stores/ui';
import { useTheme } from '@/theme';
import type { Conversation, ConversationFilter } from '@/types';

const TAB_OPTIONS: { value: ConversationFilter; label: string }[] = [
  { value: 'mine', label: 'Minhas' },
  { value: 'unassigned', label: 'Não atribuídas' },
  { value: 'all', label: 'Todas' },
];

function SkeletonRow() {
  const { colors } = useTheme();
  return (
    <View style={[styles.skeletonRow, { borderBottomColor: colors.border }]}>
      <View style={[styles.skeletonAvatar, { backgroundColor: colors.surfaceAlt }]} />
      <View style={styles.skeletonBody}>
        <View style={[styles.skeletonLine, { backgroundColor: colors.surfaceAlt, width: '55%' }]} />
        <View style={[styles.skeletonLine, { backgroundColor: colors.surfaceAlt, width: '85%' }]} />
      </View>
    </View>
  );
}

export default function ConversationsScreen() {
  const { colors, typography } = useTheme();
  const insets = useSafeAreaInsets();
  const router = useRouter();

  const filter = useUiStore((s) => s.filter);
  const setFilter = useUiStore((s) => s.setFilter);
  const setSearch = useUiStore((s) => s.setSearch);
  const advanced = useUiStore((s) => s.advanced);

  const [searchText, setSearchText] = useState('');
  const [filterSheetVisible, setFilterSheetVisible] = useState(false);

  // Debounce da busca (400ms) antes de aplicar na store/query.
  useEffect(() => {
    const timeout = setTimeout(() => setSearch(searchText.trim()), 400);
    return () => clearTimeout(timeout);
  }, [searchText, setSearch]);

  const {
    conversations,
    isLoading,
    isError,
    error,
    refetch,
    isRefetching,
    fetchNextPage,
    hasNextPage,
    isFetchingNextPage,
  } = useConversations();

  const handleOpen = useCallback(
    (conversation: Conversation) => {
      router.push(`/conversations/${conversation.id}`);
    },
    [router],
  );

  const renderItem = useCallback(
    ({ item }: { item: Conversation }) => (
      <ConversationTile conversation={item} onPress={handleOpen} />
    ),
    [handleOpen],
  );

  const hasAdvanced = hasActiveAdvancedFilters(advanced);

  return (
    <View style={[styles.container, { backgroundColor: colors.background, paddingTop: insets.top }]}>
      <View style={styles.header}>
        <Text style={[typography.title, { color: colors.textPrimary }]}>Conversas</Text>
        <Pressable onPress={() => setFilterSheetVisible(true)} hitSlop={8}>
          <View>
            <Ionicons name="options-outline" size={24} color={colors.textPrimary} />
            {hasAdvanced ? (
              <View style={[styles.filterDot, { backgroundColor: colors.primary }]} />
            ) : null}
          </View>
        </Pressable>
      </View>

      <View style={[styles.searchBox, { backgroundColor: colors.surface, borderColor: colors.border }]}>
        <Ionicons name="search" size={18} color={colors.textSecondary} />
        <TextInput
          style={[styles.searchInput, typography.body, { color: colors.textPrimary }]}
          placeholder="Buscar conversas…"
          placeholderTextColor={colors.textSecondary}
          value={searchText}
          onChangeText={setSearchText}
          autoCapitalize="none"
          returnKeyType="search"
        />
        {searchText.length > 0 ? (
          <Pressable onPress={() => setSearchText('')} hitSlop={8}>
            <Ionicons name="close-circle" size={18} color={colors.textSecondary} />
          </Pressable>
        ) : null}
      </View>

      <View style={styles.tabsRow}>
        {TAB_OPTIONS.map((tab) => {
          const active = filter === tab.value;
          return (
            <Pressable
              key={tab.value}
              onPress={() => setFilter(tab.value)}
              style={[
                styles.tabChip,
                {
                  backgroundColor: active ? colors.primary : colors.surface,
                  borderColor: active ? colors.primary : colors.border,
                },
              ]}
            >
              <Text
                style={[
                  typography.caption,
                  { color: active ? colors.onPrimary : colors.textSecondary, fontWeight: '600' },
                ]}
              >
                {tab.label}
              </Text>
            </Pressable>
          );
        })}
      </View>

      {isLoading ? (
        <View style={styles.list}>
          {Array.from({ length: 8 }).map((_, index) => (
            <SkeletonRow key={index} />
          ))}
        </View>
      ) : isError ? (
        <EmptyState
          icon="cloud-offline-outline"
          title="Erro ao carregar conversas"
          subtitle={getErrorMessage(error)}
          actionLabel="Tentar novamente"
          onAction={() => void refetch()}
        />
      ) : conversations.length === 0 ? (
        <EmptyState
          icon="chatbubbles-outline"
          title="Nenhuma conversa"
          subtitle={
            filter === 'mine'
              ? 'Você ainda não tem conversas atribuídas.'
              : 'Nenhuma conversa encontrada com os filtros atuais.'
          }
        />
      ) : (
        <FlashList
          data={conversations}
          renderItem={renderItem}
          keyExtractor={(item) => String(item.id)}
          estimatedItemSize={84}
          onEndReached={() => {
            if (hasNextPage && !isFetchingNextPage) void fetchNextPage();
          }}
          onEndReachedThreshold={0.4}
          refreshControl={
            <RefreshControl
              refreshing={isRefetching && !isFetchingNextPage}
              onRefresh={() => void refetch()}
              tintColor={colors.primary}
            />
          }
          ListFooterComponent={
            isFetchingNextPage ? (
              <ActivityIndicator color={colors.primary} style={styles.footerLoader} />
            ) : null
          }
        />
      )}

      <FilterSheet visible={filterSheetVisible} onClose={() => setFilterSheetVisible(false)} />
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
  filterDot: {
    position: 'absolute',
    top: -2,
    right: -2,
    width: 8,
    height: 8,
    borderRadius: 4,
  },
  searchBox: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginHorizontal: 16,
    paddingHorizontal: 12,
    borderRadius: 10,
    borderWidth: 1,
  },
  searchInput: {
    flex: 1,
    paddingVertical: 9,
  },
  tabsRow: {
    flexDirection: 'row',
    gap: 8,
    paddingHorizontal: 16,
    paddingVertical: 10,
  },
  tabChip: {
    paddingHorizontal: 14,
    paddingVertical: 6,
    borderRadius: 16,
    borderWidth: 1,
  },
  list: {
    flex: 1,
  },
  footerLoader: {
    marginVertical: 16,
  },
  skeletonRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingHorizontal: 16,
    paddingVertical: 14,
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  skeletonAvatar: {
    width: 46,
    height: 46,
    borderRadius: 23,
  },
  skeletonBody: {
    flex: 1,
    gap: 8,
  },
  skeletonLine: {
    height: 12,
    borderRadius: 6,
  },
});
