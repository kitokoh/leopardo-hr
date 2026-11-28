import React from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { colors, radius, spacing, typography } from '../theme';

type Chip = { key: string; label: string; count?: number };

type Props = {
  chips: Chip[];
  activeKey: string | null;
  onSelect: (key: string | null) => void;
  /** Libelle du chip « tout » ; `null` le masque. */
  allLabel?: string | null;
};

/**
 * Filtre horizontal par puces.
 *
 * `accessibilityState.selected` est indispensable : sans lui, un lecteur
 * d'ecran annonce toutes les puces de la meme facon et l'utilisateur ne sait
 * pas laquelle filtre la liste.
 */
export function ChipRow({ chips, activeKey, onSelect, allLabel = 'Tout' }: Props) {
  return (
    <ScrollView
      horizontal
      showsHorizontalScrollIndicator={false}
      contentContainerStyle={styles.row}
    >
      {allLabel ? (
        <Pressable
          accessibilityRole="button"
          accessibilityState={{ selected: activeKey === null }}
          onPress={() => onSelect(null)}
          style={[styles.chip, activeKey === null && styles.chipActive]}
        >
          <Text style={[styles.chipText, activeKey === null && styles.chipTextActive]}>
            {allLabel}
          </Text>
        </Pressable>
      ) : null}
      {chips.map((chip) => {
        const active = activeKey === chip.key;
        return (
          <Pressable
            key={chip.key}
            accessibilityRole="button"
            accessibilityState={{ selected: active }}
            onPress={() => onSelect(active ? null : chip.key)}
            style={[styles.chip, active && styles.chipActive]}
          >
            <Text style={[styles.chipText, active && styles.chipTextActive]}>
              {chip.label}
              {typeof chip.count === 'number' ? ` (${chip.count})` : ''}
            </Text>
          </Pressable>
        );
      })}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  row: {
    gap: spacing.sm,
    paddingVertical: spacing.sm,
    paddingRight: spacing.lg,
  },
  chip: {
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius.pill,
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.sm,
  },
  chipActive: { backgroundColor: colors.primary, borderColor: colors.primary },
  chipText: { color: colors.textMuted, fontSize: typography.body, fontWeight: '600' },
  chipTextActive: { color: colors.white },
});
