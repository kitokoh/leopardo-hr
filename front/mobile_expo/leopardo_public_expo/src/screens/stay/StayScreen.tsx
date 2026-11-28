import React, { useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { ScreenHeader } from '../../components/ScreenHeader';
import { ScreenState } from '../../components/ScreenState';
import { Badge, Card, Section } from '../../components/Card';
import { Button } from '../../components/Button';
import { colors, radius, spacing, typography } from '../../theme';
import { useAsync } from '../../hooks/useAsync';
import { fetchStayProperty, fetchAvailability } from '../../api/public';
import { addDaysIso, formatMoney, nightsBetween, todayIso } from '../../utils/format';
import type { ScreenProps } from '../../navigation/types';

/**
 * FICHE SEJOUR + DISPONIBILITES.
 *
 * Deux appels distincts, et c'est voulu :
 *  - `GET /public/hospitality/properties/{slug}` donne la fiche et les types
 *    de chambre (avec leur prix de base) ;
 *  - `GET .../availability?from=&to=` donne le NOMBRE de chambres libres sur
 *    la periode. Un type de chambre peut exister sans etre disponible :
 *    afficher « disponible » sans interroger la disponibilite serait faux.
 */
export function StayScreen({ navigation, route }: ScreenProps<'Stay'>) {
  const { slug } = route.params;
  const [checkIn, setCheckIn] = useState(todayIso());
  const [checkOut, setCheckOut] = useState(addDaysIso(todayIso(), 2));

  const property = useAsync(() => fetchStayProperty(slug), [slug]);
  const availability = useAsync(
    () => fetchAvailability({ slug, from: checkIn, to: checkOut }),
    [slug, checkIn, checkOut],
  );

  const nights = nightsBetween(checkIn, checkOut);
  const rooms = availability.data ?? [];
  const stay = property.data;

  const shift = (days: number) => {
    const nextIn = addDaysIso(checkIn, days);
    const nextOut = addDaysIso(checkOut, days);
    if (nightsBetween(nextIn, nextOut) < 1) return;
    setCheckIn(nextIn);
    setCheckOut(nextOut);
  };

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      <ScreenHeader
        title={stay?.name ?? 'Sejour'}
        subtitle={stay?.city ?? undefined}
        onBack={() => navigation.goBack()}
      />
      <ScreenState
        loading={property.loading}
        failure={property.failure}
        onRetry={property.retry}
        refreshing={property.refreshing}
        onRefresh={property.reload}
      >
        {stay ? (
          <>
            <Card style={styles.hero}>
              <View style={styles.heroHead}>
                <Text style={styles.heroName}>{stay.name}</Text>
                {stay.star_rating ? (
                  <Badge label={`${stay.star_rating} \u2605`} color={colors.warning} />
                ) : null}
              </View>
              <Text style={styles.heroMeta}>
                {[stay.address, stay.city, stay.country].filter(Boolean).join(' \u00b7 ')}
              </Text>
              {stay.amenities && stay.amenities.length > 0 ? (
                <Text style={styles.amenities}>{stay.amenities.join(' \u00b7 ')}</Text>
              ) : null}
            </Card>

            <Section title="Vos dates">
              <Card>
                <View style={styles.dateRow}>
                  <View style={styles.dateCol}>
                    <Text style={styles.dateLabel}>Arrivee</Text>
                    <Text style={styles.dateValue}>{checkIn}</Text>
                  </View>
                  <View style={styles.dateCol}>
                    <Text style={styles.dateLabel}>Depart</Text>
                    <Text style={styles.dateValue}>{checkOut}</Text>
                  </View>
                </View>
                <Text style={styles.nights}>
                  {nights} nuit{nights > 1 ? 's' : ''}
                </Text>
                <View style={styles.shiftRow}>
                  <Button label="\u2039 Precedent" variant="secondary" onPress={() => shift(-1)} style={styles.shiftButton} />
                  <Button label="Suivant \u203a" variant="secondary" onPress={() => shift(1)} style={styles.shiftButton} />
                </View>
              </Card>
            </Section>

            <Section title="Chambres">
              {availability.loading ? (
                <Card>
                  <Text style={styles.loadingText}>Verification des disponibilites\u2026</Text>
                </Card>
              ) : availability.failure ? (
                <Card style={styles.failureCard}>
                  <Text style={styles.failureTitle}>Disponibilites indisponibles</Text>
                  <Text style={styles.failureText}>{availability.failure.message}</Text>
                  <Button label="Reessayer" variant="secondary" onPress={availability.retry} style={styles.retryButton} />
                </Card>
              ) : rooms.length === 0 ? (
                <Card>
                  <Text style={styles.emptyTitle}>Aucune chambre ouverte</Text>
                  <Text style={styles.emptyText}>
                    Cet etablissement n'a pas de type de chambre actif pour le moment.
                  </Text>
                </Card>
              ) : (
                rooms.map((room) => {
                  const soldOut = room.available <= 0;
                  return (
                    <Card key={room.room_type_id} style={styles.roomCard}>
                      <View style={styles.roomTexts}>
                        <Text style={styles.roomName}>{room.name}</Text>
                        <Text style={styles.roomMeta}>
                          {soldOut
                            ? 'Complet sur ces dates'
                            : `${room.available} chambre(s) libre(s)`}
                        </Text>
                        <Text style={styles.roomPrice}>
                          {formatMoney(room.base_price_minor, room.currency)} / nuit
                        </Text>
                      </View>
                      <Button
                        label={soldOut ? 'Complet' : 'Reserver'}
                        disabled={soldOut}
                        onPress={() =>
                          navigation.navigate('StayBooking', {
                            slug,
                            propertyName: stay.name,
                            roomTypeId: room.room_type_id,
                            roomName: room.name,
                          })
                        }
                        style={styles.roomButton}
                      />
                    </Card>
                  );
                })
              )}
            </Section>
          </>
        ) : null}
      </ScreenState>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.background },
  hero: { gap: spacing.sm },
  heroHead: { flexDirection: 'row', alignItems: 'center', gap: spacing.md },
  heroName: { flex: 1, color: colors.text, fontSize: typography.title, fontWeight: '800', letterSpacing: -0.5 },
  heroMeta: { color: colors.textMuted, fontSize: typography.body },
  amenities: { color: colors.textFaint, fontSize: typography.caption },
  dateRow: { flexDirection: 'row', gap: spacing.lg },
  dateCol: { flex: 1 },
  dateLabel: { color: colors.textMuted, fontSize: typography.caption, fontWeight: '700', textTransform: 'uppercase', letterSpacing: 0.6 },
  dateValue: { color: colors.text, fontSize: typography.subheading, fontWeight: '700', marginTop: 2 },
  nights: { color: colors.primary, fontSize: typography.caption, fontWeight: '700', marginTop: spacing.sm },
  shiftRow: { flexDirection: 'row', gap: spacing.md, marginTop: spacing.md },
  shiftButton: { flex: 1 },
  loadingText: { color: colors.textMuted, fontSize: typography.body },
  failureCard: { borderColor: colors.danger, backgroundColor: '#FEF2F2', gap: spacing.xs },
  failureTitle: { color: colors.danger, fontSize: typography.subheading, fontWeight: '700' },
  failureText: { color: colors.danger, fontSize: typography.body, lineHeight: 19 },
  retryButton: { marginTop: spacing.sm },
  emptyTitle: { color: colors.text, fontSize: typography.subheading, fontWeight: '700' },
  emptyText: { color: colors.textMuted, fontSize: typography.body, marginTop: spacing.xs, lineHeight: 19 },
  roomCard: { flexDirection: 'row', alignItems: 'center', gap: spacing.md, marginBottom: spacing.sm },
  roomTexts: { flex: 1 },
  roomName: { color: colors.text, fontSize: typography.subheading, fontWeight: '600' },
  roomMeta: { color: colors.textMuted, fontSize: typography.caption, marginTop: 2 },
  roomPrice: { color: colors.primary, fontSize: typography.body, fontWeight: '700', marginTop: spacing.xs },
  roomButton: { paddingHorizontal: spacing.lg },
});
