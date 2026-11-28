import React, { useCallback, useEffect, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { extractErrorMessage } from '../../api/client';
import { ScreenState } from '../../components/ScreenState';
import { Badge, Card, EmptyState, StatCard } from '../../components/Card';
import { colors, radius, spacing, typography } from '../../theme';
import { formatMoney } from '../../models/payroll';
import { MODULE_SCREENS, readPath, type ListColumn, type ListSpec } from '../../verticals/screens';
import { MODULE_WRITES, type ModuleAction, type RowAction } from '../../verticals/actions';
import { moduleByKey } from '../../verticals/catalog';
import type { ClientModuleKey } from '../../verticals/types';

type Props = {
  moduleKey: ClientModuleKey;
  onBack: () => void;
  /** Devise du tenant (fallback d'affichage). */
  currency?: string;
  /** Ouvre le formulaire d'une action de creation. */
  onOpenAction?: (moduleKey: ClientModuleKey, actionKey: string) => void;
  /** Ouvre le formulaire d'une action sur element existant. */
  onOpenRowAction?: (moduleKey: ClientModuleKey, actionKey: string) => void;
};

type ListState = {
  items: unknown[];
  loading: boolean;
  error: string | null;
};

/**
 * Ecran GENERIQUE d'un module metier.
 *
 * Equivalent mobile de `ModulePageShell` du web : un bandeau compact (icone,
 * titre, sous-titre) puis les KPI et les listes declares dans le registre
 * `MODULE_SCREENS`. Un module sans entree de registre affiche un etat
 * « en preparation » explicite — jamais un ecran vide.
 */
export function ModuleScreen({
  moduleKey,
  onBack,
  currency,
  onOpenAction,
  onOpenRowAction,
}: Props) {
  const module = moduleByKey(moduleKey);
  const spec = MODULE_SCREENS[moduleKey];
  const writes = MODULE_WRITES[moduleKey];
  const createActions: ModuleAction[] = writes?.actions ?? [];
  const rowActions: RowAction[] = writes?.rowActions ?? [];

  const [kpis, setKpis] = useState<Record<string, unknown> | null>(null);
  const [kpiError, setKpiError] = useState<string | null>(null);
  const [lists, setLists] = useState<Record<string, ListState>>({});
  const [refreshing, setRefreshing] = useState(false);
  const [loading, setLoading] = useState(true);

  const loadAll = useCallback(
    async (isRefresh = false) => {
      if (!spec) {
        setLoading(false);
        return;
      }
      if (isRefresh) setRefreshing(true);
      else setLoading(true);

      // KPI
      if (spec.loadKpis) {
        try {
          const data = await spec.loadKpis();
          setKpis(data);
          setKpiError(null);
        } catch (err) {
          setKpis(null);
          setKpiError(extractErrorMessage(err));
        }
      }

      // Listes — chaque liste echoue independamment (un module peut avoir
      // une ressource autorisee et une autre refusee en 403).
      const initial: Record<string, ListState> = {};
      for (const list of spec.lists) {
        initial[list.title] = { items: [], loading: true, error: null };
      }
      setLists(initial);

      await Promise.all(
        spec.lists.map(async (list) => {
          try {
            const items = await list.load();
            setLists((prev) => ({
              ...prev,
              [list.title]: { items, loading: false, error: null },
            }));
          } catch (err) {
            setLists((prev) => ({
              ...prev,
              [list.title]: { items: [], loading: false, error: extractErrorMessage(err) },
            }));
          }
        }),
      );

      setLoading(false);
      setRefreshing(false);
    },
    [spec],
  );

  useEffect(() => {
    loadAll();
  }, [loadAll]);

  const title = spec?.title ?? module?.label ?? 'Module';
  const subtitle = spec?.subtitle ?? module?.upgradeLabel ?? '';

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      {/* Bandeau compact — meme role que ModulePageShell du web */}
      <View style={styles.header}>
        <Pressable
          accessibilityRole="button"
          accessibilityLabel="Retour"
          onPress={onBack}
          style={styles.backButton}
          hitSlop={12}
        >
          <Text style={styles.backGlyph}>{'\u2039'}</Text>
        </Pressable>
        <View style={styles.headerTexts}>
          <Text style={styles.title} numberOfLines={1}>
            {title}
          </Text>
          {subtitle ? (
            <Text style={styles.subtitle} numberOfLines={1}>
              {subtitle}
            </Text>
          ) : null}
        </View>
        {module?.vertical ? <Badge label={module.vertical} color={colors.accent} /> : null}
      </View>

      {!spec ? (
        <View style={styles.preparing}>
          <Text style={styles.preparingTitle}>Module en preparation</Text>
          <Text style={styles.preparingText}>
            {module?.upgradeLabel ?? 'Ce module'} n\u2019a pas encore d\u2019ecran mobile dedie.
            Il reste accessible depuis l\u2019application web.
          </Text>
        </View>
      ) : (
        <ScreenState
          loading={loading}
          error={null}
          refreshing={refreshing}
          onRefresh={() => loadAll(true)}
        >
          {/* Actions d'ecriture — equivalent des boutons du web */}
          {createActions.length > 0 || rowActions.length > 0 ? (
            <View style={styles.actionsBlock}>
              {createActions.length > 0 ? (
                <ScrollView
                  horizontal
                  showsHorizontalScrollIndicator={false}
                  contentContainerStyle={styles.actionRow}
                >
                  {createActions.map((action) => (
                    <Pressable
                      key={action.key}
                      accessibilityRole="button"
                      accessibilityLabel={action.label}
                      onPress={() => onOpenAction?.(moduleKey, action.key)}
                      style={({ pressed }) => [styles.actionChip, pressed && styles.pressed]}
                    >
                      <Text style={styles.actionChipPlus}>+</Text>
                      <Text style={styles.actionChipLabel} numberOfLines={1}>
                        {action.label}
                      </Text>
                    </Pressable>
                  ))}
                </ScrollView>
              ) : null}

              {rowActions.length > 0 ? (
                <View style={styles.rowActionsBlock}>
                  <Text style={styles.rowActionsTitle}>Actions</Text>
                  <View style={styles.rowActionsGrid}>
                    {rowActions.map((action) => (
                      <Pressable
                        key={action.key}
                        accessibilityRole="button"
                        accessibilityLabel={action.label}
                        onPress={() => onOpenRowAction?.(moduleKey, action.key)}
                        style={({ pressed }) => [
                          styles.rowActionTile,
                          action.destructive && styles.rowActionDestructive,
                          pressed && styles.pressed,
                        ]}
                      >
                        <Text
                          style={[
                            styles.rowActionLabel,
                            action.destructive && styles.rowActionLabelDestructive,
                          ]}
                          numberOfLines={2}
                        >
                          {action.label}
                        </Text>
                      </Pressable>
                    ))}
                  </View>
                </View>
              ) : null}
            </View>
          ) : null}

          {/* KPI */}
          {spec.kpis && spec.kpis.length > 0 ? (
            kpiError ? (
              <Card style={styles.kpiErrorCard}>
                <Text style={styles.kpiErrorText}>
                  Indicateurs indisponibles : {kpiError}
                </Text>
              </Card>
            ) : (
              <View style={styles.kpiGrid}>
                {spec.kpis.map((kpi) => (
                  <StatCard
                    key={kpi.label}
                    label={kpi.label}
                    value={formatKpi(readPath(kpis, kpi.path), kpi.format, currency)}
                    accent={kpi.accent}
                  />
                ))}
              </View>
            )
          ) : null}

          {/* Listes */}
          {spec.lists.map((list) => (
            <ListBlock
              key={list.title}
              spec={list}
              state={lists[list.title]}
              currency={currency}
            />
          ))}

          {spec.note ? (
            <Card style={styles.noteCard}>
              <Text style={styles.noteText}>{spec.note}</Text>
            </Card>
          ) : null}
        </ScreenState>
      )}
    </SafeAreaView>
  );
}

function ListBlock({
  spec,
  state,
  currency,
}: {
  spec: ListSpec;
  state?: ListState;
  currency?: string;
}) {
  const items = state?.items ?? [];

  return (
    <View style={styles.listBlock}>
      <View style={styles.listHeader}>
        <Text style={styles.listTitle}>{spec.title}</Text>
        {items.length > 0 ? (
          <Text style={styles.listCount}>{items.length}</Text>
        ) : null}
      </View>

      {state?.loading ? (
        <Card>
          <Text style={styles.listLoading}>Chargement\u2026</Text>
        </Card>
      ) : state?.error ? (
        <Card>
          <Text style={styles.listError}>{state.error}</Text>
        </Card>
      ) : items.length === 0 ? (
        <EmptyState title={spec.emptyMessage ?? 'Aucun element'} />
      ) : (
        <Card style={styles.listCard}>
          {items.slice(0, 25).map((item, index) => (
            <Row
              key={rowKey(item, index)}
              item={item}
              columns={spec.columns}
              currency={currency}
              isFirst={index === 0}
            />
          ))}
        </Card>
      )}
    </View>
  );
}

function Row({
  item,
  columns,
  currency,
  isFirst,
}: {
  item: unknown;
  columns: ListColumn[];
  currency?: string;
  isFirst: boolean;
}) {
  const titleCol = columns.find((column) => column.role === 'title') ?? columns[0];
  const metaCols = columns.filter((column) => column.role === 'meta');
  const badgeCol = columns.find((column) => column.role === 'badge');
  const amountCol = columns.find((column) => column.role === 'amount');

  const title = stringify(readPath(item, titleCol?.key ?? ''));
  const badge = badgeCol ? stringify(readPath(item, badgeCol.key)) : '';
  const amount = amountCol ? readPath(item, amountCol.key) : undefined;

  return (
    <View style={[styles.row, !isFirst && styles.rowBorder]}>
      <View style={styles.rowTexts}>
        <Text style={styles.rowTitle} numberOfLines={1}>
          {title || '\u2014'}
        </Text>
        {metaCols.length > 0 ? (
          <Text style={styles.rowMeta} numberOfLines={1}>
            {metaCols
              .map((column) => stringify(readPath(item, column.key)))
              .filter(Boolean)
              .join(' \u00b7 ')}
          </Text>
        ) : null}
      </View>
      {badge ? <Badge label={badge} color={badgeColor(badge)} /> : null}
      {amount !== undefined && amount !== null ? (
        <Text style={styles.rowAmount}>
          {formatKpi(amount, 'money', currency)}
        </Text>
      ) : null}
    </View>
  );
}

function rowKey(item: unknown, index: number): string {
  if (item && typeof item === 'object' && 'id' in (item as Record<string, unknown>)) {
    return String((item as Record<string, unknown>).id);
  }
  return `row-${index}`;
}

function stringify(value: unknown): string {
  if (value === null || value === undefined) return '';
  if (typeof value === 'string') return value;
  if (typeof value === 'number' || typeof value === 'boolean') return String(value);
  return '';
}

/** Formate une valeur de KPI selon son type declare. */
function formatKpi(
  value: unknown,
  format: 'number' | 'money' | 'percent' | 'text' | undefined,
  currency?: string,
): string {
  if (value === null || value === undefined || value === '') return '\u2014';

  if (format === 'money') {
    const numeric = typeof value === 'number' ? value : Number(value);
    if (Number.isNaN(numeric)) return stringify(value) || '\u2014';
    return formatMoney(numeric, currency ?? 'DA');
  }

  if (format === 'percent') {
    const numeric = typeof value === 'number' ? value : Number(value);
    if (Number.isNaN(numeric)) return stringify(value) || '\u2014';
    // L'API renvoie un ratio 0..1 (ex. occupancy_rate).
    const ratio = numeric > 1 ? numeric / 100 : numeric;
    return `${Math.round(ratio * 100)} %`;
  }

  if (format === 'number') {
    const numeric = typeof value === 'number' ? value : Number(value);
    if (Number.isNaN(numeric)) return stringify(value) || '\u2014';
    return new Intl.NumberFormat('fr-FR').format(numeric);
  }

  return stringify(value) || '\u2014';
}

/** Couleur d'une pastille de statut. */
function badgeColor(status: string): string {
  const normalized = status.toLowerCase();
  if (['active', 'approved', 'paid', 'confirmed', 'published', 'available', 'done', 'completed', 'online'].includes(normalized)) {
    return colors.success;
  }
  if (['pending', 'draft', 'trial', 'waiting', 'scheduled', 'in_progress', 'processing'].includes(normalized)) {
    return colors.warning;
  }
  if (['rejected', 'cancelled', 'canceled', 'failed', 'locked', 'offline', 'expired', 'void'].includes(normalized)) {
    return colors.danger;
  }
  return colors.textMuted;
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.background },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: spacing.lg,
    paddingTop: spacing.md,
    paddingBottom: spacing.md,
    gap: spacing.md,
  },
  backButton: {
    width: 36,
    height: 36,
    borderRadius: radius.md,
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
    alignItems: 'center',
    justifyContent: 'center',
  },
  backGlyph: { color: colors.text, fontSize: 24, lineHeight: 26, marginTop: -2 },
  headerTexts: { flex: 1 },
  title: { color: colors.text, fontSize: typography.heading, fontWeight: '700', letterSpacing: -0.4 },
  subtitle: { color: colors.textMuted, fontSize: typography.caption, marginTop: 1 },
  preparing: { padding: spacing.xl, alignItems: 'center' },
  preparingTitle: {
    color: colors.text,
    fontSize: typography.subheading,
    fontWeight: '700',
    textAlign: 'center',
  },
  preparingText: {
    color: colors.textMuted,
    fontSize: typography.body,
    textAlign: 'center',
    marginTop: spacing.sm,
    lineHeight: 20,
  },
  kpiGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.md, marginBottom: spacing.lg },
  kpiErrorCard: { marginBottom: spacing.lg, borderColor: `${colors.warning}55` },
  kpiErrorText: { color: colors.warning, fontSize: typography.caption },
  listBlock: { marginBottom: spacing.lg },
  listHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: spacing.sm,
  },
  listTitle: { color: colors.text, fontSize: typography.subheading, fontWeight: '700' },
  listCount: {
    color: colors.textFaint,
    fontSize: typography.caption,
    backgroundColor: colors.surfaceAlt,
    paddingHorizontal: spacing.sm,
    paddingVertical: 2,
    borderRadius: radius.pill,
    overflow: 'hidden',
  },
  listCard: { padding: 0 },
  listLoading: { color: colors.textMuted, fontSize: typography.body },
  listError: { color: colors.danger, fontSize: typography.caption, lineHeight: 18 },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.md,
    gap: spacing.sm,
  },
  rowBorder: { borderTopWidth: 1, borderTopColor: colors.border },
  rowTexts: { flex: 1 },
  rowTitle: { color: colors.text, fontSize: typography.body, fontWeight: '600' },
  rowMeta: { color: colors.textFaint, fontSize: typography.caption, marginTop: 2 },
  rowAmount: { color: colors.text, fontSize: typography.body, fontWeight: '700' },
  noteCard: { backgroundColor: colors.surfaceAlt, marginBottom: spacing.lg },
  noteText: { color: colors.textMuted, fontSize: typography.caption, lineHeight: 18 },
  actionsBlock: { marginBottom: spacing.lg },
  actionRow: { gap: spacing.sm, paddingRight: spacing.lg },
  actionChip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.xs,
    backgroundColor: colors.primary,
    borderRadius: radius.pill,
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.sm + 2,
  },
  actionChipPlus: { color: colors.white, fontSize: 16, fontWeight: '700', marginTop: -1 },
  actionChipLabel: { color: colors.white, fontSize: typography.caption, fontWeight: '700' },
  pressed: { opacity: 0.75 },
  rowActionsBlock: { marginTop: spacing.md },
  rowActionsTitle: {
    color: colors.textMuted,
    fontSize: typography.caption,
    fontWeight: '600',
    textTransform: 'uppercase',
    letterSpacing: 0.6,
    marginBottom: spacing.sm,
  },
  rowActionsGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm },
  rowActionTile: {
    flexBasis: '47%',
    flexGrow: 1,
    backgroundColor: colors.surface,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.border,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.md,
  },
  rowActionDestructive: { borderColor: `${colors.danger}55` },
  rowActionLabel: { color: colors.text, fontSize: typography.caption, fontWeight: '600' },
  rowActionLabelDestructive: { color: colors.danger },
});
