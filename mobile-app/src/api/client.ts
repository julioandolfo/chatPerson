import axios, {
  AxiosError,
  type AxiosInstance,
  type InternalAxiosRequestConfig,
} from 'axios';

import { API_URL } from '@/config';
import type { ApiEnvelope, RefreshData } from '@/types';

/** Erro normalizado da API (envelope { success: false, error }). */
export class ApiRequestError extends Error {
  readonly code?: string;
  readonly status?: number;

  constructor(message: string, code?: string, status?: number) {
    super(message);
    this.name = 'ApiRequestError';
    this.code = code;
    this.status = status;
  }
}

export function getErrorMessage(error: unknown, fallback = 'Algo deu errado. Tente novamente.'): string {
  if (error instanceof ApiRequestError) return error.message;
  if (error instanceof Error && error.message) return error.message;
  return fallback;
}

// ---------------------------------------------------------------------------
// Estado de tokens (mantido em memória; persistência é feita pela auth store)
// ---------------------------------------------------------------------------

let accessToken: string | null = null;
let refreshToken: string | null = null;
let onUnauthorized: (() => void) | null = null;
let onTokensRefreshed: ((accessToken: string) => void) | null = null;

export function setTokens(access: string | null, refresh: string | null): void {
  accessToken = access;
  refreshToken = refresh;
}

export function clearTokens(): void {
  accessToken = null;
  refreshToken = null;
}

export function getAccessToken(): string | null {
  return accessToken;
}

/** Registrado pela auth store: chamado quando o refresh falha (sessão expirada). */
export function setOnUnauthorized(handler: (() => void) | null): void {
  onUnauthorized = handler;
}

/** Registrado pela auth store: persiste o novo access token após refresh. */
export function setOnTokensRefreshed(handler: ((accessToken: string) => void) | null): void {
  onTokensRefreshed = handler;
}

// ---------------------------------------------------------------------------
// Refresh single-flight
// ---------------------------------------------------------------------------

let refreshPromise: Promise<string> | null = null;

async function doRefresh(): Promise<string> {
  if (!refreshToken) {
    throw new ApiRequestError('Sessão expirada.', 'NO_REFRESH_TOKEN', 401);
  }
  // Usa axios "cru" para não passar pelos interceptors deste client.
  const response = await axios.post<ApiEnvelope<RefreshData>>(
    `${API_URL}/auth/refresh`,
    { refresh_token: refreshToken },
    { timeout: 15000 },
  );
  const body = response.data;
  if (!body || body.success !== true) {
    throw new ApiRequestError('Não foi possível renovar a sessão.', 'REFRESH_FAILED', 401);
  }
  accessToken = body.data.access_token;
  onTokensRefreshed?.(body.data.access_token);
  return body.data.access_token;
}

function refreshAccessToken(): Promise<string> {
  if (!refreshPromise) {
    refreshPromise = doRefresh().finally(() => {
      refreshPromise = null;
    });
  }
  return refreshPromise;
}

// ---------------------------------------------------------------------------
// Instância axios com interceptors
// ---------------------------------------------------------------------------

type RetriableConfig = InternalAxiosRequestConfig & { _retry?: boolean };

export const client: AxiosInstance = axios.create({
  baseURL: API_URL,
  timeout: 30000,
  headers: { Accept: 'application/json' },
});

client.interceptors.request.use((config) => {
  if (accessToken) {
    config.headers.Authorization = `Bearer ${accessToken}`;
  }
  return config;
});

function isEnvelope(value: unknown): value is ApiEnvelope<unknown> {
  return typeof value === 'object' && value !== null && 'success' in value;
}

client.interceptors.response.use(
  (response) => {
    const body: unknown = response.data;
    if (isEnvelope(body)) {
      if (body.success) {
        response.data = body.data;
      } else {
        throw new ApiRequestError(
          body.error?.message ?? 'Erro desconhecido.',
          body.error?.code,
          response.status,
        );
      }
    }
    return response;
  },
  async (error: unknown) => {
    if (error instanceof ApiRequestError) throw error;
    if (!axios.isAxiosError(error)) throw error;

    const axiosError = error as AxiosError;
    const original = axiosError.config as RetriableConfig | undefined;
    const status = axiosError.response?.status;
    const url = original?.url ?? '';

    // 401 → tenta refresh (single-flight) e repete a request uma vez.
    if (
      status === 401 &&
      original &&
      !original._retry &&
      !url.includes('/auth/login') &&
      !url.includes('/auth/refresh')
    ) {
      original._retry = true;
      try {
        const newToken = await refreshAccessToken();
        original.headers.Authorization = `Bearer ${newToken}`;
        return client(original);
      } catch {
        onUnauthorized?.();
        throw new ApiRequestError('Sessão expirada. Faça login novamente.', 'UNAUTHORIZED', 401);
      }
    }

    const body: unknown = axiosError.response?.data;
    if (isEnvelope(body) && body.success === false) {
      throw new ApiRequestError(
        body.error?.message ?? axiosError.message,
        body.error?.code,
        status,
      );
    }
    if (axiosError.code === 'ECONNABORTED' || axiosError.message === 'Network Error') {
      throw new ApiRequestError('Sem conexão com o servidor.', 'NETWORK_ERROR', status);
    }
    throw new ApiRequestError(axiosError.message, axiosError.code, status);
  },
);
