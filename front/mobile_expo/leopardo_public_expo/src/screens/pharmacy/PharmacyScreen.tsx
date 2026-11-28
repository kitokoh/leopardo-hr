import React, { useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { ScreenHeader } from '../../components/ScreenHeader';
import { ScreenState } from '../../components/ScreenState';
import { Card } from '../../components/Card';
import { TextField } from '../../components/TextField';
import { colors, spacing, typography } from '../../theme';
import { useAsync } from '../../hooks/useAsync';
import { discover } from '../../api/public';
import { blockerFor, journeyByCode } from '../../verticals/registry';
import type { ScreenProps } from '../../navigation/types';

/**
 * PHARMACIES — annuaire `GET /public/discover?type=pharmacy`.
 *
 * L'annuaire est consultable ; la prise de contact ne l'est pas (le type
 * `pharmacy` est absent de `PublicProfileLocator::types()`, donc
 * `POST /public/contact-requests` repondrait 422). L'ecran affiche donc le
 * motif exact au lieu d'un formulaire qui echouerait.
 */
export function PharmacyScreen({ navigation }: ScreenProps<'Pharmacy'>) {
  const [query, setQuery] = useState('');
  const [submitted, setSubmitted] = useState('');
  const state = useAsync(() => discover({ type: 'pharmacy', q: submitted || null, perPage: 30 }), [submitted]);
  const items = state.data?.items ?? [];
  const contactBlocker = blockerFor(journeyByCode('health')!, 'contact');

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      <ScreenHeader title="Pharmacies" subtitle={state.data ? `${state.data.total} officine(s)` : undefined} onBack={() => navigation.goBack()} />
      <ScreenState
        loading={state.loading}
        failure={state.failure}
        onRetry={state.retry}
        refreshing={state.refreshing}
        onRefresh={state.reload}
        isEmpty={items.length === 0}
        emptyTitle="Aucune pharmacie publiee"
        emptyMessage="Les officines n'ont pas encore ouvert leur fiche publique."
      >
        <TextField
          label="Rechercher"
          placeholder="Nom, ville, quartier\u2026"
          value={query}
          onChangeText={setQuery}
          returnKeyType="search"
          onSubmitEditing={() => setSubmitted(query.trim())}
          autoCorrect={false}
        />

        {contactBlocker ? (
          <Card style={styles.blockerCard}>
            <Text style={styles.blockerTitle}>Contact direct indisponible</Text>
            <Text style={styles.blockerText}>{contactBlocker}</Text>
          </Card>
        ) : null}

        {items.map((item) => (
          <Card key={item.slug} style={styles.card}>
            <Text style={styles.name} numberOfLines={1}>
              {item.name}
            </Text>
            <Text style={styles.meta} numberOfLines={2}>
              {[item.category, item.city, item.description].filter(Boolean).join(' \u00b7 ')}
            </Text>
            {typeof item.distance_km === 'number' ? (
              <Text style={styles.distance}>{item.distance_km.toFixed(1)} km</Text>
            ) : null}
          </Card>
        ))}
      </ScreenState>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.background },
  blockerCard: { marginTop: spacing.md, backgroundColor: colors.surfaceAlt, borderColor: colors.border },
  blockerTitle: { color: colors.text, fontSize: typography.subheading, fontWeight: '700' },
  blockerText: { color: colors.textMuted, fontSize: typography.body, marginTop: spacing.xs, lineHeight: 19 },
  card: { marginTop: spacing.md, gap: spacing.xs },
  name: { color: colors.text, fontSize: typography.subheading, fontWeight: '700' },
  meta: { color: colors.textMuted, fontSize: typography.caption, lineHeight: 17 },
  distance: { color: colors.primary, fontSize: typography.caption, fontWeight: '700' },
});
