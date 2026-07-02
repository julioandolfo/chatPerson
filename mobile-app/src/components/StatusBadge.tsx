import React from 'react';
import { StyleSheet, Text, View } from 'react-native';

import { useTheme } from '@/theme';
import type { ConversationStatus } from '@/types';

interface StatusBadgeProps {
  status: ConversationStatus;
}

export const STATUS_LABELS: Record<ConversationStatus, string> = {
  open: 'Aberta',
  pending: 'Pendente',
  closed: 'Resolvida',
};

export function StatusBadge({ status }: StatusBadgeProps) {
  const { colors, typography } = useTheme();

  const color =
    status === 'open' ? colors.success : status === 'pending' ? colors.warning : colors.textSecondary;

  return (
    <View style={[styles.badge, { backgroundColor: `${color}22` }]}>
      <Text style={[typography.badge, { color }]}>{STATUS_LABELS[status] ?? status}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  badge: {
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 8,
    alignSelf: 'flex-start',
  },
});
