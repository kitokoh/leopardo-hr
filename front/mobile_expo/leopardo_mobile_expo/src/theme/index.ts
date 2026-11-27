import { Platform } from 'react-native';

/**
 * Palette Leopardo — reprise des couleurs du dashboard web
 * (front/web/src/lib/client-features.ts + tailwind.config.js).
 */
export const colors = {
  background: '#0B1220',
  surface: '#131C2E',
  surfaceAlt: '#1B2740',
  border: '#243352',
  primary: '#2F6BFF',
  primaryDark: '#1E4FD1',
  accent: '#00C2A8',
  success: '#22C55E',
  warning: '#F59E0B',
  danger: '#EF4444',
  text: '#F1F5F9',
  textMuted: '#94A3B8',
  textFaint: '#64748B',
  white: '#FFFFFF',
} as const;

export const spacing = {
  xs: 4,
  sm: 8,
  md: 12,
  lg: 16,
  xl: 24,
  xxl: 32,
} as const;

export const radius = {
  sm: 8,
  md: 12,
  lg: 16,
  xl: 24,
  pill: 999,
} as const;

export const typography = {
  title: 26,
  heading: 20,
  subheading: 16,
  body: 14,
  caption: 12,
} as const;

export const shadow = Platform.select({
  ios: {
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.25,
    shadowRadius: 12,
  },
  android: { elevation: 4 },
  default: {},
});

export const statusColors: Record<string, string> = {
  pending: colors.warning,
  approved: colors.success,
  rejected: colors.danger,
  paid: colors.success,
  present: colors.success,
  late: colors.warning,
  absent: colors.danger,
  incomplete: colors.textMuted,
};

export function statusColor(status: string | undefined | null): string {
  if (!status) return colors.textMuted;
  return statusColors[status] ?? colors.textMuted;
}
