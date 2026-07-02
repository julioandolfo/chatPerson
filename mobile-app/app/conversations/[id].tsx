import { Ionicons } from '@expo/vector-icons';
import { FlashList } from '@shopify/flash-list';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Image } from 'expo-image';
import { useLocalSearchParams, useRouter } from 'expo-router';
import React, { useCallback, useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Modal,
  Platform,
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { getErrorMessage } from '@/api/client';
import { getCloudWindow, getConversation, markConversationRead } from '@/api/conversations';
import { Avatar } from '@/components/Avatar';
import { ChannelIcon, CHANNEL_LABELS } from '@/components/ChannelIcon';
import { CloudTemplateModal } from '@/components/CloudTemplateModal';
import { Composer, type ComposerSendPayload } from '@/components/Composer';
import { ConversationDetailsSheet } from '@/components/ConversationDetailsSheet';
import { EmptyState } from '@/components/EmptyState';
import { MessageBubble } from '@/components/MessageBubble';
import { STATUS_LABELS } from '@/components/StatusBadge';
import { updateConversationInCaches } from '@/hooks/useConversations';
import { removeLocalMessage, useMessages, useSendMessage } from '@/hooks/useMessages';
import { useUiStore } from '@/stores/ui';
import { useTheme } from '@/theme';
import type { Conversation, Message, SendMessageInput } from '@/types';

let localIdCounter = 0;
function nextLocalId(): string {
  localIdCounter += 1;
  return `local-${Date.now()}-${localIdCounter}`;
}

export default function ConversationScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const conversationId = Number(id);

  const { colors, typography } = useTheme();
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const queryClient = useQueryClient();
  const setActiveConversationId = useUiStore((s) => s.setActiveConversationId);

  const [quoted, setQuoted] = useState<Message | null>(null);
  const [isNote, setIsNote] = useState(false);
  const [detailsVisible, setDetailsVisible] = useState(false);
  const [templateModalVisible, setTemplateModalVisible] = useState(false);
  const [fullscreenImage, setFullscreenImage] = useState<string | null>(null);

  /** Payloads de envios pendentes/com erro, para permitir reenviar. */
  const pendingPayloads = useRef(new Map<string, SendMessageInput>());

  const conversation = useQuery({
    queryKey: ['conversation', conversationId],
    queryFn: () => getConversation(conversationId),
    enabled: Number.isFinite(conversationId) && conversationId > 0,
  });

  const cloudWindow = useQuery({
    queryKey: ['cloud-window', conversationId],
    queryFn: () => getCloudWindow(conversationId),
    enabled: conversation.data?.channel === 'whatsapp',
    refetchInterval: 60_000,
  });

  const {
    messages,
    isLoading,
    isError,
    error,
    refetch,
    fetchNextPage,
    hasNextPage,
    isFetchingNextPage,
  } = useMessages(conversationId);

  const sendMutation = useSendMessage(conversationId);

  // Inscreve a conversa no polling de realtime enquanto a tela está aberta.
  useEffect(() => {
    setActiveConversationId(conversationId);
    return () => setActiveConversationId(null);
  }, [conversationId, setActiveConversationId]);

  // Marca como lida ao abrir e zera o badge local.
  useEffect(() => {
    if (!Number.isFinite(conversationId) || conversationId <= 0) return;
    markConversationRead(conversationId).catch(() => {});
    updateConversationInCaches(queryClient, conversationId, (c) => ({ ...c, unread_count: 0 }));
  }, [conversationId, queryClient]);

  const doSend = useCallback(
    (input: SendMessageInput) => {
      pendingPayloads.current.set(input.local_id, input);
      sendMutation.mutate(input, {
        onSuccess: () => {
          pendingPayloads.current.delete(input.local_id);
        },
      });
    },
    [sendMutation],
  );

  const handleSend = useCallback(
    (payload: ComposerSendPayload) => {
      const input: SendMessageInput = {
        local_id: nextLocalId(),
        content: payload.content,
        attachments: payload.attachments,
        is_note: isNote,
        quoted_message_id: quoted?.id != null && quoted.id > 0 ? quoted.id : undefined,
        quoted_text: quoted?.content ?? undefined,
        quoted_sender_name: quoted?.sender_name ?? undefined,
      };
      doSend(input);
      setQuoted(null);
    },
    [doSend, isNote, quoted],
  );

  const handleRetry = useCallback(
    (localId: string) => {
      const payload = pendingPayloads.current.get(localId);
      if (!payload) return;
      removeLocalMessage(queryClient, conversationId, localId);
      doSend(payload);
    },
    [conversationId, doSend, queryClient],
  );

  const handleQuote = useCallback((message: Message) => {
    setQuoted(message);
  }, []);

  const handleImagePress = useCallback((url: string) => {
    setFullscreenImage(url);
  }, []);

  const renderItem = useCallback(
    ({ item }: { item: Message }) => (
      <MessageBubble
        message={item}
        onQuote={handleQuote}
        onRetry={handleRetry}
        onImagePress={handleImagePress}
      />
    ),
    [handleImagePress, handleQuote, handleRetry],
  );

  const detail: Conversation | undefined = conversation.data;
  const isCloudBlocked =
    cloudWindow.data?.is_cloud === true && cloudWindow.data.within_window === false;
  const isClosed = detail?.status === 'closed';

  return (
    <View style={[styles.container, { backgroundColor: colors.background }]}>
      {/* Header */}
      <View
        style={[
          styles.header,
          {
            backgroundColor: colors.surface,
            borderBottomColor: colors.border,
            paddingTop: insets.top + 6,
          },
        ]}
      >
        <Pressable onPress={() => router.back()} hitSlop={8} style={styles.backButton}>
          <Ionicons name="chevron-back" size={26} color={colors.textPrimary} />
        </Pressable>

        <Pressable style={styles.headerCenter} onPress={() => setDetailsVisible(true)}>
          <Avatar name={detail?.contact.name} uri={detail?.contact.avatar} size={38} />
          <View style={styles.headerInfo}>
            <Text style={[typography.subtitle, { color: colors.textPrimary }]} numberOfLines={1}>
              {detail?.contact.name ?? 'Conversa'}
            </Text>
            {detail ? (
              <View style={styles.headerMetaRow}>
                <ChannelIcon channel={detail.channel} size={12} />
                <Text style={[typography.caption, { color: colors.textSecondary }]} numberOfLines={1}>
                  {CHANNEL_LABELS[detail.channel]} · {STATUS_LABELS[detail.status] ?? detail.status}
                </Text>
              </View>
            ) : null}
          </View>
        </Pressable>

        <Pressable onPress={() => setDetailsVisible(true)} hitSlop={8} style={styles.menuButton}>
          <Ionicons name="ellipsis-vertical" size={20} color={colors.textPrimary} />
        </Pressable>
      </View>

      <KeyboardAvoidingView
        style={styles.flex}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        keyboardVerticalOffset={0}
      >
        {/* Mensagens */}
        {isLoading ? (
          <View style={styles.centered}>
            <ActivityIndicator size="large" color={colors.primary} />
          </View>
        ) : isError ? (
          <EmptyState
            icon="cloud-offline-outline"
            title="Erro ao carregar mensagens"
            subtitle={getErrorMessage(error)}
            actionLabel="Tentar novamente"
            onAction={() => void refetch()}
          />
        ) : messages.length === 0 ? (
          <EmptyState
            icon="chatbubble-outline"
            title="Sem mensagens"
            subtitle="Envie a primeira mensagem para começar a conversa."
          />
        ) : (
          <FlashList
            data={messages}
            renderItem={renderItem}
            keyExtractor={(item) => item.local_id ?? String(item.id)}
            estimatedItemSize={72}
            inverted
            onEndReached={() => {
              if (hasNextPage && !isFetchingNextPage) void fetchNextPage();
            }}
            onEndReachedThreshold={0.3}
            ListFooterComponent={
              isFetchingNextPage ? (
                <ActivityIndicator color={colors.primary} style={styles.paginationLoader} />
              ) : null
            }
            contentContainerStyle={styles.listContent}
          />
        )}

        {/* Banner janela 24h (WhatsApp Cloud) ou conversa resolvida */}
        {isCloudBlocked ? (
          <View style={[styles.cloudBanner, { backgroundColor: `${colors.warning}33`, borderColor: colors.warning }]}>
            <Ionicons name="time-outline" size={18} color={colors.textPrimary} />
            <Text style={[typography.caption, styles.cloudBannerText, { color: colors.textPrimary }]}>
              A janela de 24h expirou. Para retomar a conversa, envie um template aprovado.
            </Text>
            <Pressable
              onPress={() => setTemplateModalVisible(true)}
              style={[styles.cloudBannerButton, { backgroundColor: colors.primary }]}
            >
              <Text style={[typography.badge, { color: colors.onPrimary }]}>Enviar template</Text>
            </Pressable>
          </View>
        ) : null}

        {isClosed && !isCloudBlocked ? (
          <View style={[styles.closedBanner, { backgroundColor: colors.surfaceAlt }]}>
            <Text style={[typography.caption, { color: colors.textSecondary }]}>
              Conversa resolvida — envie uma mensagem para continuar ou reabra pelos detalhes.
            </Text>
          </View>
        ) : null}

        {/* Composer (bloqueado quando fora da janela Cloud, exceto notas) */}
        <Composer
          onSend={handleSend}
          isNote={isNote}
          onToggleNote={() => setIsNote((v) => !v)}
          quoted={quoted}
          onClearQuote={() => setQuoted(null)}
          disabled={isCloudBlocked && !isNote}
        />
        <View style={{ height: insets.bottom }} />
      </KeyboardAvoidingView>

      {/* Sheet de detalhes/ações */}
      {detail ? (
        <ConversationDetailsSheet
          conversation={detail}
          visible={detailsVisible}
          onClose={() => setDetailsVisible(false)}
        />
      ) : null}

      {/* Modal de template Cloud */}
      <CloudTemplateModal
        conversationId={conversationId}
        integrationAccountId={detail?.integration_account_id}
        visible={templateModalVisible}
        onClose={() => setTemplateModalVisible(false)}
      />

      {/* Visualizador de imagem em tela cheia */}
      <Modal
        visible={fullscreenImage != null}
        transparent
        animationType="fade"
        onRequestClose={() => setFullscreenImage(null)}
      >
        <View style={styles.imageViewer}>
          <Pressable
            style={[styles.imageViewerClose, { top: insets.top + 10 }]}
            onPress={() => setFullscreenImage(null)}
            hitSlop={12}
          >
            <Ionicons name="close" size={28} color="#FFFFFF" />
          </Pressable>
          {fullscreenImage ? (
            <Image
              source={{ uri: fullscreenImage }}
              style={styles.imageViewerImage}
              contentFit="contain"
            />
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
  flex: {
    flex: 1,
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 8,
    paddingBottom: 8,
    borderBottomWidth: StyleSheet.hairlineWidth,
    gap: 6,
  },
  backButton: {
    padding: 2,
  },
  headerCenter: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  headerInfo: {
    flex: 1,
    gap: 1,
  },
  headerMetaRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
  },
  menuButton: {
    padding: 6,
  },
  centered: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  listContent: {
    paddingVertical: 8,
  },
  paginationLoader: {
    marginVertical: 12,
  },
  cloudBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginHorizontal: 12,
    marginBottom: 6,
    padding: 10,
    borderRadius: 10,
    borderWidth: 1,
  },
  cloudBannerText: {
    flex: 1,
  },
  cloudBannerButton: {
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 8,
  },
  closedBanner: {
    alignItems: 'center',
    marginHorizontal: 12,
    marginBottom: 6,
    padding: 8,
    borderRadius: 8,
  },
  imageViewer: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.95)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  imageViewerClose: {
    position: 'absolute',
    right: 16,
    zIndex: 10,
  },
  imageViewerImage: {
    width: '100%',
    height: '80%',
  },
});
