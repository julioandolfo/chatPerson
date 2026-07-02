import { Ionicons } from '@expo/vector-icons';
import { Image } from 'expo-image';
import { useAudioPlayer, useAudioPlayerStatus } from 'expo-audio';
import * as Haptics from 'expo-haptics';
import React, { memo } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';

import { useResolvedUrl } from '@/hooks/useResolvedUrl';
import { useTheme } from '@/theme';
import type { Attachment, Message, MessageStatus } from '@/types';
import { formatDuration, formatFileSize, formatMessageTime } from '@/utils/format';

// ---------------------------------------------------------------------------
// Status ticks
// ---------------------------------------------------------------------------

function StatusTicks({ status, tint }: { status: MessageStatus; tint: string }) {
  const readColor = '#8CE0FF';
  switch (status) {
    case 'pending':
      return <Ionicons name="time-outline" size={13} color={tint} />;
    case 'sent':
      return <Ionicons name="checkmark" size={13} color={tint} />;
    case 'delivered':
      return <Ionicons name="checkmark-done" size={13} color={tint} />;
    case 'read':
      return <Ionicons name="checkmark-done" size={13} color={readColor} />;
    case 'error':
      return <Ionicons name="alert-circle" size={13} color="#FFD1DC" />;
    default:
      return null;
  }
}

// ---------------------------------------------------------------------------
// Anexos
// ---------------------------------------------------------------------------

function ImageAttachment({
  attachment,
  onPress,
}: {
  attachment: Attachment;
  onPress: (url: string) => void;
}) {
  const { colors } = useTheme();
  const url = useResolvedUrl(attachment.url);

  if (!url) {
    return (
      <View style={[styles.imagePlaceholder, { backgroundColor: colors.surfaceAlt }]}>
        <ActivityIndicator color={colors.textSecondary} />
      </View>
    );
  }

  return (
    <Pressable onPress={() => onPress(url)}>
      <Image source={{ uri: url }} style={styles.image} contentFit="cover" transition={150} />
    </Pressable>
  );
}

function AudioPlayerInner({ uri, tint }: { uri: string; tint: string }) {
  const player = useAudioPlayer({ uri });
  const status = useAudioPlayerStatus(player);

  const toggle = () => {
    if (status.playing) {
      player.pause();
      return;
    }
    if (status.didJustFinish || (status.duration > 0 && status.currentTime >= status.duration)) {
      player.seekTo(0);
    }
    player.play();
  };

  const current = formatDuration(status.currentTime, 's');
  const total = status.duration > 0 ? formatDuration(status.duration, 's') : '--:--';

  return (
    <View style={styles.audioRow}>
      <Pressable onPress={toggle} hitSlop={8}>
        <Ionicons name={status.playing ? 'pause' : 'play'} size={24} color={tint} />
      </Pressable>
      <Text style={[styles.audioTime, { color: tint }]}>
        {current} / {total}
      </Text>
    </View>
  );
}

function AudioAttachment({ attachment, tint }: { attachment: Attachment; tint: string }) {
  const url = useResolvedUrl(attachment.url);
  if (!url) {
    return (
      <View style={styles.audioRow}>
        <ActivityIndicator size="small" color={tint} />
        <Text style={[styles.audioTime, { color: tint }]}>Carregando áudio…</Text>
      </View>
    );
  }
  return <AudioPlayerInner uri={url} tint={tint} />;
}

function DocumentAttachment({ attachment, tint }: { attachment: Attachment; tint: string }) {
  const isVideo = attachment.type.startsWith('video');
  const size = formatFileSize(attachment.size);
  return (
    <View style={styles.documentRow}>
      <Ionicons name={isVideo ? 'videocam' : 'document-text-outline'} size={22} color={tint} />
      <View style={styles.documentInfo}>
        <Text style={[styles.documentName, { color: tint }]} numberOfLines={1}>
          {attachment.name ?? (isVideo ? 'Vídeo' : 'Documento')}
        </Text>
        {size ? <Text style={[styles.documentSize, { color: tint }]}>{size}</Text> : null}
      </View>
    </View>
  );
}

function AttachmentContent({
  attachment,
  tint,
  onImagePress,
}: {
  attachment: Attachment;
  tint: string;
  onImagePress: (url: string) => void;
}) {
  if (attachment.type.startsWith('image')) {
    return <ImageAttachment attachment={attachment} onPress={onImagePress} />;
  }
  if (attachment.type.startsWith('audio')) {
    return <AudioAttachment attachment={attachment} tint={tint} />;
  }
  return <DocumentAttachment attachment={attachment} tint={tint} />;
}

// ---------------------------------------------------------------------------
// Bolha
// ---------------------------------------------------------------------------

export interface MessageBubbleProps {
  message: Message;
  onQuote: (message: Message) => void;
  onRetry: (localId: string) => void;
  onImagePress: (url: string) => void;
}

function MessageBubbleComponent({ message, onQuote, onRetry, onImagePress }: MessageBubbleProps) {
  const { colors, typography } = useTheme();

  // Mensagem de sistema: centralizada
  if (message.sender_type === 'system') {
    return (
      <View style={styles.systemContainer}>
        <View style={[styles.systemPill, { backgroundColor: colors.surfaceAlt }]}>
          <Text style={[typography.caption, { color: colors.textSecondary }]}>
            {message.content ?? ''}
          </Text>
        </View>
      </View>
    );
  }

  const isNote = message.is_note;
  const isAgentSide = message.sender_type === 'agent' || message.sender_type === 'ai_agent';
  const isAi = message.sender_type === 'ai_agent';
  const isError = message.status === 'error';

  const bubbleBackground = isNote
    ? colors.noteBg
    : isAi
      ? colors.surface
      : isAgentSide
        ? colors.primary
        : colors.surfaceAlt;

  const textColor = isNote
    ? colors.textPrimary
    : isAgentSide && !isAi
      ? colors.onPrimary
      : colors.textPrimary;

  const metaColor = isNote
    ? colors.textSecondary
    : isAgentSide && !isAi
      ? 'rgba(255,255,255,0.75)'
      : colors.textSecondary;

  const handleLongPress = () => {
    if (isNote) return;
    void Haptics.selectionAsync();
    onQuote(message);
  };

  return (
    <View
      style={[
        styles.row,
        isNote ? styles.rowNote : isAgentSide ? styles.rowRight : styles.rowLeft,
      ]}
    >
      <Pressable
        onLongPress={handleLongPress}
        delayLongPress={300}
        style={[
          styles.bubble,
          { backgroundColor: bubbleBackground },
          isNote && styles.noteBubble,
          isAi && { borderWidth: 1, borderColor: colors.info },
        ]}
      >
        {isNote ? (
          <Text style={[typography.badge, { color: colors.textSecondary }]}>
            🔒 Nota interna{message.sender_name ? ` · ${message.sender_name}` : ''}
          </Text>
        ) : null}

        {isAi ? (
          <Text style={[typography.badge, { color: colors.info }]}>
            🤖 {message.sender_name ?? 'Agente IA'}
          </Text>
        ) : null}

        {message.sender_type === 'contact' && message.sender_name ? (
          <Text style={[typography.badge, { color: colors.primary }]}>{message.sender_name}</Text>
        ) : null}

        {message.quoted_message_id != null || message.quoted_text ? (
          <View style={[styles.quote, { borderLeftColor: isAgentSide && !isAi ? colors.onPrimary : colors.primary }]}>
            {message.quoted_sender_name ? (
              <Text style={[typography.badge, { color: metaColor }]} numberOfLines={1}>
                {message.quoted_sender_name}
              </Text>
            ) : null}
            <Text style={[typography.caption, { color: metaColor }]} numberOfLines={2}>
              {message.quoted_text ?? 'Mensagem'}
            </Text>
          </View>
        ) : null}

        {message.attachments.map((attachment, index) => (
          <AttachmentContent
            key={`${attachment.url}-${index}`}
            attachment={attachment}
            tint={textColor}
            onImagePress={onImagePress}
          />
        ))}

        {message.content ? (
          <Text style={[typography.body, { color: textColor }]}>{message.content}</Text>
        ) : null}

        <View style={styles.footer}>
          <Text style={[typography.caption, styles.time, { color: metaColor }]}>
            {formatMessageTime(message.created_at)}
          </Text>
          {isAgentSide && !isNote ? (
            <StatusTicks status={message.status} tint={metaColor} />
          ) : null}
        </View>
      </Pressable>

      {isError && message.local_id ? (
        <Pressable
          onPress={() => onRetry(message.local_id as string)}
          style={styles.retryButton}
          hitSlop={8}
        >
          <Ionicons name="refresh" size={13} color={colors.danger} />
          <Text style={[typography.caption, { color: colors.danger }]}>
            Falha ao enviar. Toque para reenviar
          </Text>
        </Pressable>
      ) : null}
    </View>
  );
}

export const MessageBubble = memo(MessageBubbleComponent);

const styles = StyleSheet.create({
  row: {
    marginVertical: 3,
    paddingHorizontal: 12,
  },
  rowLeft: {
    alignItems: 'flex-start',
  },
  rowRight: {
    alignItems: 'flex-end',
  },
  rowNote: {
    alignItems: 'stretch',
  },
  bubble: {
    maxWidth: '82%',
    borderRadius: 14,
    paddingHorizontal: 12,
    paddingVertical: 8,
    gap: 4,
  },
  noteBubble: {
    maxWidth: '100%',
    alignSelf: 'stretch',
  },
  quote: {
    borderLeftWidth: 3,
    paddingLeft: 8,
    paddingVertical: 2,
    opacity: 0.9,
  },
  footer: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'flex-end',
    gap: 4,
    marginTop: 2,
  },
  time: {
    fontSize: 11,
  },
  systemContainer: {
    alignItems: 'center',
    marginVertical: 6,
    paddingHorizontal: 12,
  },
  systemPill: {
    paddingHorizontal: 12,
    paddingVertical: 4,
    borderRadius: 12,
    maxWidth: '90%',
  },
  image: {
    width: 220,
    height: 220,
    borderRadius: 10,
    marginVertical: 2,
  },
  imagePlaceholder: {
    width: 220,
    height: 220,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
    marginVertical: 2,
  },
  audioRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingVertical: 6,
    minWidth: 160,
  },
  audioTime: {
    fontSize: 13,
    fontVariant: ['tabular-nums'],
  },
  documentRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingVertical: 6,
    maxWidth: 240,
  },
  documentInfo: {
    flexShrink: 1,
  },
  documentName: {
    fontSize: 14,
    fontWeight: '600',
  },
  documentSize: {
    fontSize: 11,
    opacity: 0.8,
  },
  retryButton: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    marginTop: 4,
  },
});
