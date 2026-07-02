import { Ionicons } from '@expo/vector-icons';
import { FlashList } from '@shopify/flash-list';
import { useInfiniteQuery, useQuery } from '@tanstack/react-query';
import { useRouter } from 'expo-router';
import React, { useCallback, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Modal,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { getErrorMessage } from '@/api/client';
import { checkExistingConversation, createConversation } from '@/api/conversations';
import { getContacts, getWhatsAppAccounts } from '@/api/misc';
import { Avatar } from '@/components/Avatar';
import { EmptyState } from '@/components/EmptyState';
import { useTheme } from '@/theme';
import type { ContactSummary } from '@/types';
import { formatPhone } from '@/utils/phone';

export default function ContactsScreen() {
  const { colors, typography } = useTheme();
  const insets = useSafeAreaInsets();
  const router = useRouter();

  const [searchText, setSearchText] = useState('');
  const [search, setSearch] = useState('');
  const [selected, setSelected] = useState<ContactSummary | null>(null);
  const [pickingAccount, setPickingAccount] = useState(false);
  const [creating, setCreating] = useState(false);

  useEffect(() => {
    const timeout = setTimeout(() => setSearch(searchText.trim()), 400);
    return () => clearTimeout(timeout);
  }, [searchText]);

  const query = useInfiniteQuery({
    queryKey: ['contacts', search],
    queryFn: ({ pageParam }) => getContacts(search, pageParam),
    initialPageParam: 1,
    getNextPageParam: (lastPage) =>
      lastPage.pagination.has_next ? lastPage.pagination.page + 1 : undefined,
  });

  const contacts = query.data?.pages.flatMap((page) => page.items) ?? [];

  const accounts = useQuery({
    queryKey: ['whatsapp-accounts'],
    queryFn: getWhatsAppAccounts,
    enabled: selected != null,
    staleTime: 5 * 60 * 1000,
  });

  const closeModal = () => {
    setSelected(null);
    setPickingAccount(false);
    setCreating(false);
  };

  const openConversation = useCallback(
    (conversationId: number) => {
      closeModal();
      router.push(`/conversations/${conversationId}`);
    },
    [router],
  );

  const startConversation = async () => {
    if (!selected) return;
    setCreating(true);
    try {
      const existing = await checkExistingConversation({ contact_id: selected.id });
      if (existing.conversation_id != null) {
        openConversation(existing.conversation_id);
        return;
      }
      const available = accounts.data ?? [];
      if (available.length === 0) {
        Alert.alert('Nova conversa', 'Nenhuma conta de WhatsApp configurada.');
        return;
      }
      if (available.length === 1) {
        await createWithAccount(available[0].id);
        return;
      }
      setPickingAccount(true);
    } catch (error) {
      Alert.alert('Nova conversa', getErrorMessage(error));
    } finally {
      setCreating(false);
    }
  };

  const createWithAccount = async (accountId: number) => {
    if (!selected) return;
    setCreating(true);
    try {
      const conversation = await createConversation({
        contact_id: selected.id,
        integration_account_id: accountId,
      });
      openConversation(conversation.id);
    } catch (error) {
      Alert.alert('Nova conversa', getErrorMessage(error));
    } finally {
      setCreating(false);
    }
  };

  const renderItem = useCallback(
    ({ item }: { item: ContactSummary }) => (
      <Pressable
        onPress={() => setSelected(item)}
        style={({ pressed }) => [
          styles.item,
          {
            backgroundColor: pressed ? colors.surfaceAlt : colors.surface,
            borderBottomColor: colors.border,
          },
        ]}
      >
        <Avatar name={item.name} uri={item.avatar} size={42} />
        <View style={styles.itemBody}>
          <Text style={[typography.subtitle, { color: colors.textPrimary }]} numberOfLines={1}>
            {item.name}
          </Text>
          {item.phone ? (
            <Text style={[typography.caption, { color: colors.textSecondary }]}>
              {formatPhone(item.phone)}
            </Text>
          ) : item.email ? (
            <Text style={[typography.caption, { color: colors.textSecondary }]} numberOfLines={1}>
              {item.email}
            </Text>
          ) : null}
        </View>
        <Ionicons name="chevron-forward" size={16} color={colors.textSecondary} />
      </Pressable>
    ),
    [colors, typography],
  );

  return (
    <View style={[styles.container, { backgroundColor: colors.background, paddingTop: insets.top }]}>
      <View style={styles.header}>
        <Text style={[typography.title, { color: colors.textPrimary }]}>Contatos</Text>
      </View>

      <View style={[styles.searchBox, { backgroundColor: colors.surface, borderColor: colors.border }]}>
        <Ionicons name="search" size={18} color={colors.textSecondary} />
        <TextInput
          style={[styles.searchInput, typography.body, { color: colors.textPrimary }]}
          placeholder="Buscar contatos…"
          placeholderTextColor={colors.textSecondary}
          value={searchText}
          onChangeText={setSearchText}
          autoCapitalize="none"
          returnKeyType="search"
        />
      </View>

      {query.isLoading ? (
        <ActivityIndicator color={colors.primary} style={styles.loader} />
      ) : query.isError ? (
        <EmptyState
          icon="cloud-offline-outline"
          title="Erro ao carregar contatos"
          subtitle={getErrorMessage(query.error)}
          actionLabel="Tentar novamente"
          onAction={() => void query.refetch()}
        />
      ) : contacts.length === 0 ? (
        <EmptyState
          icon="people-outline"
          title="Nenhum contato"
          subtitle={search ? 'Nenhum contato encontrado para esta busca.' : 'Sua lista de contatos está vazia.'}
        />
      ) : (
        <FlashList
          data={contacts}
          renderItem={renderItem}
          keyExtractor={(item) => String(item.id)}
          estimatedItemSize={64}
          onEndReached={() => {
            if (query.hasNextPage && !query.isFetchingNextPage) void query.fetchNextPage();
          }}
          onEndReachedThreshold={0.4}
          ListFooterComponent={
            query.isFetchingNextPage ? (
              <ActivityIndicator color={colors.primary} style={styles.loader} />
            ) : null
          }
        />
      )}

      <Modal visible={selected != null} transparent animationType="slide" onRequestClose={closeModal}>
        <Pressable style={styles.backdrop} onPress={closeModal} />
        <View style={[styles.sheet, { backgroundColor: colors.surface }]}>
          {selected ? (
            <>
              <View style={styles.sheetHeader}>
                <Avatar name={selected.name} uri={selected.avatar} size={56} />
                <View style={styles.sheetInfo}>
                  <Text style={[typography.title, { color: colors.textPrimary }]} numberOfLines={1}>
                    {selected.name}
                  </Text>
                  {selected.phone ? (
                    <Text style={[typography.body, { color: colors.textSecondary }]}>
                      {formatPhone(selected.phone)}
                    </Text>
                  ) : null}
                  {selected.email ? (
                    <Text style={[typography.caption, { color: colors.textSecondary }]} numberOfLines={1}>
                      {selected.email}
                    </Text>
                  ) : null}
                </View>
                <Pressable onPress={closeModal} hitSlop={8}>
                  <Ionicons name="close" size={22} color={colors.textPrimary} />
                </Pressable>
              </View>

              {!pickingAccount ? (
                <Pressable
                  onPress={() => void startConversation()}
                  disabled={creating}
                  style={[styles.newButton, { backgroundColor: colors.primary, opacity: creating ? 0.6 : 1 }]}
                >
                  {creating ? (
                    <ActivityIndicator color={colors.onPrimary} />
                  ) : (
                    <>
                      <Ionicons name="chatbubble-ellipses" size={18} color={colors.onPrimary} />
                      <Text style={[typography.subtitle, { color: colors.onPrimary }]}>
                        Nova conversa
                      </Text>
                    </>
                  )}
                </Pressable>
              ) : (
                <View style={styles.accountList}>
                  <Text style={[typography.caption, { color: colors.textSecondary }]}>
                    Escolha a conta para iniciar a conversa:
                  </Text>
                  {(accounts.data ?? []).map((account) => (
                    <Pressable
                      key={account.id}
                      onPress={() => void createWithAccount(account.id)}
                      disabled={creating}
                      style={({ pressed }) => [
                        styles.accountRow,
                        {
                          backgroundColor: pressed ? colors.surfaceAlt : 'transparent',
                          borderColor: colors.border,
                        },
                      ]}
                    >
                      <Ionicons name="logo-whatsapp" size={20} color="#25D366" />
                      <View style={styles.accountInfo}>
                        <Text style={[typography.body, { color: colors.textPrimary }]}>
                          {account.name}
                        </Text>
                        {account.phone ? (
                          <Text style={[typography.caption, { color: colors.textSecondary }]}>
                            {formatPhone(account.phone)}
                          </Text>
                        ) : null}
                      </View>
                    </Pressable>
                  ))}
                </View>
              )}
            </>
          ) : null}
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  header: {
    paddingHorizontal: 16,
    paddingVertical: 10,
  },
  searchBox: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginHorizontal: 16,
    marginBottom: 8,
    paddingHorizontal: 12,
    borderRadius: 10,
    borderWidth: 1,
  },
  searchInput: {
    flex: 1,
    paddingVertical: 9,
  },
  loader: {
    marginVertical: 24,
  },
  item: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  itemBody: {
    flex: 1,
    gap: 2,
  },
  backdrop: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.4)',
  },
  sheet: {
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    padding: 20,
    paddingBottom: 36,
    gap: 16,
  },
  sheetHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  sheetInfo: {
    flex: 1,
    gap: 2,
  },
  newButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 14,
    borderRadius: 10,
  },
  accountList: {
    gap: 8,
  },
  accountRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    padding: 12,
    borderRadius: 10,
    borderWidth: 1,
  },
  accountInfo: {
    flex: 1,
  },
});
