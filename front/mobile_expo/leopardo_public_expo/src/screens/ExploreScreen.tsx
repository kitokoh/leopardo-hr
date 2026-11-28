import React, { useMemo, useState } from 'react';
import { FlatList, Pressable, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { ScreenHeader } from '../components/ScreenHeader';
import { ScreenState } from '../components/ScreenState';
import { ChipRow } from '../components/ChipRow';
import { Badge, Card } from '../components/Card';
import { TextField } from '../components/TextField';
import { colors, radius, spacing, typography } from '../theme';
import { useAsync } from '../hooks/useAsync';
import { discover, type DiscoveryItem } from '../api/public';
import { DISCOVERABLE_VERTICALS, journeyByType } from '../verticals/registry';
import type { ScreenProps } from '../navigation/types';

/**
 * EXPLORER — annuaire cross-verticale (`GET /public/discover`).
 *
 * Le filtre par type n'envoie que des types REELLEMENT acceptes par le
 * serveur (`meta.types`), c'est-a-dire ceux des verticales qui declarent un
 * `discoveryType`. Envoyer un type inconnu produirait un 422.
 */
export function ExploreScreen({ navigation, route }: ScreenProps<'Explore'>) {
  const initialType = route.params?.type ?? null;
  const [type, setType] = useState<string | null>(initialType);
  const [query, setQuery] = useState('');
  const [submitted, setSubmitted] = useState('');

  const chips = useMemo(
    () =>
      DISCOVERABLE_VERTICALS.map((vertical) => ({
        key: vertical.discoveryType as string,
        label: vertical.label,
      })),
    [],
  );

  const state = useAsync(
    () => discover({ type, q: submitted || null, perPage: 30 }),
    [type, submitted],
  );

  const openItem = (item: DiscoveryItem) => {
    const vertical = journeyByType(item.type);
    switch (vertical?.code) {
      case 'restaurant':
        navigation.navigate('Restaurant', { slug: item.slug });
        break;
      case 'commerce':
        navigation.navigate('Shop', { sellerSlug: item.slug, sellerName: item.name });
        break;
      case 'hospitality':
        navigation.navigate('Stay', { slug: item.slug });
        break;
      case 'mobility':
        navigation.navigate('VtcRide', { operatorSlug: item.slug, operatorName: item.name });
        break;
      case 'trade':
        navigation.navigate('Trade', { slug: item.slug });
        break;
      default:
        break;
    }
  };

  const items = state.data?.items ?? [];

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      <ScreenHeader
        title="Explorer"
        subtitle={state.data ? `${state.data.total} etablissement(s)` : undefined}
        onBack={() => navigation.goBack()}
      />

      <View style={styles.filters}>
        <TextField
          label="Rechercher"
          placeholder="Nom, ville, specialite\u2026"
          value={query}
          onChangeText={setQuery}
          returnKeyType="search"
          onSubmitEditing={() => setSubmitted(query.trim())}
          autoCorrect={false}
        />
        <ChipRow chips={chips} activeKey={type} onSelect={setType} />
      </View>

      <ScreenState
        loading={state.loading}
        failure={state.failure}
        onRetry={state.retry}
        refreshing={state.refreshing}
        onRefresh={state.reload}
        isEmpty={items.length === 0}
        emptyTitle="Aucun resultat"
        emptyMessage="Essayez une autre ville ou retirez le filtre."
      >
        <FlatList
          data={items}
          keyExtractor={(item) => `${item.type}|${item.slug}`}
          scrollEnabled={false}
          contentContainerStyle={styles.list}
          renderItem={({ item }) => {
            const vertical = journeyByType(item.type);
            return (
              <Card
                onPress={() => openItem(item)}
                accessibilityLabel={`${item.name}${item.city ? `, ${item.city}` : ''}`}
                style={styles.itemCard}
              >
                <View style={styles.itemHead}>
                  <View style={styles.itemTexts}>
                    <Text style={styles.itemName} numberOfLines={1}>
                      {item.name}
                    </Text>
                    <Text style={styles.itemMeta} numberOfLines={1}>
                      {[item.category, item.city].filter(Boolean).join(' \u00b7 ') || 'Leopardo'}
                    </Text>
                  </View>
                  {vertical ? (
                    <Badge label={vertical.label} color={vertical.accent} />
                  ) : null}
                </View>
                {item.description ? (
                  <Text style={styles.itemDesc} numberOfLines={2}>
                    {item.description}
                  </Text>
                ) : null}
                {typeof item.distance_km === 'number' ? (
                  <Text style={styles.distance}>{item.distance_km.toFixed(1)} km</Text>
                ) : null}
              </Card>
            );
          }}
        />
      </ScreenState>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.background },
  filters: { paddingHorizontal: spacing.lg },
  list: { gap: spacing.md, paddingTop: spacing.sm },
  itemCard: { gap: spacing.sm },
  itemHead: { flexDirection: 'row', alignItems: 'flex-start', gap: spacing.md },
  itemTexts: { flex: 1 },
  itemName: { color: colors.text, fontSize: typography.subheading, fontWeight: '700' },
  itemMeta: { color: colors.textMuted, fontSize: typography.caption, marginTop: 2 },
  itemDesc: { color: colors.textMuted, fontSize: typography.body, lineHeight: 19 },
  distance: { color: colors.primary, fontSize: typography.caption, fontWeight: '700' },
});
