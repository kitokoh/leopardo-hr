import React, { useMemo } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useAuth } from '../../api/AuthContext';
import { Badge, Card } from '../../components/Card';
import { ScreenHeader } from '../../components/ScreenHeader';
import { colors, radius, spacing, typography } from '../../theme';
import { getClientModuleAccess } from '../../verticals/access';
import { VERTICALS } from '../../verticals/catalog';
import type { ClientModuleAccess, ClientModuleKey } from '../../verticals/types';

type Props = {
  onOpenModule: (key: ClientModuleKey) => void;
};

/**
 * « MODULES & PLAN » — equivalent mobile du panneau web.
 *
 * Le web cache ce panneau hors de la barre : il liste TOUS les modules du
 * catalogue avec leur etat (actif / essai / verrouille) et la raison du
 * verrouillage. Le mobile fait de meme, en distinguant :
 *  - les modules ACTIFS (ouvrables) ;
 *  - les modules VERROUILLES (feature non activee ou role insuffisant).
 *
 * C'est ce qui rend l'architecture des verticaux lisible : un utilisateur
 * comprend pourquoi une verticale n'apparait pas dans « Mon metier ».
 */
export function ModulesPlanScreen({ onOpenModule }: Props) {
  const { employee } = useAuth();

  const all = useMemo(() => getClientModuleAccess(employee), [employee]);
  const enabled = all.filter((module) => module.enabled);
  const locked = all.filter((module) => !module.enabled);

  const activeVerticals = useMemo(() => {
    const keys = new Set(
      enabled
        .map((module) => module.vertical)
        .filter((vertical): vertical is NonNullable<typeof vertical> => Boolean(vertical)),
    );
    return VERTICALS.filter((vertical) => keys.has(vertical.key));
  }, [enabled]);

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      <ScreenHeader
        title="Modules & plan"
        subtitle={`${enabled.length} actifs \u00b7 ${locked.length} verrouilles`}
      />

      <ScrollView contentContainerStyle={styles.content}>
        <Card style={styles.summaryCard}>
          <View style={styles.summaryRow}>
            <View style={styles.summaryItem}>
              <Text style={styles.summaryValue}>{activeVerticals.length}</Text>
              <Text style={styles.summaryLabel}>Verticales</Text>
            </View>
            <View style={styles.summaryDivider} />
            <View style={styles.summaryItem}>
              <Text style={styles.summaryValue}>{enabled.length}</Text>
              <Text style={styles.summaryLabel}>Modules actifs</Text>
            </View>
            <View style={styles.summaryDivider} />
            <View style={styles.summaryItem}>
              <Text style={styles.summaryValue}>{locked.length}</Text>
              <Text style={styles.summaryLabel}>Verrouilles</Text>
            </View>
          </View>
        </Card>

        <Text style={styles.sectionTitle}>Modules actifs</Text>
        <Card style={styles.listCard}>
          {enabled.map((module, index) => (
            <ModuleLine
              key={module.key}
              module={module}
              isFirst={index === 0}
              onPress={() => onOpenModule(module.key)}
            />
          ))}
        </Card>

        {locked.length > 0 ? (
          <>
            <Text style={styles.sectionTitle}>Modules verrouilles</Text>
            <Card style={styles.listCard}>
              {locked.map((module, index) => (
                <ModuleLine key={module.key} module={module} isFirst={index === 0} />
              ))}
            </Card>
            <Text style={styles.hint}>
              Un module verrouille n\u2019est pas active sur votre espace, ou votre role ne
              permet pas d\u2019y acceder. Le responsable de l\u2019entreprise peut l\u2019activer
              depuis le web (Modules & plan).
            </Text>
          </>
        ) : null}
      </ScrollView>
    </SafeAreaView>
  );
}

function ModuleLine({
  module,
  isFirst,
  onPress,
}: {
  module: ClientModuleAccess;
  isFirst: boolean;
  onPress?: () => void;
}) {
  const reasonLabel =
    module.reason === 'role_locked'
      ? 'role insuffisant'
      : module.reason === 'feature_locked'
        ? 'non active'
        : module.reason === 'trial'
          ? 'essai'
          : 'actif';

  const reasonColor =
    module.reason === 'available'
      ? colors.success
      : module.reason === 'trial'
        ? colors.warning
        : colors.textFaint;

  const content = (
    <View style={[styles.line, !isFirst && styles.lineBorder]}>
      <View style={styles.lineTexts}>
        <Text style={[styles.lineLabel, !module.enabled && styles.lineLabelLocked]}>
          {module.label}
        </Text>
        <Text style={styles.lineMeta} numberOfLines={1}>
          {module.vertical ? `${module.vertical} \u00b7 ` : ''}
          {module.group}
        </Text>
      </View>
      <Badge label={reasonLabel} color={reasonColor} />
      {module.enabled ? <Text style={styles.lineChevron}>{'\u203a'}</Text> : null}
    </View>
  );

  if (!module.enabled || !onPress) {
    return content;
  }

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={module.label}
      onPress={onPress}
      style={({ pressed }) => pressed && styles.pressed}
    >
      {content}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.background },
  content: { paddingHorizontal: spacing.lg, paddingBottom: spacing.xxl },
  summaryCard: { backgroundColor: colors.surfaceAlt, marginBottom: spacing.lg },
  summaryRow: { flexDirection: 'row', alignItems: 'center' },
  summaryItem: { flex: 1, alignItems: 'center' },
  summaryValue: { color: colors.text, fontSize: 26, fontWeight: '800', letterSpacing: -1 },
  summaryLabel: {
    color: colors.textMuted,
    fontSize: typography.caption,
    marginTop: 2,
    textAlign: 'center',
  },
  summaryDivider: { width: 1, height: 34, backgroundColor: colors.border },
  sectionTitle: {
    color: colors.text,
    fontSize: typography.subheading,
    fontWeight: '700',
    marginTop: spacing.lg,
    marginBottom: spacing.md,
  },
  listCard: { padding: 0 },
  line: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.md,
    gap: spacing.sm,
  },
  lineBorder: { borderTopWidth: 1, borderTopColor: colors.border },
  lineTexts: { flex: 1 },
  lineLabel: { color: colors.text, fontSize: typography.body, fontWeight: '600' },
  lineLabelLocked: { color: colors.textMuted },
  lineMeta: { color: colors.textFaint, fontSize: typography.caption, marginTop: 2 },
  lineChevron: { color: colors.textFaint, fontSize: 18 },
  pressed: { opacity: 0.7 },
  hint: {
    color: colors.textFaint,
    fontSize: typography.caption,
    lineHeight: 18,
    marginTop: spacing.md,
  },
});
