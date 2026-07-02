import { Image } from 'expo-image';
import React from 'react';
import { StyleSheet, Text, View } from 'react-native';

import { useTheme } from '@/theme';
import { initials } from '@/utils/format';

interface AvatarProps {
  name: string | null | undefined;
  uri?: string | null;
  size?: number;
}

const PALETTE = ['#3B82F6', '#50CD89', '#7239EA', '#F1416C', '#FFC700', '#0EA5E9'];

function colorFor(name: string): string {
  let hash = 0;
  for (let i = 0; i < name.length; i += 1) {
    hash = (hash * 31 + name.charCodeAt(i)) % 997;
  }
  return PALETTE[hash % PALETTE.length];
}

export function Avatar({ name, uri, size = 44 }: AvatarProps) {
  const { colors } = useTheme();
  const displayName = name ?? '?';
  const hasImage = Boolean(uri && /^https?:\/\//i.test(uri));

  const containerStyle = {
    width: size,
    height: size,
    borderRadius: size / 2,
    backgroundColor: hasImage ? colors.surfaceAlt : colorFor(displayName),
  };

  return (
    <View style={[styles.container, containerStyle]}>
      {hasImage ? (
        <Image
          source={{ uri: uri as string }}
          style={{ width: size, height: size, borderRadius: size / 2 }}
          contentFit="cover"
          transition={100}
        />
      ) : (
        <Text style={[styles.initials, { fontSize: size * 0.38 }]}>{initials(displayName)}</Text>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    alignItems: 'center',
    justifyContent: 'center',
    overflow: 'hidden',
  },
  initials: {
    color: '#FFFFFF',
    fontWeight: '700',
  },
});
