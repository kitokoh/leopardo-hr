import React, { useRef, useState } from 'react';
import { KeyboardAvoidingView, Platform, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { ScreenHeader } from '../../components/ScreenHeader';
import { Card, InfoRow, Section } from '../../components/Card';
import { Button } from '../../components/Button';
import { TextField } from '../../components/TextField';
import { colors, spacing, typography } from '../../theme';
import { createRide, estimateRide, newUuidV4 } from '../../api/public';
import { classifyFailure, type PublicApiFailure } from '../../api/client';
import { formatMoney } from '../../utils/format';
import type { ScreenProps } from '../../navigation/types';

/**
 * COMMANDE D'UNE COURSE VTC — `POST /public/vtc/marketplace/rides`.
 *
 * ## Deux points de contrat non negociables
 *
 * 1. L'en-tete `Idempotency-Key` doit etre un **UUID v4** : le serveur valide
 *    `uuid`, une cle maison serait refusee en 422. On utilise donc
 *    `newUuidV4()`, pas `newIdempotencyKey()`.
 * 2. Les coordonnees sont OBLIGATOIRES (`pickup.lat/lng`, `dropoff.lat/lng`).
 *    Sans geolocalisation, l'app demande la saisie manuelle plutot que
 *    d'envoyer des coordonnees inventees.
 *
 * ## Le prix peut etre inconnu
 *
 * `price_minor` vaut `null` quand l'operateur n'a pas de grille : l'ecran
 * affiche « prix a confirmer », jamais 0.
 */
export function VtcRideScreen({ navigation, route }: ScreenProps<'VtcRide'>) {
  const { operatorSlug, operatorName } = route.params;
  const [pickupLat, setPickupLat] = useState('');
  const [pickupLng, setPickupLng] = useState('');
  const [dropoffLat, setDropoffLat] = useState('');
  const [dropoffLng, setDropoffLng] = useState('');
  const [pickupAddress, setPickupAddress] = useState('');
  const [dropoffAddress, setDropoffAddress] = useState('');
  const [passengerName, setPassengerName] = useState('');
  const [passengerPhone, setPassengerPhone] = useState('');
  const [estimate, setEstimate] = useState<{ price: number | null; currency: string | null } | null>(null);
  const [estimating, setEstimating] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [failure, setFailure] = useState<PublicApiFailure | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});

  const idempotencyKey = useRef(newUuidV4());

  const coords = () => {
    const pLat = Number(pickupLat.replace(',', '.'));
    const pLng = Number(pickupLng.replace(',', '.'));
    const dLat = Number(dropoffLat.replace(',', '.'));
    const dLng = Number(dropoffLng.replace(',', '.'));
    const valid =
      Number.isFinite(pLat) &&
      Number.isFinite(pLng) &&
      Number.isFinite(dLat) &&
      Number.isFinite(dLng) &&
      Math.abs(pLat) <= 90 &&
      Math.abs(dLat) <= 90 &&
      Math.abs(pLng) <= 180 &&
      Math.abs(dLng) <= 180;
    return valid ? { pLat, pLng, dLat, dLng } : null;
  };

  const runEstimate = async () => {
    const c = coords();
    if (!c) {
      setErrors({ coords: 'Renseignez des coordonnees valides (latitude entre -90 et 90).' });
      return;
    }
    setErrors({});
    setEstimating(true);
    setFailure(null);
    try {
      const result = await estimateRide({
        operator: operatorSlug,
        pickup: { lat: c.pLat, lng: c.pLng },
        dropoff: { lat: c.dLat, lng: c.dLng },
      });
      setEstimate({ price: result.price_minor, currency: result.currency });
    } catch (error) {
      setFailure(classifyFailure(error));
    } finally {
      setEstimating(false);
    }
  };

  const submit = async () => {
    const next: Record<string, string> = {};
    const c = coords();
    if (!c) next.coords = 'Renseignez des coordonnees valides.';
    if (passengerName.trim().length < 2) next.name = 'Indiquez votre nom.';
    if (passengerPhone.trim().length < 6) next.phone = 'Indiquez un numero joignable.';
    setErrors(next);
    if (Object.keys(next).length > 0 || !c) return;

    setSubmitting(true);
    setFailure(null);
    try {
      const result = await createRide({
        operator: operatorSlug,
        pickup: { lat: c.pLat, lng: c.pLng },
        dropoff: { lat: c.dLat, lng: c.dLng },
        pickupAddress: pickupAddress.trim() || undefined,
        dropoffAddress: dropoffAddress.trim() || undefined,
        passengerName: passengerName.trim(),
        passengerPhone: passengerPhone.trim(),
        idempotencyKey: idempotencyKey.current,
      });
      navigation.replace('Confirmation', {
        title: 'Course demandee',
        reference: result.reference,
        code: result.tracking_code,
        message: `${operatorName} recherche un chauffeur. Suivez votre course avec votre reference.`,
        totalMinor: result.estimated_price_minor,
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
      <ScreenHeader title="Commander une course" subtitle={operatorName} onBack={() => navigation.goBack()} />
      <KeyboardAvoidingView style={styles.flex} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <View style={styles.content}>
          <Section title="Point de depart">
            <TextField label="Adresse" value={pickupAddress} onChangeText={setPickupAddress} placeholder="Ou etes-vous ?" />
            <View style={styles.coordRow}>
              <TextField label="Latitude" value={pickupLat} onChangeText={setPickupLat} placeholder="5.359" keyboardType="numbers-and-punctuation" style={styles.coordInput} />
              <TextField label="Longitude" value={pickupLng} onChangeText={setPickupLng} placeholder="-4.008" keyboardType="numbers-and-punctuation" style={styles.coordInput} />
            </View>
          </Section>

          <Section title="Destination">
            <TextField label="Adresse" value={dropoffAddress} onChangeText={setDropoffAddress} placeholder="Ou allez-vous ?" />
            <View style={styles.coordRow}>
              <TextField label="Latitude" value={dropoffLat} onChangeText={setDropoffLat} placeholder="5.320" keyboardType="numbers-and-punctuation" style={styles.coordInput} />
              <TextField label="Longitude" value={dropoffLng} onChangeText={setDropoffLng} placeholder="-4.020" keyboardType="numbers-and-punctuation" style={styles.coordInput} />
            </View>
            {errors.coords ? <Text style={styles.error}>{errors.coords}</Text> : null}
            <Text style={styles.hint}>
              Les coordonnees sont necessaires pour calculer le tarif et envoyer un chauffeur.
            </Text>
          </Section>

          <Button label={estimating ? 'Estimation\u2026' : 'Estimer le prix'} variant="secondary" onPress={runEstimate} loading={estimating} style={styles.estimateButton} />

          {estimate ? (
            <Card style={styles.estimateCard}>
              <InfoRow
                label="Prix estime"
                value={
                  estimate.price !== null
                    ? formatMoney(estimate.price, estimate.currency)
                    : 'Prix a confirmer par l\u2019operateur'
                }
                strong
              />
            </Card>
          ) : null}

          <Section title="Passager">
            <TextField label="Nom" required value={passengerName} onChangeText={setPassengerName} placeholder="Votre nom" autoCapitalize="words" error={errors.name} />
            <TextField label="Telephone" required value={passengerPhone} onChangeText={setPassengerPhone} placeholder="+225 07 00 00 00 00" keyboardType="phone-pad" error={errors.phone} />
          </Section>

          {failure ? (
            <Card style={styles.failureCard}>
              <Text style={styles.failureTitle}>Course non demandee</Text>
              <Text style={styles.failureText}>{failure.message}</Text>
            </Card>
          ) : null}

          <Button label={submitting ? 'Envoi\u2026' : 'Commander la course'} onPress={submit} loading={submitting} style={styles.submit} />
        </View>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.background },
  flex: { flex: 1 },
  content: { paddingHorizontal: spacing.lg, paddingBottom: spacing.xxl },
  coordRow: { flexDirection: 'row', gap: spacing.md },
  coordInput: { flex: 1 },
  error: { color: colors.danger, fontSize: typography.caption, marginTop: spacing.xs },
  hint: { color: colors.textFaint, fontSize: typography.caption, marginTop: spacing.sm, lineHeight: 16 },
  estimateButton: { marginTop: spacing.lg },
  estimateCard: { marginTop: spacing.md },
  failureCard: { borderColor: colors.danger, backgroundColor: '#FEF2F2', marginTop: spacing.lg },
  failureTitle: { color: colors.danger, fontSize: typography.subheading, fontWeight: '700' },
  failureText: { color: colors.danger, fontSize: typography.body, marginTop: spacing.xs, lineHeight: 19 },
  submit: { marginTop: spacing.xl },
});
