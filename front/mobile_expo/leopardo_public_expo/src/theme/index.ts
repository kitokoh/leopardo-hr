import { Platform } from 'react-native';

/**
 * Palette de l'application GRAND PUBLIC.
 *
 * Volontairement distincte de `leopardo_mobile_expo` (l'app RH) : le public
 * n'est pas un employe. Fond clair, accent chaud, cartes blanches — une
 * surface commerciale, pas un back-office.
 *
 * Les couleurs restent alignees sur la charte Leopardo (bleu #2F6BFF,
 * turquoise #00C2A8) pour que l'app soit reconnaissable comme Leopardo.
 */
export const colors = {
  background: '#F6F7FB',
  surface: '#FFFFFF',
  surfaceAlt: '#EEF1F8',
  border: '#E2E7F0',
  primary: '#2F6BFF',
  primaryDark: '#1E4FD1',
  primarySoft: '#E8EFFF',
  accent: '#00C2A8',
  accentSoft: '#E0FAF6',
  success: '#16A34A',
  warning: '#D97706',
  danger: '#DC2626',
  text: '#0F172A',
  textMuted: '#5B6B85',
  textFaint: '#8A99B0',
  white: '#FFFFFF',
  overlay: 'rgba(15, 23, 42, 0.45)',
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
  xl: 22,
  pill: 999,
} as const;

export const typography = {
  display: 30,
  title: 24,
  heading: 19,
  subheading: 16,
  body: 14,
  caption: 12,
} as const;

export const shadow = Platform.select({
  ios: {
    shadowColor: '#0F172A',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.08,
    shadowRadius: 12,
  },
  android: { elevation: 2 },
  default: {},
});

/** Couleur d'un statut metier, quelle que soit la verticale. */
export const statusColors: Record<string, string> = {
  pending: colors.warning,
  confirmed: colors.success,
  paid: colors.success,
  completed: colors.success,
  served: colors.success,
  delivered: colors.success,
  cancelled: colors.danger,
  canceled: colors.danger,
  rejected: colors.danger,
  failed: colors.danger,
  expired: colors.textFaint,
  draft: colors.textMuted,
};

export function statusColor(status: string | undefined | null): string {
  if (!status) return colors.textMuted;
  return statusColors[status.toLowerCase()] ?? colors.textMuted;
}
