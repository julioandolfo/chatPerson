import type { Channel } from '@/types';

export interface ThemeColors {
  primary: string;
  primaryDark: string;
  background: string;
  surface: string;
  surfaceAlt: string;
  textPrimary: string;
  textSecondary: string;
  success: string;
  warning: string;
  danger: string;
  info: string;
  noteBg: string;
  border: string;
  onPrimary: string;
}

export const lightColors: ThemeColors = {
  primary: '#3B82F6',
  primaryDark: '#2563EB',
  background: '#F5F8FA',
  surface: '#FFFFFF',
  surfaceAlt: '#F1F5F9',
  textPrimary: '#181C32',
  textSecondary: '#7E8299',
  success: '#50CD89',
  warning: '#FFC700',
  danger: '#F1416C',
  info: '#7239EA',
  noteBg: '#FFF8DD',
  border: '#E4E6EF',
  onPrimary: '#FFFFFF',
};

export const darkColors: ThemeColors = {
  primary: '#4F9CF9',
  primaryDark: '#2563EB',
  background: '#0F1014',
  surface: '#1E1E2D',
  surfaceAlt: '#2B2B40',
  textPrimary: '#F5F8FA',
  textSecondary: '#9899AC',
  success: '#50CD89',
  warning: '#FFC700',
  danger: '#F1416C',
  info: '#7239EA',
  noteBg: '#3A3421',
  border: '#2B2B40',
  onPrimary: '#FFFFFF',
};

export const channelColors: Record<Channel, string> = {
  whatsapp: '#25D366',
  instagram: '#E4405F',
  email: '#3B82F6',
  chat: '#7239EA',
};
