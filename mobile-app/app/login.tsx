import React, { useState } from 'react';
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { getErrorMessage } from '@/api/client';
import { useAuthStore } from '@/stores/auth';
import { useTheme } from '@/theme';

export default function LoginScreen() {
  const { colors, typography } = useTheme();
  const insets = useSafeAreaInsets();
  const login = useAuthStore((s) => s.login);

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const canSubmit = email.trim().length > 0 && password.length > 0 && !submitting;

  const handleSubmit = async () => {
    if (!canSubmit) return;
    setSubmitting(true);
    setError(null);
    try {
      await login(email, password);
      // A navegação é feita pelo auth gate no root layout.
    } catch (err) {
      setError(getErrorMessage(err, 'Não foi possível entrar. Verifique suas credenciais.'));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <KeyboardAvoidingView
      style={[styles.container, { backgroundColor: colors.background, paddingTop: insets.top }]}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
    >
      <View style={styles.content}>
        <Text style={[typography.title, styles.brand, { color: colors.primary }]}>Chat Privus</Text>
        <Text style={[typography.body, styles.subtitle, { color: colors.textSecondary }]}>
          Atendimento multicanal na palma da mão
        </Text>

        <View style={styles.form}>
          <Text style={[typography.caption, { color: colors.textSecondary }]}>E-mail</Text>
          <TextInput
            style={[
              styles.input,
              typography.body,
              { backgroundColor: colors.surface, color: colors.textPrimary, borderColor: colors.border },
            ]}
            placeholder="voce@empresa.com.br"
            placeholderTextColor={colors.textSecondary}
            autoCapitalize="none"
            autoCorrect={false}
            keyboardType="email-address"
            value={email}
            onChangeText={setEmail}
            editable={!submitting}
          />

          <Text style={[typography.caption, { color: colors.textSecondary }]}>Senha</Text>
          <TextInput
            style={[
              styles.input,
              typography.body,
              { backgroundColor: colors.surface, color: colors.textPrimary, borderColor: colors.border },
            ]}
            placeholder="••••••••"
            placeholderTextColor={colors.textSecondary}
            secureTextEntry
            value={password}
            onChangeText={setPassword}
            editable={!submitting}
            onSubmitEditing={() => void handleSubmit()}
          />

          {error ? (
            <Text style={[typography.caption, styles.error, { color: colors.danger }]}>{error}</Text>
          ) : null}

          <Pressable
            onPress={() => void handleSubmit()}
            disabled={!canSubmit}
            style={({ pressed }) => [
              styles.button,
              {
                backgroundColor: pressed ? colors.primaryDark : colors.primary,
                opacity: canSubmit ? 1 : 0.6,
              },
            ]}
          >
            {submitting ? (
              <ActivityIndicator color={colors.onPrimary} />
            ) : (
              <Text style={[typography.subtitle, { color: colors.onPrimary }]}>Entrar</Text>
            )}
          </Pressable>
        </View>
      </View>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  content: {
    flex: 1,
    justifyContent: 'center',
    padding: 24,
  },
  brand: {
    fontSize: 32,
    textAlign: 'center',
  },
  subtitle: {
    textAlign: 'center',
    marginTop: 4,
    marginBottom: 32,
  },
  form: {
    gap: 8,
  },
  input: {
    borderRadius: 10,
    borderWidth: 1,
    paddingHorizontal: 14,
    paddingVertical: 12,
    marginBottom: 8,
  },
  error: {
    marginBottom: 4,
  },
  button: {
    alignItems: 'center',
    paddingVertical: 14,
    borderRadius: 10,
    marginTop: 8,
  },
});
