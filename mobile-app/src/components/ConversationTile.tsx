import { Ionicons } from '@expo/vector-icons';
import React, { memo } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { Avatar } from '@/components/Avatar';
import { ChannelIcon } from '@/components/ChannelIcon';
import { SlaBar } from '@/components/SlaBar';
import { TagChip } from '@/components/TagChip';
import { useTheme } from '@/theme';
import type { Conversation } from '@/types';
import { formatConversationTime } from '@/utils/format';

interface ConversationTileProps {
  conversation: Conversation;
  onPress: (conversation: Conversation) => void;
}

function ConversationTileComponent({ conversation, onPress }: ConversationTileProps) {
  const { colors, typography } = useTheme();
  const hasUnread = conversation.unread_count > 0;

  return (
    <Pressable
      onPress={() => onPress(conversation)}
      style={({ pressed }) => [
        styles.container,
        {
          backgroundColor: pressed ? colors.surfaceAlt : colors.surface,
          borderBottomColor: colors.border,
        },
      ]}
    >
      <SlaBar state={conversation.sla_state} />

      <View style={styles.avatarWrapper}>
        <Avatar name={conversation.contact.name} uri={conversation.contact.avatar} size={46} />
        <View style={[styles.channelBadge, { backgroundColor: colors.surface }]}>
          <ChannelIcon channel={conversation.channel} size={13} />
        </View>
      </View>

      <View style={styles.middle}>
        <View style={styles.nameRow}>
          {conversation.pinned ? (
            <Ionicons name="pin" size={12} color={colors.textSecondary} style={styles.pin} />
          ) : null}
          <Text
            style={[
              typography.subtitle,
              { color: colors.textPrimary, fontWeight: hasUnread ? '700' : '600' },
              styles.name,
            ]}
            numberOfLines={1}
          >
            {conversation.contact.name}
          </Text>
        </View>

        <Text
          style={[
            typography.body,
            {
              color: hasUnread ? colors.textPrimary : colors.textSecondary,
              fontWeight: hasUnread ? '600' : '400',
            },
          ]}
          numberOfLines={1}
        >
          {conversation.last_message_preview ?? 'Sem mensagens'}
        </Text>

        {conversation.tags.length > 0 ? (
          <View style={styles.tagsRow}>
            {conversation.tags.slice(0, 2).map((tag) => (
              <TagChip key={tag.id} tag={tag} />
            ))}
            {conversation.tags.length > 2 ? (
              <Text style={[typography.badge, { color: colors.textSecondary }]}>
                +{conversation.tags.length - 2}
              </Text>
            ) : null}
          </View>
        ) : null}
      </View>

      <View style={styles.right}>
        <Text
          style={[
            typography.caption,
            { color: hasUnread ? colors.primary : colors.textSecondary },
          ]}
        >
          {formatConversationTime(conversation.last_message_at)}
        </Text>
        {hasUnread ? (
          <View style={[styles.unreadBadge, { backgroundColor: colors.primary }]}>
            <Text style={[typography.badge, { color: colors.onPrimary }]}>
              {conversation.unread_count > 99 ? '99+' : conversation.unread_count}
            </Text>
          </View>
        ) : null}
      </View>
    </Pressable>
  );
}

export const ConversationTile = memo(ConversationTileComponent);

const styles = StyleSheet.create({
  container: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingVertical: 10,
    paddingHorizontal: 12,
    borderBottomWidth: StyleSheet.hairlineWidth,
    minHeight: 76,
  },
  avatarWrapper: {
    position: 'relative',
  },
  channelBadge: {
    position: 'absolute',
    bottom: -2,
    right: -2,
    borderRadius: 9,
    padding: 2,
  },
  middle: {
    flex: 1,
    gap: 2,
  },
  nameRow: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  pin: {
    marginRight: 4,
  },
  name: {
    flex: 1,
  },
  tagsRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    marginTop: 2,
  },
  right: {
    alignItems: 'flex-end',
    gap: 6,
    minWidth: 48,
  },
  unreadBadge: {
    minWidth: 20,
    height: 20,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 5,
  },
});
