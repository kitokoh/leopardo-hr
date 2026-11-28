import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Button } from '../components/Button';
import { Card, InfoRow } from '../components/Card';
import { colors, radius, spacing, typography } from '../theme';
import { formatMoney } from '../utils/format';
import type { ScreenProps } from '../navigation/types';

/**
 * CONFIRMATION — ecran terminal d'un parcours.
 *
 * Le code de suivi n'est affiche QUE s'il a ete renvoye par le serveur a la
 * creation (present-once). S'il est absent, l'ecran ne l'invente pas : il
 * indique que la reference suffit.
 */
export function ConfirmationScreen({ navigation, route }: ScreenProps<'Confirmation'>) {
  const { title, reference, code, message, totalMinor, currency } = route.params;

  return (
    <SafeAreaView style={styles.safe} edges={['top', 'bottom']}>
      <View style={styles.content}>
        <View style={styles.badge}>
          <Text style={styles.badgeGlyph}>\u2713</Text>
        </View>
        <Text style={styles.title}>{title}</Text>
        <Text style={styles.message}>{message}</Text>

        <Card style={styles.card}>
          <InfoRow label="Reference" value={reference} strong />
          {code ? <InfoRow label="Code de suivi" value={code} strong /> : null}
          {totalMinor !== undefined && totalMinor !== null ? (
            <InfoRow label="Montant" value={formatMoney(totalMinor, currency)} strong />
          ) : null}
        </Card>

        <Text style={styles.hint}>
          Conservez ces informations : elles vous permettent de suivre votre demande sans compte.
        </Text>

        <View style={styles.actions}>
          <Button label="Suivre ma demande" onPress={() => navigation.navigate('Track')} />
          <Button
            label="Retour a l'accueil"
            variant="secondary"
            onPress={() => navigation.popToTop()}
          />
        </View>
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.background },
  content: { flex: 1, padding: spacing.xl, justifyContent: 'center' },
  badge: {
    width: 64,
    height: 64,
    borderRadius: 32,
    backgroundColor: colors.accentSoft,
    alignItems: 'center',
    justifyContent: 'center',
    alignSelf: 'center',
    marginBottom: spacing.lg,
  },
  badgeGlyph: { fontSize: 30, color: colors.accent, fontWeight: '700' },
  title: {
    color: colors.text,
    fontSize: typography.title,
    fontWeight: '800',
    textAlign: 'center',
    letterSpacing: -0.5,
  },
  message: {
    color: colors.textMuted,
    fontSize: typography.body,
    textAlign: 'center',
    marginTop: spacing.sm,
    lineHeight: 20,
  },
  card: { marginTop: spacing.xl, borderRadius: radius.lg },
  hint: {
    color: colors.textFaint,
    fontSize: typography.caption,
    textAlign: 'center',
    marginTop: spacing.lg,
    lineHeight: 17,
  },
  actions: { gap: spacing.md, marginTop: spacing.xl },
});
