import React from 'react';
import {
  ActivityIndicator,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { colors, radius, spacing, typography } from '../theme';
import type { PublicApiFailure } from '../api/client';

type Props = {
  loading?: boolean;
  failure?: PublicApiFailure | null;
  onRetry?: () => void;
  children: React.ReactNode;
  refreshing?: boolean;
  onRefresh?: () => void;
  /** Message affiche quand le contenu est vide (liste vide, pas une erreur). */
  emptyTitle?: string;
  emptyMessage?: string;
  isEmpty?: boolean;
};

/**
 * Enveloppe d'ecran : les QUATRE etats obligatoires d'une surface publique.
 *
 * 1. chargement ;
 * 2. erreur — avec « Reessayer » SEULEMENT si l'echec est rejouable (un 404
 *    n'est pas une panne, proposer de reessayer serait mentir) ;
 * 3. vide — distinct de l'erreur : « aucune officine publiee » n'est pas
 *    « service indisponible » ;
 * 4. contenu.
 */
export function ScreenState({
  loading,
  failure,
  onRetry,
  children,
  refreshing,
  onRefresh,
  emptyTitle,
  emptyMessage,
  isEmpty,
}: Props) {
  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color={colors.primary} />
        <Text style={styles.loadingText}>Chargement\u2026</Text>
      </View>
    );
  }

  if (failure) {
    const retryable = failure.isRetryable;
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
        <View style={styles.iconCircle}>
          <Text style={styles.iconGlyph}>{failure.kind === 'notFound' ? '\u2205' : '!'}</Text>
        </View>
        <Text style={styles.errorTitle}>
          {failure.kind === 'notFound' ? 'Introuvable' : 'Impossible de charger'}
        </Text>
        <Text style={styles.errorMessage}>{failure.message}</Text>
        {retryable && onRetry ? (
          <Pressable
            accessibilityRole="button"
            onPress={onRetry}
            style={({ pressed }) => [styles.retry, pressed && styles.retryPressed]}
          >
            <Text style={styles.retryText}>Reessayer</Text>
          </Pressable>
        ) : null}
      </ScrollView>
    );
  }

  if (isEmpty && emptyTitle) {
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
        <View style={styles.iconCircle}>
          <Text style={styles.iconGlyph}>\u25cb</Text>
        </View>
        <Text style={styles.errorTitle}>{emptyTitle}</Text>
        {emptyMessage ? <Text style={styles.errorMessage}>{emptyMessage}</Text> : null}
      </ScrollView>
    );
  }

  return (
    <ScrollView
      contentContainerStyle={styles.content}
      keyboardShouldPersistTaps="handled"
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
  iconCircle: {
    width: 56,
    height: 56,
    borderRadius: 28,
    backgroundColor: colors.surfaceAlt,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: spacing.lg,
  },
  iconGlyph: { fontSize: 24, color: colors.textFaint, fontWeight: '700' },
  errorTitle: {
    color: colors.text,
    fontSize: typography.heading,
    fontWeight: '700',
    textAlign: 'center',
  },
  errorMessage: {
    color: colors.textMuted,
    fontSize: typography.body,
    textAlign: 'center',
    marginTop: spacing.sm,
    lineHeight: 20,
  },
  retry: {
    marginTop: spacing.xl,
    backgroundColor: colors.primary,
    borderRadius: radius.md,
    paddingHorizontal: spacing.xl,
    paddingVertical: spacing.md,
  },
  retryPressed: { opacity: 0.85 },
  retryText: {
    color: colors.white,
    fontSize: typography.subheading,
    fontWeight: '600',
  },
  content: {
    paddingHorizontal: spacing.lg,
    paddingBottom: spacing.xxl,
  },
});
