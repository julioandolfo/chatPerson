import Constants from 'expo-constants';
import * as Device from 'expo-device';
import { Platform } from 'react-native';

import { client } from '@/api/client';
import type { RegisterDevicePayload } from '@/types';

/** Token push registrado nesta sessão (usado no logout). */
let registeredToken: string | null = null;

export async function registerDevice(payload: RegisterDevicePayload): Promise<void> {
  await client.post('/devices', payload);
}

export async function unregisterDevice(token: string): Promise<void> {
  await client.delete(`/devices/${encodeURIComponent(token)}`);
}

/** Registra o token Expo Push deste dispositivo no backend. */
export async function registerPushToken(token: string): Promise<void> {
  await registerDevice({
    token,
    platform: Platform.OS === 'ios' ? 'ios' : 'android',
    device_name: Device.deviceName ?? Device.modelName ?? 'Dispositivo',
    app_version: Constants.expoConfig?.version ?? '1.0.0',
  });
  registeredToken = token;
}

/** Remove o registro do dispositivo atual (chamado no logout). Best-effort. */
export async function unregisterCurrentDevice(): Promise<void> {
  if (!registeredToken) return;
  const token = registeredToken;
  registeredToken = null;
  try {
    await unregisterDevice(token);
  } catch {
    // best-effort: não bloqueia o logout
  }
}
