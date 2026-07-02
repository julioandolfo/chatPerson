import { Ionicons } from '@expo/vector-icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import React, { useState } from 'react';
import {
  ActivityIndicator,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';

import { getErrorMessage } from '@/api/client';
import { sendCloudTemplate } from '@/api/conversations';
import { getTemplates } from '@/api/misc';
import { messagesQueryKey } from '@/hooks/useMessages';
import { useTheme } from '@/theme';
import type { CloudTemplate } from '@/types';

interface CloudTemplateModalProps {
  conversationId: number;
  integrationAccountId?: number | null;
  visible: boolean;
  onClose: () => void;
}

/** Conta os placeholders {{1}}, {{2}}… do corpo do template. */
function countParams(template: CloudTemplate): number {
  const body = template.body ?? '';
  const matches = body.match(/\{\{\s*\d+\s*\}\}/g);
  if (!matches) return 0;
  const indexes = matches
    .map((m) => Number(m.replace(/\D+/g, '')))
    .filter((n) => Number.isFinite(n));
  return indexes.length > 0 ? Math.max(...indexes) : 0;
}

/**
 * Modal para reabrir a janela de 24h do WhatsApp Cloud enviando um template
 * aprovado (com preenchimento de parâmetros quando o corpo possui {{n}}).
 */
export function CloudTemplateModal({
  conversationId,
  integrationAccountId,
  visible,
  onClose,
}: CloudTemplateModalProps) {
  const { colors, typography } = useTheme();
  const queryClient = useQueryClient();

  const [selected, setSelected] = useState<CloudTemplate | null>(null);
  const [params, setParams] = useState<string[]>([]);
  const [error, setError] = useState<string | null>(null);

  const templates = useQuery({
    queryKey: ['templates', integrationAccountId ?? 'all'],
    queryFn: () => getTemplates(integrationAccountId ?? undefined),
    enabled: visible,
    staleTime: 5 * 60 * 1000,
  });

  const send = useMutation({
    mutationFn: () =>
      sendCloudTemplate(conversationId, {
        template_name: (selected as CloudTemplate).name,
        language: (selected as CloudTemplate).language,
        params,
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: messagesQueryKey(conversationId) });
      void queryClient.invalidateQueries({ queryKey: ['cloud-window', conversationId] });
      handleClose();
    },
    onError: (err) => setError(getErrorMessage(err)),
  });

  const selectTemplate = (template: CloudTemplate) => {
    setSelected(template);
    setParams(new Array<string>(countParams(template)).fill(''));
    setError(null);
  };

  const handleClose = () => {
    setSelected(null);
    setParams([]);
    setError(null);
    onClose();
  };

  const canSend =
    selected != null && params.every((p) => p.trim().length > 0) && !send.isPending;

  return (
    <Modal visible={visible} transparent animationType="slide" onRequestClose={handleClose}>
      <Pressable style={styles.backdrop} onPress={handleClose} />
      <View style={[styles.sheet, { backgroundColor: colors.surface }]}>
        <View style={styles.header}>
          {selected ? (
            <Pressable onPress={() => setSelected(null)} hitSlop={8}>
              <Ionicons name="chevron-back" size={22} color={colors.textPrimary} />
            </Pressable>
          ) : (
            <View style={styles.spacer} />
          )}
          <Text style={[typography.subtitle, { color: colors.textPrimary }]}>
            {selected ? selected.name : 'Enviar template'}
          </Text>
          <Pressable onPress={handleClose} hitSlop={8}>
            <Ionicons name="close" size={22} color={colors.textPrimary} />
          </Pressable>
        </View>

        {error ? (
          <Text style={[typography.caption, styles.error, { color: colors.danger }]}>{error}</Text>
        ) : null}

        {!selected ? (
          <ScrollView showsVerticalScrollIndicator={false}>
            {templates.isLoading ? (
              <ActivityIndicator color={colors.primary} style={styles.loader} />
            ) : null}
            {templates.isError ? (
              <Text style={[typography.caption, styles.loaderText, { color: colors.danger }]}>
                Erro ao carregar templates.
              </Text>
            ) : null}
            {(templates.data ?? []).length === 0 && !templates.isLoading && !templates.isError ? (
              <Text style={[typography.caption, styles.loaderText, { color: colors.textSecondary }]}>
                Nenhum template disponível.
              </Text>
            ) : null}
            {(templates.data ?? []).map((template) => (
              <Pressable
                key={`${template.name}-${template.language}`}
                onPress={() => selectTemplate(template)}
                style={({ pressed }) => [
                  styles.templateRow,
                  {
                    backgroundColor: pressed ? colors.surfaceAlt : 'transparent',
                    borderBottomColor: colors.border,
                  },
                ]}
              >
                <View style={styles.templateInfo}>
                  <Text style={[typography.body, { color: colors.textPrimary, fontWeight: '600' }]}>
                    {template.name}
                  </Text>
                  {template.body ? (
                    <Text
                      style={[typography.caption, { color: colors.textSecondary }]}
                      numberOfLines={2}
                    >
                      {template.body}
                    </Text>
                  ) : null}
                  <Text style={[typography.badge, { color: colors.textSecondary }]}>
                    {template.language}
                    {template.category ? ` · ${template.category}` : ''}
                  </Text>
                </View>
                <Ionicons name="chevron-forward" size={16} color={colors.textSecondary} />
              </Pressable>
            ))}
          </ScrollView>
        ) : (
          <ScrollView showsVerticalScrollIndicator={false} keyboardShouldPersistTaps="handled">
            {selected.body ? (
              <View style={[styles.previewBox, { backgroundColor: colors.surfaceAlt }]}>
                <Text style={[typography.body, { color: colors.textPrimary }]}>{selected.body}</Text>
              </View>
            ) : null}

            {params.map((value, index) => (
              <TextInput
                key={index}
                style={[
                  typography.body,
                  styles.paramInput,
                  { backgroundColor: colors.surfaceAlt, color: colors.textPrimary },
                ]}
                placeholder={`Parâmetro {{${index + 1}}}`}
                placeholderTextColor={colors.textSecondary}
                value={value}
                onChangeText={(text) =>
                  setParams((prev) => prev.map((p, i) => (i === index ? text : p)))
                }
              />
            ))}

            <Pressable
              onPress={() => send.mutate()}
              disabled={!canSend}
              style={[
                styles.sendButton,
                { backgroundColor: colors.primary, opacity: canSend ? 1 : 0.5 },
              ]}
            >
              {send.isPending ? (
                <ActivityIndicator color={colors.onPrimary} />
              ) : (
                <Text style={[typography.subtitle, { color: colors.onPrimary }]}>
                  Enviar template
                </Text>
              )}
            </Pressable>
          </ScrollView>
        )}
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  backdrop: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.4)',
  },
  sheet: {
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    paddingHorizontal: 16,
    paddingTop: 12,
    paddingBottom: 32,
    maxHeight: '80%',
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 12,
  },
  spacer: {
    width: 22,
  },
  error: {
    marginBottom: 8,
  },
  loader: {
    marginVertical: 20,
  },
  loaderText: {
    marginVertical: 20,
    textAlign: 'center',
  },
  templateRow: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 12,
    borderBottomWidth: StyleSheet.hairlineWidth,
    gap: 8,
  },
  templateInfo: {
    flex: 1,
    gap: 2,
  },
  previewBox: {
    borderRadius: 10,
    padding: 12,
    marginBottom: 12,
  },
  paramInput: {
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 10,
    marginBottom: 8,
  },
  sendButton: {
    alignItems: 'center',
    paddingVertical: 14,
    borderRadius: 10,
    marginTop: 8,
  },
});
