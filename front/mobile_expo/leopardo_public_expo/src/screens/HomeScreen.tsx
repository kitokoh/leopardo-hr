import React from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { colors, radius, shadow, spacing, typography } from '../theme';
import { JOURNEY_VERTICALS, hasNativeJourney } from '../verticals/registry';
import type { ScreenProps } from '../navigation/types';

/**
 * ACCUEIL — la grille des 9 verticales.
 *
 * Chaque tuile mene a un parcours REEL. Une verticale sans contrat public
 * (`mode: 'blocked'`) reste visible mais annonce son etat : la masquer ferait
 * croire que Leopardo ne la couvre pas, l'afficher sans le dire produirait un
 * bouton mort. Elle ouvre donc un ecran qui explique pourquoi.
 */
export function HomeScreen({ navigation }: ScreenProps<'Home'>) {
  const open = (code: string) => {
    switch (code) {
      case 'restaurant':
        navigation.navigate('Explore', { type: 'restaurant' });
        break;
      case 'commerce':
        navigation.navigate('Sellers');
        break;
      case 'hospitality':
        navigation.navigate('Explore', { type: 'hospitality_property' });
        break;
      case 'travel':
        navigation.navigate('Travel');
        break;
      case 'mobility':
        navigation.navigate('Vtc');
        break;
      case 'health':
        navigation.navigate('Pharmacy');
        break;
      case 'trade':
        navigation.navigate('Explore', { type: 'trade_company' });
        break;
      case 'education':
        navigation.navigate('Education');
        break;
      case 'fuel':
        navigation.navigate('Fuel');
        break;
      default:
        navigation.navigate('Explore', undefined);
    }
  };

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.hero}>
          <Text style={styles.brand}>Leopardo</Text>
          <Text style={styles.heroTitle}>Tout pres de chez vous</Text>
          <Text style={styles.heroSub}>
            Commandez, reservez, voyagez \u2014 sans creer de compte.
          </Text>
        </View>

        <Pressable
          accessibilityRole="button"
          accessibilityLabel="Explorer tous les etablissements"
          onPress={() => navigation.navigate('Explore', undefined)}
          style={({ pressed }) => [styles.searchBar, pressed && styles.pressed]}
        >
          <Text style={styles.searchGlyph}>\u2315</Text>
          <Text style={styles.searchText}>Explorer les etablissements</Text>
        </Pressable>

        <Text style={styles.sectionTitle}>Nos services</Text>

        <View style={styles.grid}>
          {JOURNEY_VERTICALS.map((vertical) => {
            const blocked = vertical.mode === 'blocked';
            return (
              <Pressable
                key={vertical.code}
                accessibilityRole="button"
                accessibilityLabel={
                  blocked
                    ? `${vertical.label}, bientot disponible`
                    : `${vertical.label}. ${vertical.tagline}`
                }
                onPress={() => open(vertical.code)}
                style={({ pressed }) => [styles.tile, pressed && styles.pressed]}
              >
                <View style={[styles.tileIcon, { backgroundColor: `${vertical.accent}18` }]}>
                  <Text style={[styles.tileGlyph, { color: vertical.accent }]}>{vertical.glyph}</Text>
                </View>
                <Text style={styles.tileLabel} numberOfLines={1}>
                  {vertical.label}
                </Text>
                <Text style={styles.tileTagline} numberOfLines={2}>
                  {vertical.tagline}
                </Text>
                {blocked ? (
                  <View style={styles.soonPill}>
                    <Text style={styles.soonText}>Bientot</Text>
                  </View>
                ) : hasNativeJourney(vertical) ? (
                  <View style={[styles.soonPill, styles.livePill]}>
                    <Text style={[styles.soonText, styles.liveText]}>Disponible</Text>
                  </View>
                ) : null}
              </Pressable>
            );
          })}
        </View>

        <Pressable
          accessibilityRole="button"
          accessibilityLabel="Suivre une commande ou une reservation"
          onPress={() => navigation.navigate('Track')}
          style={({ pressed }) => [styles.trackCard, pressed && styles.pressed]}
        >
          <View style={styles.trackTexts}>
            <Text style={styles.trackTitle}>Suivre une commande</Text>
            <Text style={styles.trackSub}>Avec votre reference et votre code</Text>
          </View>
          <Text style={styles.trackChevron}>\u203A</Text>
        </Pressable>
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.background },
  content: { paddingHorizontal: spacing.lg, paddingBottom: spacing.xxl },
  hero: { paddingTop: spacing.lg, paddingBottom: spacing.xl },
  brand: {
    color: colors.primary,
    fontSize: typography.caption,
    fontWeight: '800',
    letterSpacing: 2,
    textTransform: 'uppercase',
  },
  heroTitle: {
    color: colors.text,
    fontSize: typography.display,
    fontWeight: '800',
    letterSpacing: -1,
    marginTop: spacing.sm,
  },
  heroSub: {
    color: colors.textMuted,
    fontSize: typography.subheading,
    marginTop: spacing.sm,
    lineHeight: 22,
  },
  searchBar: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    backgroundColor: colors.surface,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.border,
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.lg,
    ...shadow,
  },
  searchGlyph: { fontSize: 18, color: colors.textFaint },
  searchText: { color: colors.textFaint, fontSize: typography.subheading },
  pressed: { opacity: 0.85 },
  sectionTitle: {
    color: colors.text,
    fontSize: typography.heading,
    fontWeight: '700',
    marginTop: spacing.xl,
    marginBottom: spacing.md,
    letterSpacing: -0.3,
  },
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.md },
  tile: {
    width: '47.5%',
    flexGrow: 1,
    backgroundColor: colors.surface,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.border,
    padding: spacing.lg,
    minHeight: 148,
  },
  tileIcon: {
    width: 40,
    height: 40,
    borderRadius: radius.md,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: spacing.md,
  },
  tileGlyph: { fontSize: 18, fontWeight: '700' },
  tileLabel: { color: colors.text, fontSize: typography.subheading, fontWeight: '700' },
  tileTagline: {
    color: colors.textMuted,
    fontSize: typography.caption,
    marginTop: 2,
    lineHeight: 16,
  },
  soonPill: {
    alignSelf: 'flex-start',
    marginTop: spacing.sm,
    backgroundColor: colors.surfaceAlt,
    borderRadius: radius.pill,
    paddingHorizontal: spacing.sm,
    paddingVertical: 2,
  },
  livePill: { backgroundColor: colors.accentSoft },
  soonText: { color: colors.textFaint, fontSize: 10, fontWeight: '700' },
  liveText: { color: colors.accent },
  trackCard: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.primarySoft,
    borderRadius: radius.lg,
    padding: spacing.lg,
    marginTop: spacing.xl,
  },
  trackTexts: { flex: 1 },
  trackTitle: { color: colors.primaryDark, fontSize: typography.subheading, fontWeight: '700' },
  trackSub: { color: colors.primary, fontSize: typography.caption, marginTop: 2 },
  trackChevron: { fontSize: 26, color: colors.primary, fontWeight: '300' },
});
