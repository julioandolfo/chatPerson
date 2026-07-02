import React from 'react';
import { StyleSheet, View } from 'react-native';

import { useTheme } from '@/theme';
import type { SlaState } from '@/types';

interface SlaBarProps {
  state: SlaState;
}

/** Barra vertical colorida à esquerda da conversa indicando o estado do SLA. */
export function SlaBar({ state }: SlaBarProps) {
  const { colors } = useTheme();

  const color =
    state === 'ok'
      ? colors.success
      : state === 'warning'
        ? colors.warning
        : state === 'breached'
          ? colors.danger
          : 'transparent';

  return <View style={[styles.bar, { backgroundColor: color }]} />;
}

const styles = StyleSheet.create({
  bar: {
    width: 4,
    alignSelf: 'stretch',
    borderRadius: 2,
  },
});
