import React, { useCallback, useEffect, useState } from 'react';
import { Alert, Modal, Pressable, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { createAbsence, fetchAbsences, fetchLeaveBalances } from '../../api/absences';
import { extractErrorMessage } from '../../api/client';
import { ScreenState } from '../../components/ScreenState';
import { Button } from '../../components/Button';
import { TextField } from '../../components/TextField';
import { Card, Badge, EmptyState } from '../../components/Card';
import { ScreenHeader } from '../../components/ScreenHeader';
import type { Absence, LeaveBalance } from '../../models/absence';
import { absenceTypeLabel, formatDateRange } from '../../models/absence';
import { todayIso } from '../../utils/format';
import { colors, radius, spacing, typography } from '../../theme';

const STATUS_LABELS: Record<string, string> = {
  pending: 'En attente',
  approved: 'Approuvee',
  rejected: 'Refusee',
};

const STATUS_COLORS: Record<string, string> = {
  pending: colors.warning,
  approved: colors.success,
  rejected: colors.danger,
};

export function AbsencesScreen() {
  const [absences, setAbsences] = useState<Absence[]>([]);
  const [balances, setBalances] = useState<LeaveBalance[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [formOpen, setFormOpen] = useState(false);

  const load = useCallback(async (isRefresh = false) => {
    if (isRefresh) setRefreshing(true);
    else setLoading(true);
    setError(null);
    try {
      const [list, leaveBalances] = await Promise.all([
        fetchAbsences(),
        fetchLeaveBalances().catch(() => []),
      ]);
      setAbsences(list);
      setBalances(leaveBalances);
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
        title="Absences"
        subtitle="Conges et justificatifs"
        right={
          <Pressable
            accessibilityRole="button"
            accessibilityLabel="Declarer une absence"
            onPress={() => setFormOpen(true)}
            style={styles.addButton}
          >
            <Text style={styles.addButtonText}>+</Text>
          </Pressable>
        }
      />

      <ScreenState
        loading={loading}
        error={error}
        onRetry={() => load()}
        refreshing={refreshing}
        onRefresh={() => load(true)}
      >
        {balances.length > 0 ? (
          <>
            <Text style={styles.sectionTitle}>Soldes de conges</Text>
            <View style={styles.balanceGrid}>
              {balances.map((balance, index) => {
                const remaining =
                  balance.remaining_days ?? balance.balance ?? 0;
                return (
                  <View key={`${balance.id ?? index}`} style={styles.balanceCard}>
                    <Text style={styles.balanceValue}>{remaining}</Text>
                    <Text style={styles.balanceLabel}>
                      {balance.name ?? balance.policy_name ?? 'Conge'}
                    </Text>
                    {balance.entitled_days != null ? (
                      <Text style={styles.balanceHint}>
                        sur {balance.entitled_days} j
                      </Text>
                    ) : null}
                  </View>
                );
              })}
            </View>
          </>
        ) : null}

        <Text style={styles.sectionTitle}>Mes demandes</Text>
        {absences.length === 0 ? (
          <EmptyState
            title="Aucune absence declaree"
            message="Appuyez sur + pour declarer une absence."
          />
        ) : (
          absences.map((absence) => (
            <Card key={absence.id} style={styles.absenceCard}>
              <View style={styles.absenceHeader}>
                <Text style={styles.absenceType}>{absenceTypeLabel(absence)}</Text>
                <Badge
                  label={STATUS_LABELS[absence.status] ?? absence.status}
                  color={STATUS_COLORS[absence.status] ?? colors.textMuted}
                />
              </View>
              <Text style={styles.absenceDates}>
                {formatDateRange(absence.start_date, absence.end_date)}
              </Text>
              <Text style={styles.absenceMeta}>
                {absence.days_count} jour{absence.days_count > 1 ? 's' : ''}
                {absence.reason ? ` · ${absence.reason}` : ''}
              </Text>
              {absence.rejection_reason ? (
                <Text style={styles.rejection}>
                  Motif du refus : {absence.rejection_reason}
                </Text>
              ) : null}
            </Card>
          ))
        )}
      </ScreenState>

      <AbsenceFormModal
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

function AbsenceFormModal({
  visible,
  onClose,
  onCreated,
}: {
  visible: boolean;
  onClose: () => void;
  onCreated: () => void;
}) {
  const [typeId, setTypeId] = useState('1');
  const [startDate, setStartDate] = useState(todayIso());
  const [endDate, setEndDate] = useState(todayIso());
  const [reason, setReason] = useState('');
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleSubmit() {
    const parsedType = Number.parseInt(typeId, 10);
    if (!Number.isFinite(parsedType) || parsedType <= 0) {
      setError("L'identifiant du type d'absence doit etre un nombre.");
      return;
    }
    if (!/^\d{4}-\d{2}-\d{2}$/.test(startDate) || !/^\d{4}-\d{2}-\d{2}$/.test(endDate)) {
      setError('Les dates doivent etre au format AAAA-MM-JJ.');
      return;
    }
    if (endDate < startDate) {
      setError('La date de fin doit suivre la date de debut.');
      return;
    }

    setSaving(true);
    setError(null);
    try {
      await createAbsence({
        absenceTypeId: parsedType,
        startDate,
        endDate,
        reason: reason.trim() || undefined,
      });
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
      <View style={styles.modalBackdrop}>
        <View style={styles.modalSheet}>
          <Text style={styles.modalTitle}>Declarer une absence</Text>

          <TextField
            label="Type d'absence (ID)"
            value={typeId}
            onChangeText={setTypeId}
            keyboardType="number-pad"
            placeholder="1"
          />
          <TextField
            label="Date de debut"
            value={startDate}
            onChangeText={setStartDate}
            placeholder="AAAA-MM-JJ"
            autoCapitalize="none"
          />
          <TextField
            label="Date de fin"
            value={endDate}
            onChangeText={setEndDate}
            placeholder="AAAA-MM-JJ"
            autoCapitalize="none"
          />
          <TextField
            label="Motif (optionnel)"
            value={reason}
            onChangeText={setReason}
            placeholder="Precisez le motif"
            multiline
          />

          {error ? <Text style={styles.modalError}>{error}</Text> : null}

          <Button label="Envoyer la demande" onPress={handleSubmit} loading={saving} />
          <Button label="Annuler" onPress={onClose} variant="ghost" style={styles.modalCancel} />
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.background },
  addButton: {
    width: 40,
    height: 40,
    borderRadius: radius.pill,
    backgroundColor: colors.primary,
    alignItems: 'center',
    justifyContent: 'center',
  },
  addButtonText: { color: colors.white, fontSize: 24, fontWeight: '600', lineHeight: 28 },
  sectionTitle: {
    color: colors.text,
    fontSize: typography.subheading,
    fontWeight: '700',
    marginTop: spacing.md,
    marginBottom: spacing.md,
  },
  balanceGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.md },
  balanceCard: {
    flexBasis: '30%',
    flexGrow: 1,
    backgroundColor: colors.surface,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.border,
    padding: spacing.md,
    alignItems: 'center',
  },
  balanceValue: { color: colors.accent, fontSize: 24, fontWeight: '800' },
  balanceLabel: {
    color: colors.textMuted,
    fontSize: typography.caption,
    marginTop: 2,
    textAlign: 'center',
  },
  balanceHint: { color: colors.textFaint, fontSize: 10, marginTop: 2 },
  absenceCard: { marginBottom: spacing.sm },
  absenceHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  absenceType: { color: colors.text, fontSize: typography.body, fontWeight: '700', flex: 1 },
  absenceDates: { color: colors.text, fontSize: typography.body, marginTop: spacing.sm },
  absenceMeta: { color: colors.textFaint, fontSize: typography.caption, marginTop: 2 },
  rejection: { color: colors.danger, fontSize: typography.caption, marginTop: spacing.sm },
  modalBackdrop: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.6)',
    justifyContent: 'flex-end',
  },
  modalSheet: {
    backgroundColor: colors.surface,
    borderTopLeftRadius: radius.xl,
    borderTopRightRadius: radius.xl,
    padding: spacing.xl,
    paddingBottom: spacing.xxl,
  },
  modalTitle: {
    color: colors.text,
    fontSize: typography.heading,
    fontWeight: '700',
    marginBottom: spacing.lg,
  },
  modalError: { color: colors.danger, fontSize: typography.body, marginBottom: spacing.md },
  modalCancel: { marginTop: spacing.sm },
});
