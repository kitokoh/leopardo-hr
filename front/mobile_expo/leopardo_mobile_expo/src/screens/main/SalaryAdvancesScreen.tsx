import React, { useCallback, useEffect, useState } from 'react';
import { Alert, Modal, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { createSalaryAdvance, fetchSalaryAdvances } from '../../api/payroll';
import { extractErrorMessage } from '../../api/client';
import { ScreenState } from '../../components/ScreenState';
import { Button } from '../../components/Button';
import { TextField } from '../../components/TextField';
import { Card, Badge, EmptyState } from '../../components/Card';
import { ScreenHeader } from '../../components/ScreenHeader';
import type { SalaryAdvance } from '../../models/salaryAdvance';
import { advanceStatusLabel } from '../../models/salaryAdvance';
import { formatMoney } from '../../models/payroll';
import { formatDate } from '../../utils/format';
import { colors, radius, spacing, typography } from '../../theme';

const STATUS_COLORS: Record<string, string> = {
  pending: colors.warning,
  manager_approved: colors.primary,
  approved: colors.success,
  paid: colors.success,
  rejected: colors.danger,
  cancelled: colors.textMuted,
};

export function SalaryAdvancesScreen() {
  const [advances, setAdvances] = useState<SalaryAdvance[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [formOpen, setFormOpen] = useState(false);

  const load = useCallback(async (isRefresh = false) => {
    if (isRefresh) setRefreshing(true);
    else setLoading(true);
    setError(null);
    try {
      setAdvances(await fetchSalaryAdvances());
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

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      <ScreenHeader
        title="Avances"
        subtitle="Avances sur salaire"
        right={
          <Button
            label="Demander"
            onPress={() => setFormOpen(true)}
            style={styles.headerButton}
          />
        }
      />

      <ScreenState
        loading={loading}
        error={error}
        onRetry={() => load()}
        refreshing={refreshing}
        onRefresh={() => load(true)}
      >
        {advances.length === 0 ? (
          <EmptyState
            title="Aucune avance"
            message="Vous n'avez pas encore demande d'avance sur salaire."
          />
        ) : (
          advances.map((advance) => (
            <Card key={advance.id} style={styles.card}>
              <View style={styles.row}>
                <View style={styles.texts}>
                  <Text style={styles.amount}>
                    {formatMoney(advance.amount, advance.currency)}
                  </Text>
                  <Text style={styles.meta}>
                    Demande du {formatDate(advance.requested_at ?? advance.created_at)}
                  </Text>
                  {advance.reason ? (
                    <Text style={styles.reason}>{advance.reason}</Text>
                  ) : null}
                </View>
                <Badge
                  label={advanceStatusLabel(advance.status)}
                  color={STATUS_COLORS[advance.status] ?? colors.textMuted}
                />
              </View>

              {advance.amount_remaining != null ? (
                <View style={styles.footer}>
                  <Text style={styles.footerText}>
                    Reste a rembourser :{' '}
                    {formatMoney(advance.amount_remaining, advance.currency)}
                  </Text>
                  {advance.monthly_deduction != null ? (
                    <Text style={styles.footerText}>
                      Mensualite : {formatMoney(advance.monthly_deduction, advance.currency)}
                    </Text>
                  ) : null}
                </View>
              ) : null}

              {advance.decision_comment ? (
                <Text style={styles.comment}>{advance.decision_comment}</Text>
              ) : null}
            </Card>
          ))
        )}
      </ScreenState>

      <AdvanceFormModal
        visible={formOpen}
        onClose={() => setFormOpen(false)}
        onCreated={() => {
          setFormOpen(false);
          load(true);
        }}
      />
    </SafeAreaView>
  );
}

function AdvanceFormModal({
  visible,
  onClose,
  onCreated,
}: {
  visible: boolean;
  onClose: () => void;
  onCreated: () => void;
}) {
  const [amount, setAmount] = useState('');
  const [reason, setReason] = useState('');
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleSubmit() {
    const parsed = Number.parseFloat(amount.replace(',', '.'));
    if (!Number.isFinite(parsed) || parsed <= 0) {
      setError('Saisissez un montant superieur a zero.');
      return;
    }
    setSaving(true);
    setError(null);
    try {
      await createSalaryAdvance({ amount: parsed, reason: reason.trim() || undefined });
      setAmount('');
      setReason('');
      onCreated();
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setSaving(false);
    }
  }

  return (
    <Modal visible={visible} animationType="slide" transparent onRequestClose={onClose}>
      <View style={styles.backdrop}>
        <View style={styles.sheet}>
          <Text style={styles.sheetTitle}>Demander une avance</Text>
          <TextField
            label="Montant"
            value={amount}
            onChangeText={setAmount}
            keyboardType="decimal-pad"
            placeholder="0.00"
          />
          <TextField
            label="Motif (optionnel)"
            value={reason}
            onChangeText={setReason}
            placeholder="Precisez le motif"
            multiline
          />
          {error ? <Text style={styles.sheetError}>{error}</Text> : null}
          <Button label="Envoyer la demande" onPress={handleSubmit} loading={saving} />
          <Button label="Annuler" onPress={onClose} variant="ghost" style={styles.cancel} />
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.background },
  headerButton: { minHeight: 38, paddingHorizontal: spacing.md },
  card: { marginBottom: spacing.sm },
  row: { flexDirection: 'row', alignItems: 'flex-start' },
  texts: { flex: 1 },
  amount: { color: colors.text, fontSize: typography.heading, fontWeight: '800' },
  meta: { color: colors.textFaint, fontSize: typography.caption, marginTop: 2 },
  reason: { color: colors.textMuted, fontSize: typography.body, marginTop: spacing.sm },
  footer: {
    marginTop: spacing.md,
    paddingTop: spacing.md,
    borderTopWidth: 1,
    borderTopColor: colors.border,
    gap: 2,
  },
  footerText: { color: colors.textMuted, fontSize: typography.caption },
  comment: { color: colors.textMuted, fontSize: typography.caption, marginTop: spacing.sm },
  backdrop: { flex: 1, backgroundColor: 'rgba(0,0,0,0.6)', justifyContent: 'flex-end' },
  sheet: {
    backgroundColor: colors.surface,
    borderTopLeftRadius: radius.xl,
    borderTopRightRadius: radius.xl,
    padding: spacing.xl,
    paddingBottom: spacing.xxl,
  },
  sheetTitle: {
    color: colors.text,
    fontSize: typography.heading,
    fontWeight: '700',
    marginBottom: spacing.lg,
  },
  sheetError: { color: colors.danger, fontSize: typography.body, marginBottom: spacing.md },
  cancel: { marginTop: spacing.sm },
});
