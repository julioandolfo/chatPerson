import * as SecureStore from 'expo-secure-store';
import { create } from 'zustand';

import { getMe, login as apiLogin } from '@/api/auth';
import {
  clearTokens,
  setOnTokensRefreshed,
  setOnUnauthorized,
  setTokens,
} from '@/api/client';
import { unregisterCurrentDevice } from '@/api/devices';
import type { User } from '@/types';

const ACCESS_TOKEN_KEY = 'cp_access_token';
const REFRESH_TOKEN_KEY = 'cp_refresh_token';
const USER_KEY = 'cp_user';

export type AuthStatus = 'loading' | 'authenticated' | 'unauthenticated';

interface AuthState {
  status: AuthStatus;
  user: User | null;
  permissions: string[];
  /** Restaura a sessão salva no boot do app. */
  hydrate: () => Promise<void>;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  hasPermission: (permission: string) => boolean;
}

function parseUser(json: string | null): User | null {
  if (!json) return null;
  try {
    return JSON.parse(json) as User;
  } catch {
    return null;
  }
}

export const useAuthStore = create<AuthState>()((set, get) => {
  // O client avisa quando o refresh falha (sessão expirada) e quando um novo
  // access token é emitido (para persistirmos).
  setOnUnauthorized(() => {
    void get().logout();
  });
  setOnTokensRefreshed((accessToken) => {
    void SecureStore.setItemAsync(ACCESS_TOKEN_KEY, accessToken);
  });

  return {
    status: 'loading',
    user: null,
    permissions: [],

    hydrate: async () => {
      try {
        const [access, refresh, userJson] = await Promise.all([
          SecureStore.getItemAsync(ACCESS_TOKEN_KEY),
          SecureStore.getItemAsync(REFRESH_TOKEN_KEY),
          SecureStore.getItemAsync(USER_KEY),
        ]);

        if (!access || !refresh) {
          set({ status: 'unauthenticated', user: null, permissions: [] });
          return;
        }

        setTokens(access, refresh);
        set({ status: 'authenticated', user: parseUser(userJson), permissions: [] });

        // Atualiza usuário/permissões em background. Se o token estiver
        // inválido, o interceptor faz refresh; se o refresh falhar, o
        // onUnauthorized acima derruba a sessão.
        try {
          const me = await getMe();
          const { permissions, ...user } = me;
          set({ user, permissions });
          void SecureStore.setItemAsync(USER_KEY, JSON.stringify(user));
        } catch {
          // erro de rede: mantém dados em cache
        }
      } catch {
        set({ status: 'unauthenticated', user: null, permissions: [] });
      }
    },

    login: async (email, password) => {
      const data = await apiLogin(email.trim(), password);
      setTokens(data.access_token, data.refresh_token);
      await Promise.all([
        SecureStore.setItemAsync(ACCESS_TOKEN_KEY, data.access_token),
        SecureStore.setItemAsync(REFRESH_TOKEN_KEY, data.refresh_token),
        SecureStore.setItemAsync(USER_KEY, JSON.stringify(data.user)),
      ]);
      set({ status: 'authenticated', user: data.user, permissions: [] });

      try {
        const me = await getMe();
        const { permissions, ...user } = me;
        set({ user, permissions });
      } catch {
        // permissões chegam na próxima hidratação
      }
    },

    logout: async () => {
      await unregisterCurrentDevice();
      clearTokens();
      await Promise.all([
        SecureStore.deleteItemAsync(ACCESS_TOKEN_KEY),
        SecureStore.deleteItemAsync(REFRESH_TOKEN_KEY),
        SecureStore.deleteItemAsync(USER_KEY),
      ]);
      set({ status: 'unauthenticated', user: null, permissions: [] });
    },

    hasPermission: (permission) => get().permissions.includes(permission),
  };
});
