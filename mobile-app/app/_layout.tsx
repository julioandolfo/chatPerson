import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import * as Notifications from 'expo-notifications';
import { Stack, useRouter, useSegments } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import React, { useEffect } from 'react';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';

import { usePushRegistration } from '@/hooks/usePushRegistration';
import { useRealtime } from '@/hooks/useRealtime';
import { useAuthStore } from '@/stores/auth';
import { useTheme } from '@/theme';

// Exibe notificações mesmo com o app em primeiro plano.
Notifications.setNotificationHandler({
  handleNotification: async () => ({
    shouldShowAlert: true,
    shouldPlaySound: true,
    shouldSetBadge: true,
  }),
});

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      staleTime: 30_000,
      refetchOnWindowFocus: false,
    },
  },
});

/** Efeitos que só rodam com sessão ativa (polling + push). */
function AuthenticatedEffects() {
  useRealtime();
  usePushRegistration();
  return null;
}

function Splash() {
  const { colors, typography } = useTheme();
  return (
    <View style={[styles.splash, { backgroundColor: colors.background }]}>
      <Text style={[typography.title, { color: colors.primary, fontSize: 28 }]}>Chat Privus</Text>
      <ActivityIndicator size="large" color={colors.primary} style={styles.splashSpinner} />
    </View>
  );
}

function RootNavigator() {
  const { colors, scheme } = useTheme();
  const status = useAuthStore((s) => s.status);
  const hydrate = useAuthStore((s) => s.hydrate);
  const router = useRouter();
  const segments = useSegments();

  useEffect(() => {
    void hydrate();
  }, [hydrate]);

  // Auth gate: redireciona conforme o estado da sessão.
  useEffect(() => {
    if (status === 'loading') return;
    const inLogin = segments[0] === 'login';
    if (status === 'unauthenticated') {
      queryClient.clear();
      if (!inLogin) router.replace('/login');
    } else if (status === 'authenticated' && inLogin) {
      router.replace('/(tabs)');
    }
  }, [status, segments, router]);

  if (status === 'loading') {
    return <Splash />;
  }

  return (
    <>
      <StatusBar style={scheme === 'dark' ? 'light' : 'dark'} />
      {status === 'authenticated' ? <AuthenticatedEffects /> : null}
      <Stack
        screenOptions={{
          headerShown: false,
          contentStyle: { backgroundColor: colors.background },
        }}
      >
        <Stack.Screen name="login" />
        <Stack.Screen name="(tabs)" />
        <Stack.Screen name="conversations/[id]" />
      </Stack>
    </>
  );
}

export default function RootLayout() {
  return (
    <QueryClientProvider client={queryClient}>
      <RootNavigator />
    </QueryClientProvider>
  );
}

const styles = StyleSheet.create({
  splash: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 16,
  },
  splashSpinner: {
    marginTop: 8,
  },
});
