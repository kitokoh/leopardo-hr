import React from 'react';
import {
  ActivityIndicator,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { colors, spacing, typography } from '../theme';

type Props = {
  loading?: boolean;
  error?: string | null;
  onRetry?: () => void;
  children: React.ReactNode;
  refreshing?: boolean;
  onRefresh?: () => void;
};

/**
 * Enveloppe d'ecran : gere les trois etats (chargement / erreur / contenu)
 * et le tirer-pour-rafraichir, pour eviter de dupliquer cette logique
 * dans chaque ecran.
 */
export function ScreenState({
  loading,
  error,
  onRetry,
  children,
  refreshing,
  onRefresh,
}: Props) {
  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color={colors.primary} />
        <Text style={styles.loadingText}>Chargement…</Text>
      </View>
    );
  }

  if (error) {
    return (
      <ScrollView
        contentContainerStyle={styles.center}
        refreshControl={
          onRefresh ? (
            <RefreshControl
              refreshing={Boolean(refreshing)}
              onRefresh={onRefresh}
              tintColor={colors.primary}
            />
          ) : undefined
        }
      >
        <Text style={styles.errorTitle}>Impossible de charger les donnees</Text>
        <Text style={styles.errorMessage}>{error}</Text>
        {onRetry ? (
          <Text style={styles.retry} onPress={onRetry}>
            Reessayer
          </Text>
        ) : null}
      </ScrollView>
    );
  }

  return (
    <ScrollView
      contentContainerStyle={styles.content}
      refreshControl={
        onRefresh ? (
          <RefreshControl
            refreshing={Boolean(refreshing)}
            onRefresh={onRefresh}
            tintColor={colors.primary}
          />
        ) : undefined
      }
    >
      {children}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  center: {
    flexGrow: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: spacing.xl,
  },
  loadingText: {
    color: colors.textMuted,
    marginTop: spacing.md,
    fontSize: typography.body,
  },
  errorTitle: {
    color: colors.text,
    fontSize: typography.subheading,
    fontWeight: '600',
    textAlign: 'center',
  },
  errorMessage: {
    color: colors.textMuted,
    fontSize: typography.body,
    textAlign: 'center',
    marginTop: spacing.sm,
  },
  retry: {
    color: colors.primary,
    fontSize: typography.subheading,
    fontWeight: '600',
    marginTop: spacing.lg,
  },
  content: {
    paddingHorizontal: spacing.lg,
    paddingBottom: spacing.xxl,
  },
});
