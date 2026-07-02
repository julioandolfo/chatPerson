/**
 * Configuração global do app.
 * A URL base da API pode ser sobrescrita via variável de ambiente pública do Expo:
 *   EXPO_PUBLIC_API_URL=https://meu-backend.com npx expo start
 */
export const BASE_URL: string =
  process.env.EXPO_PUBLIC_API_URL ?? 'https://chat.personizi.com.br';

export const API_URL = `${BASE_URL}/api/v1`;

/** Intervalo do polling de tempo real (ms). */
export const POLL_INTERVAL_MS = 5000;

/** Tamanho de página padrão para listagens. */
export const CONVERSATIONS_PAGE_SIZE = 20;
export const MESSAGES_PAGE_SIZE = 50;

/** Duração mínima de uma gravação de áudio para envio (ms). */
export const MIN_RECORDING_MS = 800;
