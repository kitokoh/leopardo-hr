import React, { useCallback, useEffect, useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { fetchPayrollSummary, fetchPaySlips } from '../../api/payroll';
import { extractErrorMessage } from '../../api/client';
import { ScreenState } from '../../components/ScreenState';
import { Card, Badge, EmptyState } from '../../components/Card';
import { ScreenHeader } from '../../components/ScreenHeader';
import type { PaySlip, PayrollMobileSummary } from '../../models/payroll';
import { formatMoney, monthLabel } from '../../models/payroll';
import { formatDate } from '../../utils/format';
import { colors, radius, spacing, typography } from '../../theme';

export function PayrollScreen() {
  const [summary, setSummary] = useState<PayrollMobileSummary | null>(null);
  const [slips, setSlips] = useState<PaySlip[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async (isRefresh = false) => {
    if (isRefresh) setRefreshing(true);
    else setLoading(true);
    setError(null);
    try {
      const [summaryData, slipList] = await Promise.all([
        fetchPayrollSummary().catch(() => null),
        fetchPaySlips(),
      ]);
      setSummary(summaryData);
      setSlips(slipList);
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const currency = summary?.currency ?? slips[0]?.currency ?? 'DA';
  const lastSlip = summary?.last_pay_slip ?? slips[0] ?? null;

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      <ScreenHeader title="Paie" subtitle="Bulletins et synthese" />

      <ScreenState
        loading={loading}
        error={error}
        onRetry={() => load()}
        refreshing={refreshing}
        onRefresh={() => load(true)}
      >
        <Card style={styles.heroCard}>
          <Text style={styles.heroLabel}>Dernier net verse</Text>
          <Text style={styles.heroValue}>
            {formatMoney(lastSlip?.net_salary ?? summary?.current_month_net ?? 0, currency)}
          </Text>
          {lastSlip ? (
            <Text style={styles.heroHint}>
              {monthLabel(lastSlip.month, lastSlip.year)} · brut{' '}
              {formatMoney(lastSlip.gross_salary, currency)}
            </Text>
          ) : (
            <Text style={styles.heroHint}>Aucun bulletin disponible</Text>
          )}
        </Card>

        <View style={styles.metaRow}>
          <View style={styles.metaItem}>
            <Text style={styles.metaValue}>
              {summary?.pending_advances != null
                ? formatMoney(summary.pending_advances, currency)
                : '—'}
            </Text>
            <Text style={styles.metaLabel}>Avances en cours</Text>
          </View>
          <View style={styles.metaItem}>
            <Text style={styles.metaValue}>
              {summary?.next_payment_date ? formatDate(summary.next_payment_date) : '—'}
            </Text>
            <Text style={styles.metaLabel}>Prochain versement</Text>
          </View>
        </View>

        <Text style={styles.sectionTitle}>Bulletins de paie</Text>
        {slips.length === 0 ? (
          <EmptyState
            title="Aucun bulletin"
            message="Vos bulletins apparaitront ici des leur publication."
          />
        ) : (
          slips.map((slip) => (
            <Card key={slip.id} style={styles.slipCard}>
              <View style={styles.slipRow}>
                <View style={styles.slipTexts}>
                  <Text style={styles.slipPeriod}>
                    {monthLabel(slip.month, slip.year)}
                  </Text>
                  <Text style={styles.slipMeta}>
                    Brut {formatMoney(slip.gross_salary, slip.currency)}
                  </Text>
                </View>
                <View style={styles.slipRight}>
                  <Text style={styles.slipNet}>
                    {formatMoney(slip.net_salary, slip.currency)}
                  </Text>
                  <Badge
                    label={slip.status}
                    color={slip.status === 'paid' ? colors.success : colors.warning}
                  />
                </View>
              </View>
            </Card>
          ))
        )}
      </ScreenState>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.background },
  heroCard: { backgroundColor: colors.surfaceAlt },
  heroLabel: {
    color: colors.textMuted,
    fontSize: typography.caption,
    textTransform: 'uppercase',
    letterSpacing: 0.8,
  },
  heroValue: {
    color: colors.text,
    fontSize: 36,
    fontWeight: '800',
    letterSpacing: -1.2,
    marginTop: spacing.xs,
  },
  heroHint: { color: colors.textMuted, fontSize: typography.body, marginTop: spacing.xs },
  metaRow: {
    flexDirection: 'row',
    backgroundColor: colors.surface,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.border,
    padding: spacing.lg,
    marginTop: spacing.lg,
  },
  metaItem: { flex: 1, alignItems: 'center' },
  metaValue: { color: colors.text, fontSize: typography.subheading, fontWeight: '700' },
  metaLabel: {
    color: colors.textFaint,
    fontSize: typography.caption,
    marginTop: 2,
    textAlign: 'center',
  },
  sectionTitle: {
    color: colors.text,
    fontSize: typography.subheading,
    fontWeight: '700',
    marginTop: spacing.xl,
    marginBottom: spacing.md,
  },
  slipCard: { marginBottom: spacing.sm },
  slipRow: { flexDirection: 'row', alignItems: 'center' },
  slipTexts: { flex: 1 },
  slipPeriod: { color: colors.text, fontSize: typography.body, fontWeight: '700' },
  slipMeta: { color: colors.textFaint, fontSize: typography.caption, marginTop: 2 },
  slipRight: { alignItems: 'flex-end', gap: spacing.xs },
  slipNet: { color: colors.accent, fontSize: typography.subheading, fontWeight: '700' },
});
