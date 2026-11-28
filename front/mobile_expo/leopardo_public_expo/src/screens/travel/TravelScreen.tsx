import React, { useMemo, useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { ScreenHeader } from '../../components/ScreenHeader';
import { ScreenState } from '../../components/ScreenState';
import { Badge, Card } from '../../components/Card';
import { Button } from '../../components/Button';
import { colors, radius, spacing, typography } from '../../theme';
import { useAsync } from '../../hooks/useAsync';
import { fetchTravelCities, searchTrips } from '../../api/public';
import { formatDateTime, formatMoney, todayIso } from '../../utils/format';
import type { ScreenProps } from '../../navigation/types';

/**
 * RECHERCHE DE BILLETS — `/public/travel/marketplace`.
 *
 * Les villes viennent de `GET .../cities` : seules les villes DESSERVIES par
 * un trajet publie y figurent. On ne propose donc jamais une destination qui
 * ne renverrait aucun resultat.
 */
export function TravelScreen({ navigation }: ScreenProps<'Travel'>) {
  const cities = useAsync(() => fetchTravelCities(), []);
  const [originId, setOriginId] = useState<number | null>(null);
  const [destinationId, setDestinationId] = useState<number | null>(null);
  const [date, setDate] = useState(todayIso());
  const [searchKey, setSearchKey] = useState(0);

  const trips = useAsync(
    () =>
      searchTrips({
        originCityId: originId,
        destinationCityId: destinationId,
        date,
      }),
    [originId, destinationId, date, searchKey],
  );

  const cityList = cities.data ?? [];
  const results = trips.data ?? [];

  const canSearch = originId !== null && destinationId !== null && originId !== destinationId;

  const swap = () => {
    setOriginId(destinationId);
    setDestinationId(originId);
  };

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      <ScreenHeader title="Billets" subtitle="Trajets inter-villes" onBack={() => navigation.goBack()} />
      <ScreenState
        loading={cities.loading}
        failure={cities.failure}
        onRetry={cities.retry}
        refreshing={cities.refreshing}
        onRefresh={cities.reload}
        isEmpty={cityList.length === 0}
        emptyTitle="Aucun trajet publie"
        emptyMessage="Les agences partenaires n'ont pas encore publie de trajets."
      >
        <Card style={styles.searchCard}>
          <Text style={styles.fieldLabel}>Depart</Text>
          <View style={styles.cityWrap}>
            {cityList.map((city) => {
              const active = originId === city.id;
              return (
                <Pressable
                  key={`o-${city.id}`}
                  accessibilityRole="button"
                  accessibilityState={{ selected: active }}
                  onPress={() => setOriginId(active ? null : city.id)}
                  style={[styles.cityChip, active && styles.cityChipActive]}
                >
                  <Text style={[styles.cityText, active && styles.cityTextActive]}>{city.name}</Text>
                </Pressable>
              );
            })}
          </View>

          <Pressable
            accessibilityRole="button"
            accessibilityLabel="Inverser depart et destination"
            onPress={swap}
            style={styles.swap}
          >
            <Text style={styles.swapGlyph}>\u21C5</Text>
            <Text style={styles.swapText}>Inverser</Text>
          </Pressable>

          <Text style={styles.fieldLabel}>Destination</Text>
          <View style={styles.cityWrap}>
            {cityList.map((city) => {
              const active = destinationId === city.id;
              const disabled = originId === city.id;
              return (
                <Pressable
                  key={`d-${city.id}`}
                  accessibilityRole="button"
                  accessibilityState={{ selected: active, disabled }}
                  disabled={disabled}
                  onPress={() => setDestinationId(active ? null : city.id)}
                  style={[
                    styles.cityChip,
                    active && styles.cityChipActive,
                    disabled && styles.cityChipDisabled,
                  ]}
                >
                  <Text style={[styles.cityText, active && styles.cityTextActive]}>{city.name}</Text>
                </Pressable>
              );
            })}
          </View>

          <Text style={styles.fieldLabel}>Date</Text>
          <View style={styles.dateRow}>
            <Button label="\u2039" variant="secondary" onPress={() => setDate((d) => shiftIso(d, -1))} style={styles.dateButton} />
            <Text style={styles.dateValue}>{date}</Text>
            <Button label="\u203a" variant="secondary" onPress={() => setDate((d) => shiftIso(d, 1))} style={styles.dateButton} />
          </View>

          <Button
            label="Rechercher"
            disabled={!canSearch}
            onPress={() => setSearchKey((k) => k + 1)}
            style={styles.searchButton}
          />
          {!canSearch ? (
            <Text style={styles.searchHint}>Choisissez une ville de depart et une destination differentes.</Text>
          ) : null}
        </Card>

        {canSearch ? (
          <View style={styles.results}>
            <Text style={styles.resultsTitle}>
              {trips.loading ? 'Recherche\u2026' : `${results.length} trajet(s)`}
            </Text>
            {trips.failure ? (
              <Card style={styles.failureCard}>
                <Text style={styles.failureTitle}>Recherche impossible</Text>
                <Text style={styles.failureText}>{trips.failure.message}</Text>
                <Button label="Reessayer" variant="secondary" onPress={trips.retry} style={styles.retryButton} />
              </Card>
            ) : results.length === 0 && !trips.loading ? (
              <Card>
                <Text style={styles.emptyTitle}>Aucun trajet ce jour</Text>
                <Text style={styles.emptyText}>Essayez une autre date ou un autre itineraire.</Text>
              </Card>
            ) : (
              results.map((trip) => (
                <Card
                  key={trip.id}
                  onPress={() => navigation.navigate('TravelTrip', { tripId: trip.id })}
                  accessibilityLabel={`${trip.origin_city?.name} vers ${trip.destination_city?.name}, ${trip.available_seats} places`}
                  style={styles.tripCard}
                >
                  <View style={styles.tripHead}>
                    <Text style={styles.tripRoute} numberOfLines={1}>
                      {trip.origin_city?.name} \u2192 {trip.destination_city?.name}
                    </Text>
                    <Badge
                      label={trip.available_seats > 0 ? `${trip.available_seats} places` : 'Complet'}
                      color={trip.available_seats > 0 ? colors.success : colors.danger}
                    />
                  </View>
                  <Text style={styles.tripMeta}>
                    {formatDateTime(trip.departure_date, trip.departure_time)}
                    {trip.agency.name ? ` \u00b7 ${trip.agency.name}` : ''}
                  </Text>
                  <Text style={styles.tripPrice}>
                    {trip.price_from_minor !== null
                      ? `A partir de ${formatMoney(trip.price_from_minor, trip.currency)}`
                      : 'Prix a confirmer'}
                  </Text>
                </Card>
              ))
            )}
          </View>
        ) : null}
      </ScreenState>
    </SafeAreaView>
  );
}

function shiftIso(iso: string, days: number): string {
  const date = new Date(`${iso}T00:00:00`);
  date.setDate(date.getDate() + days);
  const month = `${date.getMonth() + 1}`.padStart(2, '0');
  const day = `${date.getDate()}`.padStart(2, '0');
  return `${date.getFullYear()}-${month}-${day}`;
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.background },
  searchCard: { gap: spacing.sm },
  fieldLabel: {
    color: colors.textMuted,
    fontSize: typography.caption,
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: 0.6,
    marginTop: spacing.md,
  },
  cityWrap: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm, marginTop: spacing.xs },
  cityChip: {
    backgroundColor: colors.surfaceAlt,
    borderRadius: radius.pill,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
  },
  cityChipActive: { backgroundColor: colors.primary },
  cityChipDisabled: { opacity: 0.35 },
  cityText: { color: colors.textMuted, fontSize: typography.body, fontWeight: '600' },
  cityTextActive: { color: colors.white },
  swap: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm, alignSelf: 'center', paddingVertical: spacing.sm },
  swapGlyph: { fontSize: 18, color: colors.primary },
  swapText: { color: colors.primary, fontSize: typography.caption, fontWeight: '700' },
  dateRow: { flexDirection: 'row', alignItems: 'center', gap: spacing.md, marginTop: spacing.xs },
  dateButton: { minWidth: 48, paddingHorizontal: 0 },
  dateValue: { flex: 1, textAlign: 'center', color: colors.text, fontSize: typography.subheading, fontWeight: '700' },
  searchButton: { marginTop: spacing.lg },
  searchHint: { color: colors.textFaint, fontSize: typography.caption, textAlign: 'center', marginTop: spacing.sm },
  results: { marginTop: spacing.xl },
  resultsTitle: { color: colors.text, fontSize: typography.heading, fontWeight: '700', marginBottom: spacing.md },
  tripCard: { gap: spacing.xs, marginBottom: spacing.sm },
  tripHead: { flexDirection: 'row', alignItems: 'center', gap: spacing.md },
  tripRoute: { flex: 1, color: colors.text, fontSize: typography.subheading, fontWeight: '700' },
  tripMeta: { color: colors.textMuted, fontSize: typography.caption },
  tripPrice: { color: colors.primary, fontSize: typography.body, fontWeight: '700', marginTop: spacing.xs },
  failureCard: { borderColor: colors.danger, backgroundColor: '#FEF2F2', gap: spacing.xs },
  failureTitle: { color: colors.danger, fontSize: typography.subheading, fontWeight: '700' },
  failureText: { color: colors.danger, fontSize: typography.body, lineHeight: 19 },
  retryButton: { marginTop: spacing.sm },
  emptyTitle: { color: colors.text, fontSize: typography.subheading, fontWeight: '700' },
  emptyText: { color: colors.textMuted, fontSize: typography.body, marginTop: spacing.xs },
});
