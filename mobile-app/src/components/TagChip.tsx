import React from 'react';
import { StyleSheet, Text, View } from 'react-native';

import { useTheme } from '@/theme';
import type { Tag } from '@/types';

interface TagChipProps {
  tag: Tag;
}

export function TagChip({ tag }: TagChipProps) {
  const { typography } = useTheme();
  const color = tag.color || '#7E8299';

  return (
    <View style={[styles.chip, { backgroundColor: `${color}22`, borderColor: `${color}55` }]}>
      <Text style={[typography.badge, { color }]} numberOfLines={1}>
        {tag.name}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  chip: {
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 10,
    borderWidth: StyleSheet.hairlineWidth,
    maxWidth: 120,
  },
});
