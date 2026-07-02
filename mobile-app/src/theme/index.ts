import { useColorScheme } from 'react-native';

import { useSettingsStore } from '@/stores/settings';
import { channelColors, darkColors, lightColors, type ThemeColors } from '@/theme/colors';
import { typography, type Typography } from '@/theme/typography';

export { channelColors, darkColors, lightColors, typography };
export type { ThemeColors, Typography };

export type ColorSchemeName = 'light' | 'dark';

export interface Theme {
  colors: ThemeColors;
  scheme: ColorSchemeName;
  typography: Typography;
  channelColors: typeof channelColors;
}

/**
 * Tema atual do app: segue o sistema por padrão, com override manual
 * persistido na store de configurações.
 */
export function useTheme(): Theme {
  const system = useColorScheme();
  const preference = useSettingsStore((s) => s.theme);

  const scheme: ColorSchemeName =
    preference === 'system' ? (system === 'dark' ? 'dark' : 'light') : preference;

  return {
    colors: scheme === 'dark' ? darkColors : lightColors,
    scheme,
    typography,
    channelColors,
  };
}
