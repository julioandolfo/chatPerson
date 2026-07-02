import { Ionicons } from '@expo/vector-icons';
import * as DocumentPicker from 'expo-document-picker';
import * as Haptics from 'expo-haptics';
import * as ImagePicker from 'expo-image-picker';
import React, { useCallback, useState } from 'react';
import { Alert, Pressable, StyleSheet, Text, TextInput, View } from 'react-native';

import { AttachmentPreview } from '@/components/AttachmentPreview';
import { MIN_RECORDING_MS } from '@/config';
import { useAudioRecorder } from '@/hooks/useAudioRecorder';
import { useTheme } from '@/theme';
import type { LocalAttachment, Message } from '@/types';
import { formatDuration } from '@/utils/format';

export interface ComposerSendPayload {
  content?: string;
  attachments?: LocalAttachment[];
}

interface ComposerProps {
  onSend: (payload: ComposerSendPayload) => void;
  isNote: boolean;
  onToggleNote: () => void;
  quoted: Message | null;
  onClearQuote: () => void;
  disabled?: boolean;
}

function imageAssetToAttachment(asset: ImagePicker.ImagePickerAsset): LocalAttachment {
  const extension = asset.uri.split('.').pop() ?? 'jpg';
  return {
    uri: asset.uri,
    name: asset.fileName ?? `midia-${Date.now()}.${extension}`,
    type: asset.mimeType ?? (asset.type === 'video' ? 'video/mp4' : 'image/jpeg'),
    size: asset.fileSize ?? undefined,
  };
}

export function Composer({
  onSend,
  isNote,
  onToggleNote,
  quoted,
  onClearQuote,
  disabled,
}: ComposerProps) {
  const { colors, typography } = useTheme();
  const [text, setText] = useState('');
  const [attachments, setAttachments] = useState<LocalAttachment[]>([]);
  const recorder = useAudioRecorder();

  const canSend = !disabled && (text.trim().length > 0 || attachments.length > 0);

  const handleSend = useCallback(() => {
    if (!canSend) return;
    onSend({
      content: text.trim() || undefined,
      attachments: attachments.length > 0 ? attachments : undefined,
    });
    setText('');
    setAttachments([]);
  }, [attachments, canSend, onSend, text]);

  const pickFromLibrary = useCallback(async () => {
    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ['images', 'videos'],
      allowsMultipleSelection: true,
      selectionLimit: 5,
      quality: 0.8,
    });
    if (!result.canceled) {
      setAttachments((prev) => [...prev, ...result.assets.map(imageAssetToAttachment)]);
    }
  }, []);

  const pickDocument = useCallback(async () => {
    const result = await DocumentPicker.getDocumentAsync({
      multiple: true,
      copyToCacheDirectory: true,
    });
    if (!result.canceled && result.assets) {
      setAttachments((prev) => [
        ...prev,
        ...result.assets.map((asset) => ({
          uri: asset.uri,
          name: asset.name,
          type: asset.mimeType ?? 'application/octet-stream',
          size: asset.size ?? undefined,
        })),
      ]);
    }
  }, []);

  const pickAttachment = useCallback(() => {
    Alert.alert('Anexar', 'O que você deseja enviar?', [
      { text: 'Foto ou vídeo', onPress: () => void pickFromLibrary() },
      { text: 'Documento', onPress: () => void pickDocument() },
      { text: 'Cancelar', style: 'cancel' },
    ]);
  }, [pickDocument, pickFromLibrary]);

  const openCamera = useCallback(async () => {
    const permission = await ImagePicker.requestCameraPermissionsAsync();
    if (!permission.granted) {
      Alert.alert('Câmera', 'Permita o acesso à câmera para enviar fotos.');
      return;
    }
    const result = await ImagePicker.launchCameraAsync({ quality: 0.8 });
    if (!result.canceled) {
      setAttachments((prev) => [...prev, ...result.assets.map(imageAssetToAttachment)]);
    }
  }, []);

  const startRecording = useCallback(async () => {
    const started = await recorder.start();
    if (started) {
      void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
    } else {
      Alert.alert('Microfone', 'Permita o acesso ao microfone para gravar áudios.');
    }
  }, [recorder]);

  const stopRecordingAndSend = useCallback(async () => {
    const result = await recorder.stop();
    if (!result) return;
    if (result.durationMs < MIN_RECORDING_MS) {
      return; // gravação muito curta: descarta
    }
    onSend({
      attachments: [
        {
          uri: result.uri,
          name: `audio-${Date.now()}.m4a`,
          type: 'audio/m4a',
        },
      ],
    });
  }, [onSend, recorder]);

  const removeAttachment = useCallback((index: number) => {
    setAttachments((prev) => prev.filter((_, i) => i !== index));
  }, []);

  const inputBackground = isNote ? colors.noteBg : colors.surfaceAlt;

  return (
    <View style={[styles.container, { backgroundColor: colors.surface, borderTopColor: colors.border }]}>
      {quoted ? (
        <View style={[styles.quoteBar, { backgroundColor: colors.surfaceAlt, borderLeftColor: colors.primary }]}>
          <View style={styles.quoteContent}>
            <Text style={[typography.badge, { color: colors.primary }]} numberOfLines={1}>
              Respondendo a {quoted.sender_name ?? 'mensagem'}
            </Text>
            <Text style={[typography.caption, { color: colors.textSecondary }]} numberOfLines={1}>
              {quoted.content ?? '📎 Anexo'}
            </Text>
          </View>
          <Pressable onPress={onClearQuote} hitSlop={8}>
            <Ionicons name="close-circle" size={20} color={colors.textSecondary} />
          </Pressable>
        </View>
      ) : null}

      <AttachmentPreview attachments={attachments} onRemove={removeAttachment} />

      {isNote ? (
        <Text style={[typography.badge, styles.noteHint, { color: colors.textSecondary }]}>
          🔒 Nota interna — visível apenas para a equipe
        </Text>
      ) : null}

      <View style={styles.row}>
        <Pressable onPress={pickAttachment} hitSlop={8} style={styles.iconButton} disabled={disabled}>
          <Ionicons name="attach" size={24} color={disabled ? colors.border : colors.textSecondary} />
        </Pressable>
        <Pressable onPress={() => void openCamera()} hitSlop={8} style={styles.iconButton} disabled={disabled}>
          <Ionicons name="camera-outline" size={24} color={disabled ? colors.border : colors.textSecondary} />
        </Pressable>
        <Pressable onPress={onToggleNote} hitSlop={8} style={styles.iconButton} disabled={disabled}>
          <Ionicons
            name={isNote ? 'lock-closed' : 'lock-open-outline'}
            size={22}
            color={isNote ? colors.warning : disabled ? colors.border : colors.textSecondary}
          />
        </Pressable>

        {recorder.isRecording ? (
          <View style={[styles.input, styles.recordingBox, { backgroundColor: inputBackground }]}>
            <View style={[styles.recordingDot, { backgroundColor: colors.danger }]} />
            <Text style={[typography.body, { color: colors.textPrimary }]}>
              Gravando… {formatDuration(recorder.durationMs)}
            </Text>
          </View>
        ) : (
          <TextInput
            style={[
              styles.input,
              typography.body,
              { backgroundColor: inputBackground, color: colors.textPrimary },
            ]}
            placeholder={isNote ? 'Escreva uma nota interna…' : 'Digite uma mensagem…'}
            placeholderTextColor={colors.textSecondary}
            value={text}
            onChangeText={setText}
            multiline
            editable={!disabled}
          />
        )}

        {canSend ? (
          <Pressable
            onPress={handleSend}
            style={[styles.sendButton, { backgroundColor: colors.primary }]}
            hitSlop={4}
          >
            <Ionicons name="send" size={18} color={colors.onPrimary} />
          </Pressable>
        ) : (
          <Pressable
            onPressIn={() => void startRecording()}
            onPressOut={() => void stopRecordingAndSend()}
            disabled={disabled}
            style={[
              styles.sendButton,
              { backgroundColor: recorder.isRecording ? colors.danger : colors.primary },
            ]}
            hitSlop={4}
          >
            <Ionicons name="mic" size={20} color={colors.onPrimary} />
          </Pressable>
        )}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    borderTopWidth: StyleSheet.hairlineWidth,
    paddingBottom: 6,
  },
  quoteBar: {
    flexDirection: 'row',
    alignItems: 'center',
    marginHorizontal: 12,
    marginTop: 8,
    padding: 8,
    borderRadius: 8,
    borderLeftWidth: 3,
    gap: 8,
  },
  quoteContent: {
    flex: 1,
  },
  noteHint: {
    paddingHorizontal: 16,
    paddingTop: 6,
  },
  row: {
    flexDirection: 'row',
    alignItems: 'flex-end',
    paddingHorizontal: 8,
    paddingTop: 6,
    gap: 2,
  },
  iconButton: {
    padding: 6,
    paddingBottom: 8,
  },
  input: {
    flex: 1,
    borderRadius: 20,
    paddingHorizontal: 14,
    paddingTop: 9,
    paddingBottom: 9,
    maxHeight: 120,
    marginHorizontal: 4,
  },
  recordingBox: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    minHeight: 40,
  },
  recordingDot: {
    width: 10,
    height: 10,
    borderRadius: 5,
  },
  sendButton: {
    width: 40,
    height: 40,
    borderRadius: 20,
    alignItems: 'center',
    justifyContent: 'center',
  },
});
