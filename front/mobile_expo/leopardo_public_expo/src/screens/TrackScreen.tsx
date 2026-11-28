import React, { useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { ScreenHeader } from '../components/ScreenHeader';
import { Card, InfoRow, Section } from '../components/Card';
import { Button } from '../components/Button';
import { TextField } from '../components/TextField';
import { colors, radius, spacing, typography } from '../theme';
import {
  trackMarketOrder,
  trackRestaurantOrder,
  trackRide,
  trackStayReservation,
  type TrackedOrder,
} from '../api/public';
import { classifyFailure, type PublicApiFailure } from '../api/client';
import { formatMoney, statusLabel } from '../utils/format';
import { statusColor } from '../theme';
import type { ScreenProps } from '../navigation/types';

/**
 * SUIVI SANS COMPTE — reference + code.
 *
 * Chaque verticale a son propre contrat de suivi, avec son propre nom de
 * parametre (`secret`, `code`, `token`). L'utilisateur choisit la verticale ;
 * l'app envoie le bon parametre. Envoyer `code` a un endpoint qui attend
 * `secret` renverrait 404 et ferait croire que la commande n'existe pas.
 */
type TrackKind = 'restaurant' | 'stay' | 'ride' | 'market';

const KINDS: { key: TrackKind; label: string; hint: string }[] = [
  { key: 'restaurant', label: 'Restaurant', hint: 'Slug du restaurant + reference + secret' },
  { key: 'stay', label: 'Sejour', hint: 'Reference + code de reservation' },
  { key: 'ride', label: 'VTC', hint: 'Reference + code de course' },
  { key: 'market', label: 'Boutique', hint: 'Reference + token de suivi' },
];

export function TrackScreen({ navigation }: ScreenProps<'Track'>) {
  const [kind, setKind] = useState<TrackKind>('restaurant');
  const [slug, setSlug] = useState('');
  const [reference, setReference] = useState('');
  const [code, setCode] = useState('');
  const [result, setResult] = useState<TrackedOrder | null>(null);
  const [failure, setFailure] = useState<PublicApiFailure | null>(null);
  const [loading, setLoading] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});

  const submit = async () => {
    const next: Record<string, string> = {};
    if (reference.trim().length < 3) next.reference = 'Indiquez la reference recue.';
    if (code.trim().length < 3) next.code = 'Indiquez le code recu.';
    if (kind === 'restaurant' && slug.trim().length < 2) next.slug = 'Indiquez le nom du restaurant.';
    setErrors(next);
    if (Object.keys(next).length > 0) return;

    setLoading(true);
    setFailure(null);
    setResult(null);
    try {
      let tracked: TrackedOrder;
      if (kind === 'restaurant') {
        tracked = await trackRestaurantOrder({ slug: slug.trim(), reference: reference.trim(), secret: code.trim() });
      } else if (kind === 'stay') {
        tracked = await trackStayReservation({ reference: reference.trim(), code: code.trim() });
      } else if (kind === 'ride') {
        tracked = await trackRide({ reference: reference.trim(), code: code.trim() });
      } else {
        tracked = await trackMarketOrder({ reference: reference.trim(), token: code.trim() });
      }
      setResult(tracked);
    } catch (error) {
      setFailure(classifyFailure(error));
    } finally {
      setLoading(false);
    }
  };

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      <ScreenHeader title="Suivre" subtitle="Sans compte, avec votre reference" onBack={() => navigation.goBack()} />
      <View style={styles.content}>
        <View style={styles.kindRow}>
          {KINDS.map((item) => {
            const active = kind === item.key;
            return (
              <Pressable
                key={item.key}
                accessibilityRole="button"
                accessibilityState={{ selected: active }}
                onPress={() => {
                  setKind(item.key);
                  setResult(null);
                  setFailure(null);
                }}
                style={[styles.kindChip, active && styles.kindChipActive]}
              >
                <Text style={[styles.kindText, active && styles.kindTextActive]}>{item.label}</Text>
              </Pressable>
            );
          })}
        </View>

        <Text style={styles.hint}>{KINDS.find((k) => k.key === kind)?.hint}</Text>

        {kind === 'restaurant' ? (
          <TextField label="Restaurant (slug)" required value={slug} onChangeText={setSlug} placeholder="ex. le-baobab" autoCapitalize="none" autoCorrect={false} error={errors.slug} />
        ) : null}
        <TextField label="Reference" required value={reference} onChangeText={setReference} placeholder="ex. RST-2026-0001" autoCapitalize="characters" autoCorrect={false} error={errors.reference} />
        <TextField label="Code" required value={code} onChangeText={setCode} placeholder="Code recu a la creation" autoCapitalize="none" autoCorrect={false} error={errors.code} />

        <Button label={loading ? 'Recherche\u2026' : 'Suivre'} onPress={submit} loading={loading} style={styles.submit} />

        {failure ? (
          <Card style={styles.failureCard}>
            <Text style={styles.failureTitle}>
              {failure.kind === 'notFound' ? 'Aucune demande trouvee' : 'Suivi impossible'}
            </Text>
            <Text style={styles.failureText}>
              {failure.kind === 'notFound'
                ? 'Verifiez la reference et le code : ils doivent correspondre exactement.'
                : failure.message}
            </Text>
          </Card>
        ) : null}

        {result ? (
          <Section title="Etat de votre demande">
            <Card>
              <InfoRow label="Reference" value={result.reference} strong />
              <InfoRow label="Statut" value={statusLabel(result.status)} strong />
              {result.detail ? <InfoRow label="Detail" value={result.detail} /> : null}
              {result.total_minor !== null ? (
                <InfoRow label="Montant" value={formatMoney(result.total_minor, result.currency)} />
              ) : null}
              <View style={[styles.statusDot, { backgroundColor: statusColor(result.status) }]} />
            </Card>
          </Section>
        ) : null}
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.background },
  content: { paddingHorizontal: spacing.lg, paddingBottom: spacing.xxl },
  kindRow: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm, marginBottom: spacing.md },
  kindChip: {
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius.pill,
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.sm,
  },
  kindChipActive: { backgroundColor: colors.primary, borderColor: colors.primary },
  kindText: { color: colors.textMuted, fontSize: typography.body, fontWeight: '600' },
  kindTextActive: { color: colors.white },
  hint: { color: colors.textFaint, fontSize: typography.caption, marginBottom: spacing.lg, lineHeight: 16 },
  submit: { marginTop: spacing.sm },
  failureCard: { borderColor: colors.danger, backgroundColor: '#FEF2F2', marginTop: spacing.lg },
  failureTitle: { color: colors.danger, fontSize: typography.subheading, fontWeight: '700' },
  failureText: { color: colors.danger, fontSize: typography.body, marginTop: spacing.xs, lineHeight: 19 },
  statusDot: { width: 10, height: 10, borderRadius: 5, marginTop: spacing.md },
});
