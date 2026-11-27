import React from 'react';
import {
  ActivityIndicator,
  Pressable,
  StyleSheet,
  Text,
  View,
  ViewStyle,
} from 'react-native';
import { colors, radius, spacing, typography } from '../theme';

type Props = {
  title: string;
  subtitle?: string;
  right?: React.ReactNode;
  style?: ViewStyle;
};

export function ScreenHeader({ title, subtitle, right, style }: Props) {
  return (
    <View style={[styles.header, style]}>
      <View style={styles.texts}>
        <Text style={styles.title}>{title}</Text>
        {subtitle ? <Text style={styles.subtitle}>{subtitle}</Text> : null}
      </View>
      {right}
    </View>
  );
}

const styles = StyleSheet.create({
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: spacing.lg,
    paddingTop: spacing.lg,
    paddingBottom: spacing.md,
  },
  texts: { flex: 1 },
  title: {
    color: colors.text,
    fontSize: typography.title,
    fontWeight: '700',
    letterSpacing: -0.5,
  },
  subtitle: {
    color: colors.textMuted,
    fontSize: typography.body,
    marginTop: 2,
  },
});
