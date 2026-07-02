import { Ionicons } from '@expo/vector-icons';
import Constants from 'expo-constants';
import React, { useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Pressable,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { Avatar } from '@/components/Avatar';
import { useAuthStore } from '@/stores/auth';
import { useSettingsStore, type ThemePreference } from '@/stores/settings';
import { useTheme } from '@/theme';

const THEME_OPTIONS: { value: ThemePreference; label: string; icon: keyof typeof Ionicons.glyphMap }[] = [
  { value: 'light', label: 'Claro', icon: 'sunny-outline' },
  { value: 'dark', label: 'Escuro', icon: 'moon-outline' },
  { value: 'system', label: 'Sistema', icon: 'phone-portrait-outline' },
];

export default function ProfileScreen() {
  const { colors, typography } = useTheme();
  const insets = useSafeAreaInsets();

  const user = useAuthStore((s) => s.user);
  const logout = useAuthStore((s) => s.logout);

  const theme = useSettingsStore((s) => s.theme);
  const setTheme = useSettingsStore((s) => s.setTheme);
  const soundEnabled = useSettingsStore((s) => s.soundEnabled);
  const setSoundEnabled = useSettingsStore((s) => s.setSoundEnabled);

  const [loggingOut, setLoggingOut] = useState(false);

  const confirmLogout = () => {
    Alert.alert('Sair', 'Deseja realmente sair da sua conta?', [
      { text: 'Cancelar', style: 'cancel' },
      {
        text: 'Sair',
        style: 'destructive',
        onPress: () => {
          setLoggingOut(true);
          void logout().finally(() => setLoggingOut(false));
        },
      },
    ]);
  };

  return (
    <ScrollView
      style={[styles.container, { backgroundColor: colors.background }]}
      contentContainerStyle={[styles.content, { paddingTop: insets.top + 10 }]}
    >
      <Text style={[typography.title, { color: colors.textPrimary }]}>Perfil</Text>

      <View style={[styles.card, { backgroundColor: colors.surface, borderColor: colors.border }]}>
        <Avatar name={user?.name} uri={user?.avatar} size={56} />
        <View style={styles.userInfo}>
          <Text style={[typography.subtitle, { color: colors.textPrimary }]} numberOfLines={1}>
            {user?.name ?? 'Agente'}
          </Text>
          <Text style={[typography.caption, { color: colors.textSecondary }]} numberOfLines={1}>
            {user?.email ?? ''}
          </Text>
          {user?.role ? (
            <Text style={[typography.badge, { color: colors.primary }]}>{user.role}</Text>
          ) : null}
        </View>
      </View>

      <Text style={[typography.subtitle, styles.sectionTitle, { color: colors.textPrimary }]}>
        Aparência
      </Text>
      <View style={styles.themeRow}>
        {THEME_OPTIONS.map((option) => {
          const active = theme === option.value;
          return (
            <Pressable
              key={option.value}
              onPress={() => setTheme(option.value)}
              style={[
                styles.themeOption,
                {
                  backgroundColor: active ? colors.primary : colors.surface,
                  borderColor: active ? colors.primary : colors.border,
                },
              ]}
            >
              <Ionicons
                name={option.icon}
                size={18}
                color={active ? colors.onPrimary : colors.textSecondary}
              />
              <Text
                style={[
                  typography.caption,
                  { color: active ? colors.onPrimary : colors.textPrimary, fontWeight: '600' },
                ]}
              >
                {option.label}
              </Text>
            </Pressable>
          );
        })}
      </View>

      <Text style={[typography.subtitle, styles.sectionTitle, { color: colors.textPrimary }]}>
        Notificações
      </Text>
      <View style={[styles.settingRow, { backgroundColor: colors.surface, borderColor: colors.border }]}>
        <Ionicons name="volume-high-outline" size={20} color={colors.textSecondary} />
        <Text style={[typography.body, styles.settingLabel, { color: colors.textPrimary }]}>
          Sons e vibração
        </Text>
        <Switch
          value={soundEnabled}
          onValueChange={setSoundEnabled}
          trackColor={{ true: colors.primary }}
        />
      </View>

      <Pressable
        onPress={confirmLogout}
        disabled={loggingOut}
        style={({ pressed }) => [
          styles.logoutButton,
          {
            backgroundColor: pressed ? `${colors.danger}22` : colors.surface,
            borderColor: colors.danger,
          },
        ]}
      >
        {loggingOut ? (
          <ActivityIndicator color={colors.danger} />
        ) : (
          <>
            <Ionicons name="log-out-outline" size={20} color={colors.danger} />
            <Text style={[typography.subtitle, { color: colors.danger }]}>Sair da conta</Text>
          </>
        )}
      </Pressable>

      <Text style={[typography.caption, styles.version, { color: colors.textSecondary }]}>
        Chat Privus v{Constants.expoConfig?.version ?? '1.0.0'}
      </Text>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  content: {
    padding: 16,
    gap: 12,
    paddingBottom: 40,
  },
  card: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    padding: 16,
    borderRadius: 14,
    borderWidth: 1,
  },
  userInfo: {
    flex: 1,
    gap: 2,
  },
  sectionTitle: {
    marginTop: 8,
  },
  themeRow: {
    flexDirection: 'row',
    gap: 8,
  },
  themeOption: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    paddingVertical: 12,
    borderRadius: 10,
    borderWidth: 1,
  },
  settingRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    padding: 14,
    borderRadius: 12,
    borderWidth: 1,
  },
  settingLabel: {
    flex: 1,
  },
  logoutButton: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingVertical: 14,
    borderRadius: 10,
    borderWidth: 1,
    marginTop: 16,
  },
  version: {
    textAlign: 'center',
    marginTop: 8,
  },
});
