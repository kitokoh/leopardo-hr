import React from 'react';
import { FlatList, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { ScreenHeader } from '../../components/ScreenHeader';
import { ScreenState } from '../../components/ScreenState';
import { Card } from '../../components/Card';
import { colors, spacing, typography } from '../../theme';
import { useAsync } from '../../hooks/useAsync';
import { fetchVtcOperators } from '../../api/public';
import type { ScreenProps } from '../../navigation/types';

/** ANNUAIRE VTC — `GET /public/vtc/marketplace/operators`. */
export function VtcScreen({ navigation }: ScreenProps<'Vtc'>) {
  const state = useAsync(() => fetchVtcOperators(), []);
  const operators = state.data ?? [];

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      <ScreenHeader
        title="VTC & taxi"
        subtitle={state.data ? `${operators.length} operateur(s)` : undefined}
        onBack={() => navigation.goBack()}
      />
      <ScreenState
        loading={state.loading}
        failure={state.failure}
        onRetry={state.retry}
        refreshing={state.refreshing}
        onRefresh={state.reload}
        isEmpty={operators.length === 0}
        emptyTitle="Aucun operateur"
        emptyMessage="Aucun operateur VTC n'est ouvert dans votre zone pour le moment."
      >
        <FlatList
          data={operators}
          keyExtractor={(item) => item.slug}
          scrollEnabled={false}
          contentContainerStyle={styles.list}
          renderItem={({ item }) => (
            <Card
              onPress={() =>
                navigation.navigate('VtcRide', { operatorSlug: item.slug, operatorName: item.name })
              }
              accessibilityLabel={`${item.name}${item.city ? `, ${item.city}` : ''}`}
              style={styles.card}
            >
              <Text style={styles.name} numberOfLines={1}>
                {item.name}
              </Text>
              <Text style={styles.meta} numberOfLines={1}>
                {[item.city, item.country].filter(Boolean).join(' \u00b7 ') || 'Zone non precisee'}
              </Text>
            </Card>
          )}
        />
      </ScreenState>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.background },
  list: { gap: spacing.md, paddingHorizontal: spacing.lg, paddingTop: spacing.sm },
  card: { gap: spacing.xs },
  name: { color: colors.text, fontSize: typography.subheading, fontWeight: '700' },
  meta: { color: colors.textMuted, fontSize: typography.caption },
});
