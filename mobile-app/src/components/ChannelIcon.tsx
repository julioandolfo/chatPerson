import { Ionicons } from '@expo/vector-icons';
import React from 'react';

import { channelColors } from '@/theme';
import type { Channel } from '@/types';

interface ChannelIconProps {
  channel: Channel;
  size?: number;
  color?: string;
}

const ICONS: Record<Channel, keyof typeof Ionicons.glyphMap> = {
  whatsapp: 'logo-whatsapp',
  instagram: 'logo-instagram',
  email: 'mail',
  chat: 'chatbubble-ellipses',
};

export const CHANNEL_LABELS: Record<Channel, string> = {
  whatsapp: 'WhatsApp',
  instagram: 'Instagram',
  email: 'E-mail',
  chat: 'Chat',
};

export function ChannelIcon({ channel, size = 14, color }: ChannelIconProps) {
  const iconName = ICONS[channel] ?? 'chatbubble-ellipses';
  return <Ionicons name={iconName} size={size} color={color ?? channelColors[channel] ?? '#7239EA'} />;
}
