import { differenceInCalendarDays, format, isToday, isYesterday } from 'date-fns';
import { ptBR } from 'date-fns/locale';

/** Horário curto de mensagem: 14:32 */
export function formatMessageTime(iso: string): string {
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return '';
  return format(date, 'HH:mm');
}

/** Data completa: 12 de março de 2026 às 14:32 */
export function formatFullDate(iso: string): string {
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return '';
  return format(date, "d 'de' MMMM 'às' HH:mm", { locale: ptBR });
}

/**
 * Timestamp compacto para a lista de conversas:
 * hoje → 14:32 · ontem → "Ontem" · última semana → "seg" · mais antigo → 12/03/26
 */
export function formatConversationTime(iso: string | null | undefined): string {
  if (!iso) return '';
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return '';
  if (isToday(date)) return format(date, 'HH:mm');
  if (isYesterday(date)) return 'Ontem';
  if (differenceInCalendarDays(new Date(), date) < 7) {
    return format(date, 'EEE', { locale: ptBR });
  }
  return format(date, 'dd/MM/yy');
}

/** Tamanho de arquivo legível: 1,4 MB */
export function formatFileSize(bytes: number | null | undefined): string {
  if (bytes == null || bytes <= 0) return '';
  const units = ['B', 'KB', 'MB', 'GB'];
  let value = bytes;
  let unit = 0;
  while (value >= 1024 && unit < units.length - 1) {
    value /= 1024;
    unit += 1;
  }
  const rounded = unit === 0 ? String(Math.round(value)) : value.toFixed(1).replace('.', ',');
  return `${rounded} ${units[unit]}`;
}

/** Duração de áudio/gravação: 0:42 ou 12:05 (entrada em ms ou s). */
export function formatDuration(value: number, unit: 'ms' | 's' = 'ms'): string {
  const totalSeconds = Math.max(0, Math.floor(unit === 'ms' ? value / 1000 : value));
  const minutes = Math.floor(totalSeconds / 60);
  const seconds = totalSeconds % 60;
  return `${minutes}:${String(seconds).padStart(2, '0')}`;
}

/** Iniciais para avatar: "João da Silva" → "JS" */
export function initials(name: string | null | undefined): string {
  if (!name) return '?';
  const parts = name.trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) return '?';
  const first = parts[0][0] ?? '';
  const last = parts.length > 1 ? parts[parts.length - 1][0] ?? '' : '';
  return (first + last).toUpperCase();
}
