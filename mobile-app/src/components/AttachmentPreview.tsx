import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import React from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';

import { useTheme } from '@/theme';
import type { LocalAttachment } from '@/types';

interface AttachmentPreviewProps {
  attachments: LocalAttachment[];
  onRemove: (index: number) => void;
}

/** Miniaturas dos anexos selecionados no Composer, com botão de remover. */
export function AttachmentPreview({ attachments, onRemove }: AttachmentPreviewProps) {
  const { colors, typography } = useTheme();

  if (attachments.length === 0) return null;

  return (
    <ScrollView
      horizontal
      showsHorizontalScrollIndicator={false}
      contentContainerStyle={styles.content}
      style={styles.container}
    >
      {attachments.map((attachment, index) => {
        const isImage = attachment.type.startsWith('image/');
        return (
          <View
            key={`${attachment.uri}-${index}`}
            style={[styles.item, { backgroundColor: colors.surfaceAlt, borderColor: colors.border }]}
          >
            {isImage ? (
              <Image source={{ uri: attachment.uri }} style={styles.thumb} contentFit="cover" />
            ) : (
              <View style={styles.fileThumb}>
                <Ionicons
                  name={attachment.type.startsWith('audio/') ? 'mic' : 'document-text-outline'}
                  size={22}
                  color={colors.textSecondary}
                />
                <Text
                  style={[typography.badge, { color: colors.textSecondary }]}
                  numberOfLines={2}
                >
                  {attachment.name}
                </Text>
              </View>
            )}
            <Pressable
              onPress={() => onRemove(index)}
              style={[styles.removeButton, { backgroundColor: colors.danger }]}
              hitSlop={8}
            >
              <Ionicons name="close" size={12} color="#FFFFFF" />
            </Pressable>
          </View>
        );
      })}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: {
    maxHeight: 84,
  },
  content: {
    gap: 8,
    paddingHorizontal: 12,
    paddingVertical: 6,
  },
  item: {
    width: 64,
    height: 64,
    borderRadius: 10,
    borderWidth: StyleSheet.hairlineWidth,
    overflow: 'visible',
  },
  thumb: {
    width: '100%',
    height: '100%',
    borderRadius: 10,
  },
  fileThumb: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 4,
    gap: 2,
  },
  removeButton: {
    position: 'absolute',
    top: -6,
    right: -6,
    width: 18,
    height: 18,
    borderRadius: 9,
    alignItems: 'center',
    justifyContent: 'center',
  },
});
