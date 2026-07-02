import Constants from 'expo-constants';
import * as Device from 'expo-device';
import * as Notifications from 'expo-notifications';
import { useRouter } from 'expo-router';
import { useEffect } from 'react';
import { Platform } from 'react-native';

import { registerPushToken } from '@/api/devices';
import { useAuthStore } from '@/stores/auth';

function getProjectId(): string | undefined {
  const extra = Constants.expoConfig?.extra as
    | { eas?: { projectId?: string } }
    | undefined;
  const fromExtra = extra?.eas?.projectId;
  if (fromExtra && !fromExtra.startsWith('REPLACE')) return fromExtra;
  return Constants.easConfig?.projectId ?? fromExtra;
}

/**
 * Registra o dispositivo para push (Expo Push Token → POST /devices) e trata
 * o tap em notificações navegando para a conversa correspondente.
 */
export function usePushRegistration(): void {
  const status = useAuthStore((s) => s.status);
  const router = useRouter();

  useEffect(() => {
    if (status !== 'authenticated') return;
    let mounted = true;

    (async () => {
      try {
        if (!Device.isDevice) return; // simulador não recebe push

        if (Platform.OS === 'android') {
          await Notifications.setNotificationChannelAsync('messages', {
            name: 'Mensagens',
            importance: Notifications.AndroidImportance.HIGH,
            sound: 'default',
            vibrationPattern: [0, 250, 250, 250],
            lockscreenVisibility: Notifications.AndroidNotificationVisibility.PRIVATE,
          });
        }

        const current = await Notifications.getPermissionsAsync();
        let granted = current.status === 'granted';
        if (!granted) {
          const requested = await Notifications.requestPermissionsAsync();
          granted = requested.status === 'granted';
        }
        if (!granted || !mounted) return;

        const projectId = getProjectId();
        const token = await Notifications.getExpoPushTokenAsync(
          projectId ? { projectId } : undefined,
        );
        if (!mounted) return;

        await registerPushToken(token.data);
      } catch (error) {
        console.warn('Falha ao registrar push token:', error);
      }
    })();

    const responseSubscription = Notifications.addNotificationResponseReceivedListener(
      (response) => {
        const data = response.notification.request.content.data as
          | { conversation_id?: number | string }
          | undefined;
        const conversationId = data?.conversation_id;
        if (conversationId != null) {
          router.push(`/conversations/${conversationId}`);
        }
      },
    );

    return () => {
      mounted = false;
      responseSubscription.remove();
    };
  }, [status, router]);
}
