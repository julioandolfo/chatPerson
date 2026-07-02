import { useQuery } from '@tanstack/react-query';
import React, { useEffect, useState } from 'react';
import { Modal, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';

import { getDepartments, getFunnels } from '@/api/misc';
import { CHANNEL_LABELS } from '@/components/ChannelIcon';
import { STATUS_LABELS } from '@/components/StatusBadge';
import { EMPTY_ADVANCED_FILTERS, useUiStore, type AdvancedFilters } from '@/stores/ui';
import { useTheme } from '@/theme';
import type { Channel, ConversationStatus } from '@/types';

interface FilterSheetProps {
  visible: boolean;
  onClose: () => void;
}

const STATUS_OPTIONS: ConversationStatus[] = ['open', 'pending', 'closed'];
const CHANNEL_OPTIONS: Channel[] = ['whatsapp', 'instagram', 'email', 'chat'];

interface OptionChipProps {
  label: string;
  selected: boolean;
  onPress: () => void;
}

function OptionChip({ label, selected, onPress }: OptionChipProps) {
  const { colors, typography } = useTheme();
  return (
    <Pressable
      onPress={onPress}
      style={[
        styles.chip,
        {
          backgroundColor: selected ? colors.primary : colors.surfaceAlt,
          borderColor: selected ? colors.primary : colors.border,
        },
      ]}
    >
      <Text style={[typography.caption, { color: selected ? colors.onPrimary : colors.textPrimary }]}>
        {label}
      </Text>
    </Pressable>
  );
}

/** Bottom sheet de filtros avançados da lista de conversas. */
export function FilterSheet({ visible, onClose }: FilterSheetProps) {
  const { colors, typography } = useTheme();
  const advanced = useUiStore((s) => s.advanced);
  const setAdvancedFilters = useUiStore((s) => s.setAdvancedFilters);

  const [draft, setDraft] = useState<AdvancedFilters>(advanced);

  useEffect(() => {
    if (visible) setDraft(advanced);
  }, [visible, advanced]);

  const departments = useQuery({
    queryKey: ['departments'],
    queryFn: getDepartments,
    enabled: visible,
    staleTime: 5 * 60 * 1000,
  });

  const funnels = useQuery({
    queryKey: ['funnels'],
    queryFn: getFunnels,
    enabled: visible,
    staleTime: 5 * 60 * 1000,
  });

  const apply = () => {
    setAdvancedFilters(draft);
    onClose();
  };

  const clear = () => {
    setDraft(EMPTY_ADVANCED_FILTERS);
    setAdvancedFilters(EMPTY_ADVANCED_FILTERS);
    onClose();
  };

  return (
    <Modal visible={visible} transparent animationType="slide" onRequestClose={onClose}>
      <Pressable style={styles.backdrop} onPress={onClose} />
      <View style={[styles.sheet, { backgroundColor: colors.surface }]}>
        <View style={[styles.handle, { backgroundColor: colors.border }]} />
        <Text style={[typography.title, { color: colors.textPrimary }]}>Filtros</Text>

        <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={styles.scrollContent}>
          <Text style={[typography.subtitle, styles.sectionTitle, { color: colors.textPrimary }]}>
            Status
          </Text>
          <View style={styles.chipRow}>
            <OptionChip
              label="Todos"
              selected={draft.status === null}
              onPress={() => setDraft((d) => ({ ...d, status: null }))}
            />
            {STATUS_OPTIONS.map((status) => (
              <OptionChip
                key={status}
                label={STATUS_LABELS[status]}
                selected={draft.status === status}
                onPress={() => setDraft((d) => ({ ...d, status }))}
              />
            ))}
          </View>

          <Text style={[typography.subtitle, styles.sectionTitle, { color: colors.textPrimary }]}>
            Canal
          </Text>
          <View style={styles.chipRow}>
            <OptionChip
              label="Todos"
              selected={draft.channel === null}
              onPress={() => setDraft((d) => ({ ...d, channel: null }))}
            />
            {CHANNEL_OPTIONS.map((channel) => (
              <OptionChip
                key={channel}
                label={CHANNEL_LABELS[channel]}
                selected={draft.channel === channel}
                onPress={() => setDraft((d) => ({ ...d, channel }))}
              />
            ))}
          </View>

          <Text style={[typography.subtitle, styles.sectionTitle, { color: colors.textPrimary }]}>
            Setor
          </Text>
          <View style={styles.chipRow}>
            <OptionChip
              label="Todos"
              selected={draft.department_id === null}
              onPress={() => setDraft((d) => ({ ...d, department_id: null }))}
            />
            {(departments.data ?? []).map((department) => (
              <OptionChip
                key={department.id}
                label={department.name}
                selected={draft.department_id === department.id}
                onPress={() => setDraft((d) => ({ ...d, department_id: department.id }))}
              />
            ))}
          </View>

          <Text style={[typography.subtitle, styles.sectionTitle, { color: colors.textPrimary }]}>
            Funil
          </Text>
          <View style={styles.chipRow}>
            <OptionChip
              label="Todos"
              selected={draft.funnel_id === null}
              onPress={() => setDraft((d) => ({ ...d, funnel_id: null }))}
            />
            {(funnels.data ?? []).map((funnel) => (
              <OptionChip
                key={funnel.id}
                label={funnel.name}
                selected={draft.funnel_id === funnel.id}
                onPress={() => setDraft((d) => ({ ...d, funnel_id: funnel.id }))}
              />
            ))}
          </View>
        </ScrollView>

        <View style={styles.footer}>
          <Pressable
            onPress={clear}
            style={[styles.footerButton, { backgroundColor: colors.surfaceAlt }]}
          >
            <Text style={[typography.subtitle, { color: colors.textPrimary }]}>Limpar</Text>
          </Pressable>
          <Pressable
            onPress={apply}
            style={[styles.footerButton, { backgroundColor: colors.primary }]}
          >
            <Text style={[typography.subtitle, { color: colors.onPrimary }]}>Aplicar</Text>
          </Pressable>
        </View>
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
    padding: 20,
    paddingBottom: 32,
    maxHeight: '80%',
    gap: 8,
  },
  handle: {
    alignSelf: 'center',
    width: 40,
    height: 4,
    borderRadius: 2,
    marginBottom: 8,
  },
  scrollContent: {
    paddingBottom: 8,
  },
  sectionTitle: {
    marginTop: 16,
    marginBottom: 8,
  },
  chipRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  chip: {
    paddingHorizontal: 14,
    paddingVertical: 7,
    borderRadius: 16,
    borderWidth: 1,
  },
  footer: {
    flexDirection: 'row',
    gap: 12,
    marginTop: 12,
  },
  footerButton: {
    flex: 1,
    alignItems: 'center',
    paddingVertical: 12,
    borderRadius: 10,
  },
});
