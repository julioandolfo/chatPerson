import {
  AudioModule,
  RecordingPresets,
  setAudioModeAsync,
  useAudioRecorder as useExpoAudioRecorder,
  useAudioRecorderState,
} from 'expo-audio';
import { useCallback, useState } from 'react';

export interface RecordingResult {
  uri: string;
  durationMs: number;
}

export interface AudioRecorderControls {
  isRecording: boolean;
  /** Duração da gravação em andamento (ms). */
  durationMs: number;
  /** Pede permissão e inicia a gravação. Retorna false se negada. */
  start: () => Promise<boolean>;
  /** Para a gravação e retorna o arquivo (.m4a) gravado. */
  stop: () => Promise<RecordingResult | null>;
  /** Para e descarta a gravação. */
  cancel: () => Promise<void>;
}

/**
 * Gravação de áudio (segurar para gravar) usando expo-audio.
 * O preset HIGH_QUALITY gera arquivos .m4a (AAC) em iOS e Android.
 */
export function useAudioRecorder(): AudioRecorderControls {
  const recorder = useExpoAudioRecorder(RecordingPresets.HIGH_QUALITY);
  const recorderState = useAudioRecorderState(recorder, 200);
  const [isRecording, setIsRecording] = useState(false);

  const start = useCallback(async (): Promise<boolean> => {
    try {
      const permission = await AudioModule.requestRecordingPermissionsAsync();
      if (!permission.granted) return false;
      await setAudioModeAsync({ allowsRecording: true, playsInSilentMode: true });
      await recorder.prepareToRecordAsync();
      recorder.record();
      setIsRecording(true);
      return true;
    } catch {
      setIsRecording(false);
      return false;
    }
  }, [recorder]);

  const finish = useCallback(async (): Promise<string | null> => {
    setIsRecording(false);
    try {
      await recorder.stop();
    } catch {
      return null;
    } finally {
      await setAudioModeAsync({ allowsRecording: false, playsInSilentMode: true }).catch(() => {});
    }
    return recorder.uri ?? null;
  }, [recorder]);

  const stop = useCallback(async (): Promise<RecordingResult | null> => {
    if (!isRecording) return null;
    const durationMs = recorderState.durationMillis ?? 0;
    const uri = await finish();
    if (!uri) return null;
    return { uri, durationMs };
  }, [finish, isRecording, recorderState.durationMillis]);

  const cancel = useCallback(async (): Promise<void> => {
    if (!isRecording) return;
    await finish();
  }, [finish, isRecording]);

  return {
    isRecording,
    durationMs: isRecording ? recorderState.durationMillis ?? 0 : 0,
    start,
    stop,
    cancel,
  };
}
