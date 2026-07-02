import type { TextStyle } from 'react-native';

export type TypographyVariant = 'title' | 'subtitle' | 'body' | 'caption' | 'badge';

export const typography: Record<TypographyVariant, TextStyle> = {
  title: { fontSize: 20, fontWeight: '600' },
  subtitle: { fontSize: 16, fontWeight: '600' },
  body: { fontSize: 15, fontWeight: '400' },
  caption: { fontSize: 12, fontWeight: '400' },
  badge: { fontSize: 11, fontWeight: '700' },
};

export type Typography = typeof typography;
