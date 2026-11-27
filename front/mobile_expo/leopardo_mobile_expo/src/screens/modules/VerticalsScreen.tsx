import React, { useCallback, useMemo, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useAuth } from '../../api/AuthContext';
import { Badge, Card, EmptyState } from '../../components/Card';
import { colors, radius, spacing, typography } from '../../theme';
import { VERTICALS } from '../../verticals/catalog';
import {
  buildBusinessRail,
  getBusinessModules,
  getCoreModules,
  getModulesByGroup,
} from '../../verticals/access';
import type { ClientModuleAccess, ClientModuleKey, VerticalDescriptor } from '../../verticals/types';

type Props = {
  onOpenModule: (key: ClientModuleKey) => void;
};

/**
 * HUB « MON METIER » — le rail des verticales.
 *
 * Reproduit la structure du web : les modules metier (`scope: 'business'`)
 * sont regroupes par verticale, les sous-ecrans (`parentKey`) sont rendus
 * SOUS leur carte parente, et les modules transverses restent accessibles
 * dans une section « Socle ».
 *
 * Un tenant qui n'a aucune verticale active voit un etat vide explicite,
 * pas un ecran casse.
 */
export function VerticalsScreen({ onOpenModule }: Props) {
  const { employee } = useAuth();
  const [expanded, setExpanded] = useState<string | null>(null);

  const businessModules = useMemo(() => getBusinessModules(employee), [employee]);
  const coreModules = useMemo(() => getCoreModules(employee), [employee]);
  const rail = useMemo(() => buildBusinessRail(businessModules), [businessModules]);

  // Verticales actives, dans l'ordre du catalogue.
  const activeVerticals = useMemo(() => {
    const activeKeys = new Set(
      businessModules
        .map((module) => module.vertical)
        .filter((vertical): vertical is NonNullable<typeof vertical> => Boolean(vertical)),
    );
    return VERTICALS.filter((vertical) => activeKeys.has(vertical.key));
  }, [businessModules]);

  const toggle = useCallback((key: string) => {
    setExpanded((current) => (current === key ? null : key));
  }, []);

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      <View style={styles.header}>
        <Text style={styles.title}>Mon metier</Text>
        <Text style={styles.subtitle}>
          {activeVerticals.length > 0
            ? `${activeVerticals.length} verticale${activeVerticals.length > 1 ? 's' : ''} active${activeVerticals.length > 1 ? 's' : ''}`
            : 'Aucune verticale active'}
        </Text>
      </View>

      <ScrollView contentContainerStyle={styles.content}>
        {activeVerticals.length === 0 ? (
          <EmptyState
            title="Aucune verticale active"
            message="Les modules metier (restaurant, voyage, sante, commerce…) apparaissent ici des qu'une solution est activee sur votre espace."
          />
        ) : (
          activeVerticals.map((vertical) => {
            const entries = rail.filter((entry) => entry.module.vertical === vertical.key);
            if (entries.length === 0) return null;
            return (
              <VerticalBlock
                key={vertical.key}
                vertical={vertical}
                entries={entries}
                expanded={expanded === vertical.key}
                onToggle={() => toggle(vertical.key)}
                onOpenModule={onOpenModule}
              />
            );
          })
        )}

        {/* Socle transverse */}
        <Text style={styles.sectionTitle}>Socle</Text>
        <View style={styles.coreGrid}>
          {coreModules
            .filter((module) => module.group !== 'platform')
            .map((module) => (
              <CoreTile
                key={module.key}
                module={module}
                onPress={() => onOpenModule(module.key)}
              />
            ))}
        </View>

        {getModulesByGroup(employee, 'platform').length > 0 ? (
          <>
            <Text style={styles.sectionTitle}>Plateforme</Text>
            <View style={styles.coreGrid}>
              {getModulesByGroup(employee, 'platform').map((module) => (
                <CoreTile
                  key={module.key}
                  module={module}
                  onPress={() => onOpenModule(module.key)}
                />
              ))}
            </View>
          </>
        ) : null}
      </ScrollView>
    </SafeAreaView>
  );
}

function VerticalBlock({
  vertical,
  entries,
  expanded,
  onToggle,
  onOpenModule,
}: {
  vertical: VerticalDescriptor;
  entries: ReturnType<typeof buildBusinessRail>;
  expanded: boolean;
  onToggle: () => void;
  onOpenModule: (key: ClientModuleKey) => void;
}) {
  const totalChildren = entries.reduce((sum, entry) => sum + entry.children.length, 0);

  return (
    <View style={styles.verticalBlock}>
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={`${vertical.title} — ${vertical.description}`}
        accessibilityState={{ expanded }}
        onPress={onToggle}
        style={({ pressed }) => [
          styles.verticalCard,
          { borderColor: `${vertical.accent}55` },
          pressed && styles.pressed,
        ]}
      >
        <View style={[styles.verticalIcon, { backgroundColor: `${vertical.accent}22` }]}>
          <View style={[styles.verticalIconInner, { backgroundColor: vertical.accent }]} />
        </View>
        <View style={styles.verticalTexts}>
          <Text style={styles.verticalTitle}>{vertical.title}</Text>
          <Text style={styles.verticalDescription} numberOfLines={1}>
            {vertical.description}
          </Text>
        </View>
        <View style={styles.verticalRight}>
          <Text style={styles.verticalCount}>
            {entries.length + totalChildren}
          </Text>
          <Text style={styles.chevron}>{expanded ? '\u2303' : '\u2304'}</Text>
        </View>
      </Pressable>

      {expanded ? (
        <View style={styles.children}>
          {entries.map((entry) => (
            <View key={entry.module.key}>
              <ModuleRow
                module={entry.module}
                accent={vertical.accent}
                onPress={() => onOpenModule(entry.module.key)}
              />
              {entry.children.map((child) => (
                <ModuleRow
                  key={child.key}
                  module={child}
                  accent={vertical.accent}
                  nested
                  onPress={() => onOpenModule(child.key)}
                />
              ))}
            </View>
          ))}
        </View>
      ) : null}
    </View>
  );
}

function ModuleRow({
  module,
  accent,
  nested,
  onPress,
}: {
  module: ClientModuleAccess;
  accent: string;
  nested?: boolean;
  onPress: () => void;
}) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={module.label}
      onPress={onPress}
      style={({ pressed }) => [
        styles.moduleRow,
        nested && styles.moduleRowNested,
        pressed && styles.pressed,
      ]}
    >
      <View style={[styles.moduleDot, { backgroundColor: accent }]} />
      <Text style={styles.moduleLabel} numberOfLines={1}>
        {module.label}
      </Text>
      {module.state === 'trial' ? <Badge label="essai" color={colors.warning} /> : null}
      <Text style={styles.moduleChevron}>{'\u203a'}</Text>
    </Pressable>
  );
}

function CoreTile({
  module,
  onPress,
}: {
  module: ClientModuleAccess;
  onPress: () => void;
}) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={module.label}
      onPress={onPress}
      style={({ pressed }) => [styles.coreTile, pressed && styles.pressed]}
    >
      <Text style={styles.coreLabel} numberOfLines={2}>
        {module.label}
      </Text>
      {module.state === 'trial' ? <Badge label="essai" color={colors.warning} /> : null}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.background },
  header: {
    paddingHorizontal: spacing.lg,
    paddingTop: spacing.lg,
    paddingBottom: spacing.md,
  },
  title: { color: colors.text, fontSize: typography.title, fontWeight: '700', letterSpacing: -0.6 },
  subtitle: { color: colors.textMuted, fontSize: typography.body, marginTop: 2 },
  content: { paddingHorizontal: spacing.lg, paddingBottom: spacing.xxl },
  verticalBlock: { marginBottom: spacing.md },
  verticalCard: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.surface,
    borderRadius: radius.lg,
    borderWidth: 1,
    padding: spacing.lg,
    gap: spacing.md,
  },
  pressed: { opacity: 0.75 },
  verticalIcon: {
    width: 40,
    height: 40,
    borderRadius: radius.md,
    alignItems: 'center',
    justifyContent: 'center',
  },
  verticalIconInner: { width: 16, height: 16, borderRadius: 5 },
  verticalTexts: { flex: 1 },
  verticalTitle: { color: colors.text, fontSize: typography.subheading, fontWeight: '700' },
  verticalDescription: { color: colors.textFaint, fontSize: typography.caption, marginTop: 2 },
  verticalRight: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm },
  verticalCount: {
    color: colors.textMuted,
    fontSize: typography.caption,
    backgroundColor: colors.surfaceAlt,
    paddingHorizontal: spacing.sm,
    paddingVertical: 2,
    borderRadius: radius.pill,
    overflow: 'hidden',
  },
  chevron: { color: colors.textFaint, fontSize: 14 },
  children: {
    marginTop: spacing.sm,
    backgroundColor: colors.surface,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.border,
    overflow: 'hidden',
  },
  moduleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.md,
    gap: spacing.md,
  },
  moduleRowNested: { paddingLeft: spacing.xxl, backgroundColor: colors.surfaceAlt },
  moduleDot: { width: 6, height: 6, borderRadius: 3 },
  moduleLabel: { flex: 1, color: colors.text, fontSize: typography.body, fontWeight: '600' },
  moduleChevron: { color: colors.textFaint, fontSize: 18 },
  sectionTitle: {
    color: colors.text,
    fontSize: typography.subheading,
    fontWeight: '700',
    marginTop: spacing.xl,
    marginBottom: spacing.md,
  },
  coreGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.md },
  coreTile: {
    flexBasis: '47%',
    flexGrow: 1,
    backgroundColor: colors.surface,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.border,
    padding: spacing.lg,
    gap: spacing.sm,
  },
  coreLabel: { color: colors.text, fontSize: typography.body, fontWeight: '600' },
});
