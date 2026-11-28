import React from 'react';
import { Pressable, StyleProp, StyleSheet, Text, View, ViewStyle } from 'react-native';
import { colors, radius, spacing, typography } from '../theme';

type CardProps = {
  children: React.ReactNode;
  style?: StyleProp<ViewStyle>;
  onPress?: () => void;
  accessibilityLabel?: string;
};

/** Carte de contenu. Pressable quand elle ouvre une fiche. */
export function Card({ children, style, onPress, accessibilityLabel }: CardProps) {
  if (onPress) {
    return (
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={accessibilityLabel}
        onPress={onPress}
        style={({ pressed }) => [styles.card, pressed && styles.pressed, style]}
      >
        {children}
      </Pressable>
    );
  }
  return <View style={[styles.card, style]}>{children}</View>;
}

type BadgeProps = { label: string; color?: string; soft?: boolean };

export function Badge({ label, color, soft = true }: BadgeProps) {
  const tint = color ?? colors.textMuted;
  return (
    <View
      style={[
        styles.badge,
        soft
          ? { backgroundColor: `${tint}18`, borderColor: `${tint}33` }
          : { backgroundColor: tint, borderColor: tint },
      ]}
    >
      <Text style={[styles.badgeText, { color: soft ? tint : colors.white }]}>{label}</Text>
    </View>
  );
}

type SectionProps = { title: string; action?: React.ReactNode; children: React.ReactNode };

export function Section({ title, action, children }: SectionProps) {
  return (
    <View style={styles.section}>
      <View style={styles.sectionHead}>
        <Text style={styles.sectionTitle}>{title}</Text>
        {action}
      </View>
      {children}
    </View>
  );
}

type RowProps = { label: string; value: string; strong?: boolean };

export function InfoRow({ label, value, strong }: RowProps) {
  return (
    <View style={styles.row}>
      <Text style={styles.rowLabel}>{label}</Text>
      <Text style={[styles.rowValue, strong && styles.rowValueStrong]} numberOfLines={2}>
        {value}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: colors.surface,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.border,
    padding: spacing.lg,
  },
  pressed: { opacity: 0.9, transform: [{ scale: 0.995 }] },
  badge: {
    alignSelf: 'flex-start',
    borderRadius: radius.pill,
    borderWidth: 1,
    paddingHorizontal: spacing.md,
    paddingVertical: 3,
  },
  badgeText: { fontSize: typography.caption, fontWeight: '700' },
  section: { marginTop: spacing.xl },
  sectionHead: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: spacing.md,
  },
  sectionTitle: {
    color: colors.text,
    fontSize: typography.heading,
    fontWeight: '700',
    letterSpacing: -0.3,
  },
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    paddingVertical: spacing.sm,
    gap: spacing.lg,
  },
  rowLabel: { color: colors.textMuted, fontSize: typography.body, flexShrink: 0 },
  rowValue: {
    color: colors.text,
    fontSize: typography.body,
    fontWeight: '600',
    flex: 1,
    textAlign: 'right',
  },
  rowValueStrong: { fontSize: typography.subheading, fontWeight: '700' },
});
