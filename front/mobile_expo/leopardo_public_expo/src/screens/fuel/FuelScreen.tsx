import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { ScreenHeader } from '../../components/ScreenHeader';
import { Card } from '../../components/Card';
import { colors, spacing, typography } from '../../theme';
import { journeyByCode } from '../../verticals/registry';
import type { ScreenProps } from '../../navigation/types';

/**
 * CARBURANT — verticale sans contrat public.
 *
 * Toutes les routes de `fuel_station.php` sont tenant-scoped derriere
 * `auth:sanctum` + `module.fuel_station`, et aucun provider
 * `public.discoverable` n'existe pour cette verticale. Il n'y a donc AUCUN
 * endpoint appelable depuis une app grand public.
 *
 * Cet ecran ne simule rien : il explique l'etat reel et n'affiche aucun
 * bouton d'action qui echouerait.
 */
export function FuelScreen({ navigation }: ScreenProps<'Fuel'>) {
  const vertical = journeyByCode('fuel');
  const reason = vertical?.blockers[0]?.reason ?? 'Cette verticale n\u2019est pas encore ouverte au public.';

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      <ScreenHeader title="Carburant" onBack={() => navigation.goBack()} />
      <View style={styles.content}>
        <View style={styles.iconCircle}>
          <Text style={styles.iconGlyph}>{vertical?.glyph ?? '\u26FD'}</Text>
        </View>
        <Text style={styles.title}>Bientot disponible</Text>
        <Text style={styles.text}>{reason}</Text>

        <Card style={styles.card}>
          <Text style={styles.cardTitle}>Ce qui existe aujourd\u2019hui</Text>
          <Text style={styles.cardText}>
            Les stations partenaires gerent leurs ventes, leur caisse et leurs equipes depuis
            l\u2019espace professionnel Leopardo. La vitrine grand public (stations proches, prix a la
            pompe) n\u2019a pas encore de contrat public.
          </Text>
        </Card>
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.background },
  content: { flex: 1, padding: spacing.xl, alignItems: 'center', justifyContent: 'center' },
  iconCircle: {
    width: 72,
    height: 72,
    borderRadius: 36,
    backgroundColor: colors.surfaceAlt,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: spacing.lg,
  },
  iconGlyph: { fontSize: 30 },
  title: { color: colors.text, fontSize: typography.title, fontWeight: '800', textAlign: 'center', letterSpacing: -0.5 },
  text: { color: colors.textMuted, fontSize: typography.body, textAlign: 'center', marginTop: spacing.sm, lineHeight: 20 },
  card: { marginTop: spacing.xl, alignSelf: 'stretch' },
  cardTitle: { color: colors.text, fontSize: typography.subheading, fontWeight: '700' },
  cardText: { color: colors.textMuted, fontSize: typography.body, marginTop: spacing.xs, lineHeight: 20 },
});
