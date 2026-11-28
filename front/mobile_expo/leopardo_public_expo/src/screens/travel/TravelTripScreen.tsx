import React, { useRef, useState } from 'react';
import { KeyboardAvoidingView, Platform, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { ScreenHeader } from '../../components/ScreenHeader';
import { ScreenState } from '../../components/ScreenState';
import { Card, InfoRow, Section } from '../../components/Card';
import { Button } from '../../components/Button';
import { TextField } from '../../components/TextField';
import { colors, spacing, typography } from '../../theme';
import { useAsync } from '../../hooks/useAsync';
import { createTravelBooking, newIdempotencyKey, searchTrips } from '../../api/public';
import { classifyFailure, type PublicApiFailure } from '../../api/client';
import { formatDateTime, formatMoney } from '../../utils/format';
import type { ScreenProps } from '../../navigation/types';

/**
 * RESERVATION D'UN TRAJET — `POST /public/travel/marketplace/bookings`.
 *
 * Contrat : `idempotency_key` OBLIGATOIRE, et CHAQUE passager exige
 * `age_category` + `class_id`. La classe est celle du tarif choisi : on
 * reprend la premiere classe tarifee du trajet, jamais une valeur inventee.
 */
export function TravelTripScreen({ navigation, route }: ScreenProps<'TravelTrip'>) {
  const { tripId } = route.params;
  const [passengerName, setPassengerName] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [failure, setFailure] = useState<PublicApiFailure | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});

  const idempotencyKey = useRef(newIdempotencyKey());

  // Le trajet est relu par la recherche : l'endpoint `show` exige un id
  // numerique et renvoie le meme DTO. On filtre sur l'id recu.
  const state = useAsync(async () => {
    const trips = await searchTrips({});
    return trips.find((trip) => trip.id === tripId) ?? null;
  }, [tripId]);

  const trip = state.data;
  const classId = trip?.prices[0]?.class_id ?? null;

  const submit = async () => {
    const next: Record<string, string> = {};
    if (passengerName.trim().length < 2) next.name = 'Indiquez le nom du passager.';
    if (!email.trim() && !phone.trim()) next.contact = 'Laissez au moins un email ou un telephone.';
    if (classId === null) next.cls = 'Aucun tarif disponible pour ce trajet.';
    setErrors(next);
    if (Object.keys(next).length > 0) return;

    setSubmitting(true);
    setFailure(null);
    try {
      const result = await createTravelBooking({
        tripId,
        passengers: [
          {
            full_name: passengerName.trim(),
            age_category: 'adult',
            class_id: classId as number,
          },
        ],
        contactEmail: email.trim() || undefined,
        contactPhone: phone.trim() || undefined,
        idempotencyKey: idempotencyKey.current,
      });
      navigation.replace('Confirmation', {
        title: 'Reservation confirmee',
        reference: result.reference,
        message:
          'Votre place est reservee. Presentez votre reference au depart. Le suivi se fait avec cette reference.',
        totalMinor: result.total_amount_minor,
        currency: result.currency,
      });
    } catch (error) {
      setFailure(classifyFailure(error));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      <ScreenHeader title="Reserver un billet" onBack={() => navigation.goBack()} />
      <ScreenState
        loading={state.loading}
        failure={state.failure}
        onRetry={state.retry}
        refreshing={state.refreshing}
        onRefresh={state.reload}
        isEmpty={trip === null}
        emptyTitle="Trajet introuvable"
        emptyMessage="Ce trajet n'est plus disponible a la vente."
      >
        {trip ? (
          <KeyboardAvoidingView style={styles.flex} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
            <View style={styles.content}>
              <Card style={styles.tripCard}>
                <Text style={styles.route}>
                  {trip.origin_city?.name} \u2192 {trip.destination_city?.name}
                </Text>
                <InfoRow label="Depart" value={formatDateTime(trip.departure_date, trip.departure_time)} />
                {trip.arrival_date ? (
                  <InfoRow label="Arrivee" value={formatDateTime(trip.arrival_date, trip.arrival_time)} />
                ) : null}
                <InfoRow label="Places libres" value={String(trip.available_seats)} />
                {trip.agency.name ? <InfoRow label="Agence" value={trip.agency.name} /> : null}
                <InfoRow
                  label="Tarif adulte"
                  value={
                    trip.prices[0]
                      ? formatMoney(trip.prices[0].adult_price_minor, trip.prices[0].currency)
                      : 'A confirmer'
                  }
                  strong
                />
              </Card>

              <Section title="Passager">
                <TextField label="Nom complet" required value={passengerName} onChangeText={setPassengerName} placeholder="Nom et prenom" autoCapitalize="words" error={errors.name} />
                <TextField label="Email" value={email} onChangeText={setEmail} placeholder="vous@exemple.com" keyboardType="email-address" autoCapitalize="none" error={errors.contact} />
                <TextField label="Telephone" value={phone} onChangeText={setPhone} placeholder="+225 07 00 00 00 00" keyboardType="phone-pad" />
              </Section>

              {failure ? (
                <Card style={styles.failureCard}>
                  <Text style={styles.failureTitle}>Reservation non enregistree</Text>
                  <Text style={styles.failureText}>{failure.message}</Text>
                </Card>
              ) : null}

              <Button
                label={submitting ? 'Envoi\u2026' : 'Reserver ma place'}
                onPress={submit}
                loading={submitting}
                disabled={trip.available_seats <= 0}
                style={styles.submit}
              />
              {trip.available_seats <= 0 ? (
                <Text style={styles.hint}>Ce trajet est complet.</Text>
              ) : null}
            </View>
          </KeyboardAvoidingView>
        ) : null}
      </ScreenState>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.background },
  flex: { flex: 1 },
  content: { paddingHorizontal: spacing.lg, paddingBottom: spacing.xxl },
  tripCard: { gap: spacing.xs },
  route: { color: colors.text, fontSize: typography.title, fontWeight: '800', letterSpacing: -0.5, marginBottom: spacing.sm },
  failureCard: { borderColor: colors.danger, backgroundColor: '#FEF2F2', marginTop: spacing.lg },
  failureTitle: { color: colors.danger, fontSize: typography.subheading, fontWeight: '700' },
  failureText: { color: colors.danger, fontSize: typography.body, marginTop: spacing.xs, lineHeight: 19 },
  submit: { marginTop: spacing.xl },
  hint: { color: colors.textFaint, fontSize: typography.caption, textAlign: 'center', marginTop: spacing.md },
});
