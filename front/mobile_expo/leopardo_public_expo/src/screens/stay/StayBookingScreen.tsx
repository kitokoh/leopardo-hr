import React, { useRef, useState } from 'react';
import { KeyboardAvoidingView, Platform, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { ScreenHeader } from '../../components/ScreenHeader';
import { Card, InfoRow, Section } from '../../components/Card';
import { Button } from '../../components/Button';
import { TextField } from '../../components/TextField';
import { colors, spacing, typography } from '../../theme';
import { createStayReservation, newIdempotencyKey } from '../../api/public';
import { classifyFailure, type PublicApiFailure } from '../../api/client';
import { addDaysIso, todayIso } from '../../utils/format';
import type { ScreenProps } from '../../navigation/types';

/**
 * RESERVATION DE SEJOUR — `POST /public/hospitality/properties/{slug}/reservations`.
 *
 * Le montant n'est PAS envoye : le serveur le calcule (`base_price_minor` x
 * nuits). L'ecran ne l'affiche donc pas avant confirmation — annoncer un
 * total que le serveur peut recalculer serait un engagement qu'on ne tient pas.
 */
export function StayBookingScreen({ navigation, route }: ScreenProps<'StayBooking'>) {
  const { slug, propertyName, roomTypeId, roomName } = route.params;
  const [guestName, setGuestName] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [checkIn, setCheckIn] = useState(todayIso());
  const [checkOut, setCheckOut] = useState(addDaysIso(todayIso(), 2));
  const [adults, setAdults] = useState(1);
  const [children, setChildren] = useState(0);
  const [submitting, setSubmitting] = useState(false);
  const [failure, setFailure] = useState<PublicApiFailure | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});

  const idempotencyKey = useRef(newIdempotencyKey());

  const submit = async () => {
    const next: Record<string, string> = {};
    if (guestName.trim().length < 2) next.guestName = 'Indiquez le nom du voyageur principal.';
    if (!email.trim() && !phone.trim()) {
      next.contact = 'Laissez au moins un email ou un telephone.';
    }
    if (checkOut <= checkIn) next.dates = 'La date de depart doit suivre la date d\u2019arrivee.';
    setErrors(next);
    if (Object.keys(next).length > 0) return;

    setSubmitting(true);
    setFailure(null);
    try {
      const result = await createStayReservation({
        slug,
        roomTypeId,
        guestName: guestName.trim(),
        email: email.trim() || undefined,
        phone: phone.trim() || undefined,
        checkIn,
        checkOut,
        adults,
        children,
        idempotencyKey: idempotencyKey.current,
      });
      navigation.replace('Confirmation', {
        title: 'Reservation enregistree',
        reference: result.reference,
        code: result.tracking_code,
        message: `${propertyName} \u2014 ${roomName}. La reservation est en attente de confirmation par l'etablissement.`,
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
      <ScreenHeader title="Reserver" subtitle={`${propertyName} \u00b7 ${roomName}`} onBack={() => navigation.goBack()} />
      <KeyboardAvoidingView style={styles.flex} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <View style={styles.content}>
          <Section title="Sejour">
            <Card>
              <InfoRow label="Chambre" value={roomName} strong />
              <InfoRow label="Arrivee" value={checkIn} />
              <InfoRow label="Depart" value={checkOut} />
              {errors.dates ? <Text style={styles.error}>{errors.dates}</Text> : null}
            </Card>
          </Section>

          <Section title="Voyageurs">
            <Card>
              <View style={styles.counterRow}>
                <Text style={styles.counterLabel}>Adultes</Text>
                <View style={styles.counter}>
                  <Button label="\u2212" variant="secondary" onPress={() => setAdults((v) => Math.max(1, v - 1))} style={styles.counterButton} />
                  <Text style={styles.counterValue}>{adults}</Text>
                  <Button label="+" variant="secondary" onPress={() => setAdults((v) => Math.min(30, v + 1))} style={styles.counterButton} />
                </View>
              </View>
              <View style={styles.counterRow}>
                <Text style={styles.counterLabel}>Enfants</Text>
                <View style={styles.counter}>
                  <Button label="\u2212" variant="secondary" onPress={() => setChildren((v) => Math.max(0, v - 1))} style={styles.counterButton} />
                  <Text style={styles.counterValue}>{children}</Text>
                  <Button label="+" variant="secondary" onPress={() => setChildren((v) => Math.min(30, v + 1))} style={styles.counterButton} />
                </View>
              </View>
            </Card>
          </Section>

          <Section title="Voyageur principal">
            <TextField label="Nom complet" required value={guestName} onChangeText={setGuestName} placeholder="Nom et prenom" autoCapitalize="words" error={errors.guestName} />
            <TextField label="Email" value={email} onChangeText={setEmail} placeholder="vous@exemple.com" keyboardType="email-address" autoCapitalize="none" error={errors.contact} />
            <TextField label="Telephone" value={phone} onChangeText={setPhone} placeholder="+225 07 00 00 00 00" keyboardType="phone-pad" />
          </Section>

          {failure ? (
            <Card style={styles.failureCard}>
              <Text style={styles.failureTitle}>Reservation non enregistree</Text>
              <Text style={styles.failureText}>{failure.message}</Text>
            </Card>
          ) : null}

          <Button label={submitting ? 'Envoi\u2026' : 'Demander la reservation'} onPress={submit} loading={submitting} style={styles.submit} />
          <Text style={styles.hint}>
            Le montant total est calcule par l'etablissement et vous sera confirme avec la reservation.
          </Text>
        </View>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.background },
  flex: { flex: 1 },
  content: { paddingHorizontal: spacing.lg, paddingBottom: spacing.xxl },
  counterRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingVertical: spacing.sm },
  counterLabel: { color: colors.text, fontSize: typography.body, fontWeight: '600' },
  counter: { flexDirection: 'row', alignItems: 'center', gap: spacing.md },
  counterButton: { minWidth: 44, paddingHorizontal: 0 },
  counterValue: { color: colors.text, fontSize: typography.subheading, fontWeight: '700', minWidth: 20, textAlign: 'center' },
  error: { color: colors.danger, fontSize: typography.caption, marginTop: spacing.xs },
  failureCard: { borderColor: colors.danger, backgroundColor: '#FEF2F2', marginTop: spacing.lg },
  failureTitle: { color: colors.danger, fontSize: typography.subheading, fontWeight: '700' },
  failureText: { color: colors.danger, fontSize: typography.body, marginTop: spacing.xs, lineHeight: 19 },
  submit: { marginTop: spacing.xl },
  hint: { color: colors.textFaint, fontSize: typography.caption, textAlign: 'center', marginTop: spacing.md, lineHeight: 17 },
});
