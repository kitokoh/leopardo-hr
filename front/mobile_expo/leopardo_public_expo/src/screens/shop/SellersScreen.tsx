import React from 'react';
import { FlatList, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { ScreenHeader } from '../../components/ScreenHeader';
import { ScreenState } from '../../components/ScreenState';
import { Badge, Card } from '../../components/Card';
import { colors, spacing, typography } from '../../theme';
import { useAsync } from '../../hooks/useAsync';
import { fetchSellers } from '../../api/public';
import type { ScreenProps } from '../../navigation/types';

/** ANNUAIRE DES BOUTIQUES — `GET /public/market/sellers`. */
export function SellersScreen({ navigation }: ScreenProps<'Sellers'>) {
  const state = useAsync(() => fetchSellers(), []);
  const sellers = state.data ?? [];

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      <ScreenHeader
        title="Boutiques"
        subtitle={state.data ? `${sellers.length} boutique(s)` : undefined}
        onBack={() => navigation.goBack()}
      />
      <ScreenState
        loading={state.loading}
        failure={state.failure}
        onRetry={state.retry}
        refreshing={state.refreshing}
        onRefresh={state.reload}
        isEmpty={sellers.length === 0}
        emptyTitle="Aucune boutique en ligne"
        emptyMessage="Les boutiques partenaires n'ont pas encore ouvert leur vitrine."
      >
        <FlatList
          data={sellers}
          keyExtractor={(item) => item.slug}
          scrollEnabled={false}
          contentContainerStyle={styles.list}
          renderItem={({ item }) => (
            <Card
              onPress={() =>
                navigation.navigate('Shop', { sellerSlug: item.slug, sellerName: item.name })
              }
              accessibilityLabel={`${item.name}, ${item.products_count} produits`}
              style={styles.card}
            >
              <View style={styles.head}>
                <View style={styles.texts}>
                  <Text style={styles.name} numberOfLines={1}>
                    {item.name}
                  </Text>
                  <Text style={styles.meta} numberOfLines={1}>
                    {[item.city, `${item.products_count} produits`].filter(Boolean).join(' \u00b7 ')}
                  </Text>
                </View>
                {item.rating_avg !== null ? (
                  <Badge label={`\u2605 ${item.rating_avg.toFixed(1)}`} color={colors.warning} />
                ) : null}
              </View>
              {item.description ? (
                <Text style={styles.desc} numberOfLines={2}>
                  {item.description}
                </Text>
              ) : null}
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
  card: { gap: spacing.sm },
  head: { flexDirection: 'row', alignItems: 'flex-start', gap: spacing.md },
  texts: { flex: 1 },
  name: { color: colors.text, fontSize: typography.subheading, fontWeight: '700' },
  meta: { color: colors.textMuted, fontSize: typography.caption, marginTop: 2 },
  desc: { color: colors.textMuted, fontSize: typography.body, lineHeight: 19 },
});
