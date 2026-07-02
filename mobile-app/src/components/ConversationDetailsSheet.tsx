import { Ionicons } from '@expo/vector-icons';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import React, { useState } from 'react';
import {
  ActivityIndicator,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  TextInput,
  View,
} from 'react-native';

import { getErrorMessage } from '@/api/client';
import {
  addConversationTag,
  addNote,
  assignConversation,
  closeConversation,
  listNotes,
  moveConversationStage,
  removeConversationTag,
  reopenConversation,
  setConversationDepartment,
} from '@/api/conversations';
import { getDepartments, getFunnelStages, getTags } from '@/api/misc';
import { Avatar } from '@/components/Avatar';
import { CHANNEL_LABELS } from '@/components/ChannelIcon';
import { StatusBadge } from '@/components/StatusBadge';
import { TagChip } from '@/components/TagChip';
import { CONVERSATIONS_KEY } from '@/hooks/useConversations';
import { useAuthStore } from '@/stores/auth';
import { useTheme } from '@/theme';
import type { Conversation, Department, FunnelStage, Note, Tag } from '@/types';
import { formatFullDate } from '@/utils/format';
import { formatPhone } from '@/utils/phone';

type SheetView = 'main' | 'stage' | 'department' | 'tags' | 'notes';

interface ConversationDetailsSheetProps {
  conversation: Conversation;
  visible: boolean;
  onClose: () => void;
}

interface ActionRowProps {
  icon: keyof typeof Ionicons.glyphMap;
  label: string;
  onPress: () => void;
  destructive?: boolean;
  loading?: boolean;
}

function ActionRow({ icon, label, onPress, destructive, loading }: ActionRowProps) {
  const { colors, typography } = useTheme();
  const color = destructive ? colors.danger : colors.textPrimary;
  return (
    <Pressable
      onPress={onPress}
      disabled={loading}
      style={({ pressed }) => [
        styles.actionRow,
        { backgroundColor: pressed ? colors.surfaceAlt : 'transparent' },
      ]}
    >
      <Ionicons name={icon} size={20} color={destructive ? colors.danger : colors.textSecondary} />
      <Text style={[typography.body, styles.actionLabel, { color }]}>{label}</Text>
      {loading ? (
        <ActivityIndicator size="small" color={colors.textSecondary} />
      ) : (
        <Ionicons name="chevron-forward" size={16} color={colors.textSecondary} />
      )}
    </Pressable>
  );
}

export function ConversationDetailsSheet({
  conversation,
  visible,
  onClose,
}: ConversationDetailsSheetProps) {
  const { colors, typography } = useTheme();
  const queryClient = useQueryClient();
  const me = useAuthStore((s) => s.user);

  const [view, setView] = useState<SheetView>('main');
  const [error, setError] = useState<string | null>(null);
  const [noteText, setNoteText] = useState('');
  const [notePrivate, setNotePrivate] = useState(true);

  const invalidate = () => {
    void queryClient.invalidateQueries({ queryKey: ['conversation', conversation.id] });
    void queryClient.invalidateQueries({ queryKey: [CONVERSATIONS_KEY] });
  };

  const runAction = useMutation({
    mutationFn: (action: () => Promise<void>) => action(),
    onSuccess: () => {
      setError(null);
      invalidate();
      setView('main');
    },
    onError: (err) => setError(getErrorMessage(err)),
  });

  const stages = useQuery({
    queryKey: ['funnel-stages', conversation.funnel_id],
    queryFn: () => getFunnelStages(conversation.funnel_id as number),
    enabled: visible && view === 'stage' && conversation.funnel_id != null,
  });

  const departments = useQuery({
    queryKey: ['departments'],
    queryFn: getDepartments,
    enabled: visible && view === 'department',
    staleTime: 5 * 60 * 1000,
  });

  const allTags = useQuery({
    queryKey: ['tags'],
    queryFn: getTags,
    enabled: visible && view === 'tags',
    staleTime: 5 * 60 * 1000,
  });

  const notes = useQuery({
    queryKey: ['notes', conversation.id],
    queryFn: () => listNotes(conversation.id),
    enabled: visible && view === 'notes',
  });

  const addNoteMutation = useMutation({
    mutationFn: () =>
      addNote(conversation.id, { content: noteText.trim(), is_private: notePrivate }),
    onSuccess: () => {
      setNoteText('');
      void queryClient.invalidateQueries({ queryKey: ['notes', conversation.id] });
    },
    onError: (err) => setError(getErrorMessage(err)),
  });

  const toggleTag = useMutation({
    mutationFn: (params: { tagId: number; active: boolean }) =>
      params.active
        ? removeConversationTag(conversation.id, params.tagId)
        : addConversationTag(conversation.id, params.tagId),
    onSuccess: invalidate,
    onError: (err) => setError(getErrorMessage(err)),
  });

  const close = () => {
    setView('main');
    setError(null);
    onClose();
  };

  const isMine = me != null && conversation.agent_id === me.id;
  const activeTagIds = new Set(conversation.tags.map((t) => t.id));

  const renderMain = () => (
    <>
      <View style={styles.headerBlock}>
        <Avatar name={conversation.contact.name} uri={conversation.contact.avatar} size={56} />
        <View style={styles.headerInfo}>
          <Text style={[typography.title, { color: colors.textPrimary }]} numberOfLines={1}>
            {conversation.contact.name}
          </Text>
          {conversation.contact.phone ? (
            <Text style={[typography.caption, { color: colors.textSecondary }]}>
              {formatPhone(conversation.contact.phone)}
            </Text>
          ) : null}
          <View style={styles.headerMeta}>
            <StatusBadge status={conversation.status} />
            <Text style={[typography.caption, { color: colors.textSecondary }]}>
              {CHANNEL_LABELS[conversation.channel]}
            </Text>
          </View>
        </View>
      </View>

      {conversation.agent_name ? (
        <Text style={[typography.caption, styles.assignedTo, { color: colors.textSecondary }]}>
          Atribuída a {conversation.agent_name}
        </Text>
      ) : (
        <Text style={[typography.caption, styles.assignedTo, { color: colors.warning }]}>
          Não atribuída
        </Text>
      )}

      {conversation.tags.length > 0 ? (
        <View style={styles.tagsRow}>
          {conversation.tags.map((tag) => (
            <TagChip key={tag.id} tag={tag} />
          ))}
        </View>
      ) : null}

      <View style={[styles.divider, { backgroundColor: colors.border }]} />

      {!isMine && me ? (
        <ActionRow
          icon="person-add-outline"
          label="Atribuir a mim"
          loading={runAction.isPending}
          onPress={() => runAction.mutate(() => assignConversation(conversation.id, me.id))}
        />
      ) : null}

      {conversation.status === 'closed' ? (
        <ActionRow
          icon="refresh-outline"
          label="Reabrir conversa"
          loading={runAction.isPending}
          onPress={() => runAction.mutate(() => reopenConversation(conversation.id))}
        />
      ) : (
        <ActionRow
          icon="checkmark-circle-outline"
          label="Resolver conversa"
          loading={runAction.isPending}
          onPress={() => runAction.mutate(() => closeConversation(conversation.id))}
        />
      )}

      {conversation.funnel_id != null ? (
        <ActionRow icon="trending-up" label="Mover etapa do funil" onPress={() => setView('stage')} />
      ) : null}

      <ActionRow icon="business-outline" label="Trocar setor" onPress={() => setView('department')} />
      <ActionRow icon="pricetags-outline" label="Tags" onPress={() => setView('tags')} />
      <ActionRow icon="document-text-outline" label="Notas" onPress={() => setView('notes')} />
    </>
  );

  const renderStage = () => (
    <>
      {stages.isLoading ? <ActivityIndicator color={colors.primary} style={styles.loader} /> : null}
      {(stages.data ?? []).map((stage: FunnelStage) => (
        <ActionRow
          key={stage.id}
          icon={
            stage.id === conversation.funnel_stage_id ? 'radio-button-on' : 'radio-button-off'
          }
          label={stage.name}
          loading={runAction.isPending}
          onPress={() => runAction.mutate(() => moveConversationStage(conversation.id, stage.id))}
        />
      ))}
    </>
  );

  const renderDepartment = () => (
    <>
      {departments.isLoading ? (
        <ActivityIndicator color={colors.primary} style={styles.loader} />
      ) : null}
      {(departments.data ?? []).map((department: Department) => (
        <ActionRow
          key={department.id}
          icon={department.id === conversation.department_id ? 'radio-button-on' : 'radio-button-off'}
          label={department.name}
          loading={runAction.isPending}
          onPress={() =>
            runAction.mutate(() => setConversationDepartment(conversation.id, department.id))
          }
        />
      ))}
    </>
  );

  const renderTags = () => (
    <>
      {allTags.isLoading ? <ActivityIndicator color={colors.primary} style={styles.loader} /> : null}
      {(allTags.data ?? []).map((tag: Tag) => {
        const active = activeTagIds.has(tag.id);
        return (
          <ActionRow
            key={tag.id}
            icon={active ? 'checkbox' : 'square-outline'}
            label={tag.name}
            loading={toggleTag.isPending}
            onPress={() => toggleTag.mutate({ tagId: tag.id, active })}
          />
        );
      })}
    </>
  );

  const renderNotes = () => (
    <>
      {notes.isLoading ? <ActivityIndicator color={colors.primary} style={styles.loader} /> : null}
      {(notes.data ?? []).length === 0 && !notes.isLoading ? (
        <Text style={[typography.caption, styles.loaderText, { color: colors.textSecondary }]}>
          Nenhuma nota nesta conversa.
        </Text>
      ) : null}
      {(notes.data ?? []).map((note: Note) => (
        <View key={note.id} style={[styles.noteCard, { backgroundColor: colors.noteBg }]}>
          <Text style={[typography.body, { color: colors.textPrimary }]}>{note.content}</Text>
          <Text style={[typography.caption, { color: colors.textSecondary }]}>
            {note.is_private ? '🔒 ' : ''}
            {note.author_name ? `${note.author_name} · ` : ''}
            {formatFullDate(note.created_at)}
          </Text>
        </View>
      ))}

      <View style={[styles.noteComposer, { borderColor: colors.border }]}>
        <TextInput
          style={[typography.body, styles.noteInput, { color: colors.textPrimary, backgroundColor: colors.surfaceAlt }]}
          placeholder="Nova nota…"
          placeholderTextColor={colors.textSecondary}
          value={noteText}
          onChangeText={setNoteText}
          multiline
        />
        <View style={styles.noteFooter}>
          <View style={styles.noteSwitchRow}>
            <Switch value={notePrivate} onValueChange={setNotePrivate} />
            <Text style={[typography.caption, { color: colors.textSecondary }]}>Privada</Text>
          </View>
          <Pressable
            onPress={() => addNoteMutation.mutate()}
            disabled={noteText.trim().length === 0 || addNoteMutation.isPending}
            style={[
              styles.noteButton,
              {
                backgroundColor: colors.primary,
                opacity: noteText.trim().length === 0 || addNoteMutation.isPending ? 0.5 : 1,
              },
            ]}
          >
            <Text style={[typography.caption, { color: colors.onPrimary, fontWeight: '700' }]}>
              Adicionar
            </Text>
          </Pressable>
        </View>
      </View>
    </>
  );

  const titles: Record<SheetView, string> = {
    main: 'Detalhes da conversa',
    stage: 'Mover etapa',
    department: 'Trocar setor',
    tags: 'Tags',
    notes: 'Notas',
  };

  return (
    <Modal visible={visible} transparent animationType="slide" onRequestClose={close}>
      <Pressable style={styles.backdrop} onPress={close} />
      <View style={[styles.sheet, { backgroundColor: colors.surface }]}>
        <View style={styles.sheetHeader}>
          {view !== 'main' ? (
            <Pressable onPress={() => setView('main')} hitSlop={8}>
              <Ionicons name="chevron-back" size={22} color={colors.textPrimary} />
            </Pressable>
          ) : (
            <View style={styles.headerSpacer} />
          )}
          <Text style={[typography.subtitle, { color: colors.textPrimary }]}>{titles[view]}</Text>
          <Pressable onPress={close} hitSlop={8}>
            <Ionicons name="close" size={22} color={colors.textPrimary} />
          </Pressable>
        </View>

        {error ? (
          <Text style={[typography.caption, styles.error, { color: colors.danger }]}>{error}</Text>
        ) : null}

        <ScrollView showsVerticalScrollIndicator={false} keyboardShouldPersistTaps="handled">
          {view === 'main' ? renderMain() : null}
          {view === 'stage' ? renderStage() : null}
          {view === 'department' ? renderDepartment() : null}
          {view === 'tags' ? renderTags() : null}
          {view === 'notes' ? renderNotes() : null}
        </ScrollView>
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
    maxHeight: '85%',
  },
  sheetHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 12,
  },
  headerSpacer: {
    width: 22,
  },
  headerBlock: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    marginBottom: 8,
  },
  headerInfo: {
    flex: 1,
    gap: 2,
  },
  headerMeta: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginTop: 2,
  },
  assignedTo: {
    marginBottom: 8,
  },
  tagsRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 6,
    marginBottom: 8,
  },
  divider: {
    height: StyleSheet.hairlineWidth,
    marginVertical: 8,
  },
  actionRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingVertical: 13,
    paddingHorizontal: 4,
    borderRadius: 8,
  },
  actionLabel: {
    flex: 1,
  },
  loader: {
    marginVertical: 16,
  },
  loaderText: {
    marginVertical: 16,
    textAlign: 'center',
  },
  error: {
    marginBottom: 8,
  },
  noteCard: {
    borderRadius: 10,
    padding: 10,
    marginBottom: 8,
    gap: 4,
  },
  noteComposer: {
    marginTop: 8,
    borderTopWidth: StyleSheet.hairlineWidth,
    paddingTop: 10,
    gap: 8,
  },
  noteInput: {
    borderRadius: 10,
    padding: 10,
    minHeight: 60,
    textAlignVertical: 'top',
  },
  noteFooter: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  noteSwitchRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  noteButton: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 8,
  },
});
