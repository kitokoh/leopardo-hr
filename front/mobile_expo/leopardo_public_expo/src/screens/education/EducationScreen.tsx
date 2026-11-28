import React, { useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { ScreenHeader } from '../../components/ScreenHeader';
import { Card, Section } from '../../components/Card';
import { Button } from '../../components/Button';
import { TextField } from '../../components/TextField';
import { colors, spacing, typography } from '../../theme';
import { blockerFor, journeyByCode } from '../../verticals/registry';
import type { ScreenProps } from '../../navigation/types';

/**
 * SCOLARITE — portail a jeton.
 *
 * `GET /edu-manager/portal/{token}` est public mais exige le JETON remis par
 * l'etablissement : il n'y a ni annuaire public ni inscription libre, et
 * c'est voulu (donnees de mineurs). L'app propose donc la saisie du lien recu.
 *
 * ## Ce que cet ecran ne fait pas
 *
 * Il n'appelle PAS le portail : le jeton est un secret a usage unique, et le
 * parcours complet (consultation des notes, absences, paiements) est servi par
 * la page web du portail. L'app oriente vers ce lien plutot que de dupliquer
 * un contrat qui n'est pas concu pour un client mobile.
 */
export function EducationScreen({ navigation }: ScreenProps<'Education'>) {
  const [token, setToken] = useState('');
  const [error, setError] = useState<string | null>(null);
  const catalogBlocker = blockerFor(journeyByCode('education')!, 'catalog');

  const openPortal = () => {
    const cleaned = token.trim();
    if (cleaned.length < 8) {
      setError('Le lien recu par l\u2019ecole contient un code d\u2019au moins 8 caracteres.');
      return;
    }
    setError(null);
    // Le portail est une page web servie par l'API : on ne peut pas la rendre
    // dans l'app sans dupliquer son contrat. On indique donc l'adresse exacte.
    navigation.navigate('Confirmation', {
      title: 'Portail scolarite',
      reference: cleaned,
      message:
        'Ouvrez le lien transmis par l\u2019etablissement dans votre navigateur pour consulter la scolarite. Ce code vous identifie, ne le partagez pas.',
    });
  };

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      <ScreenHeader title="Scolarite" subtitle="Portail des parents" onBack={() => navigation.goBack()} />
      <View style={styles.content}>
        <Card style={styles.hero}>
          <Text style={styles.heroTitle}>Suivre la scolarite de votre enfant</Text>
          <Text style={styles.heroText}>
            Notes, absences, emploi du temps et paiements : l\u2019acces se fait avec le lien
            personnel transmis par l\u2019ecole.
          </Text>
        </Card>

        {catalogBlocker ? (
          <Card style={styles.blockerCard}>
            <Text style={styles.blockerTitle}>Pas d\u2019annuaire public</Text>
            <Text style={styles.blockerText}>{catalogBlocker}</Text>
          </Card>
        ) : null}

        <Section title="J\u2019ai recu un lien">
          <TextField
            label="Code du portail"
            required
            value={token}
            onChangeText={setToken}
            placeholder="Collez le code recu"
            autoCapitalize="none"
            autoCorrect={false}
            error={error}
          />
          <Button label="Ouvrir mon portail" onPress={openPortal} />
        </Section>

        <Section title="Je n\u2019ai pas de lien">
          <Card>
            <Text style={styles.helpText}>
              Demandez le lien a l\u2019administration de l\u2019etablissement. Il est personnel et
              donne acces aux donnees de votre enfant.
            </Text>
          </Card>
        </Section>
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.background },
  content: { paddingHorizontal: spacing.lg, paddingBottom: spacing.xxl },
  hero: { gap: spacing.sm },
  heroTitle: { color: colors.text, fontSize: typography.heading, fontWeight: '700', letterSpacing: -0.3 },
  heroText: { color: colors.textMuted, fontSize: typography.body, lineHeight: 20 },
  blockerCard: { marginTop: spacing.md, backgroundColor: colors.surfaceAlt, borderColor: colors.border },
  blockerTitle: { color: colors.text, fontSize: typography.subheading, fontWeight: '700' },
  blockerText: { color: colors.textMuted, fontSize: typography.body, marginTop: spacing.xs, lineHeight: 19 },
  helpText: { color: colors.textMuted, fontSize: typography.body, lineHeight: 20 },
});
