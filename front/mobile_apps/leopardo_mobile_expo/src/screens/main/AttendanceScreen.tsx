import React, { useCallback, useEffect, useState } from 'react';
import { Alert, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import * as Location from 'expo-location';
import { checkIn, checkOut, fetchDailySummary, fetchToday } from '../../api/attendance';
import { extractErrorMessage } from '../../api/client';
import { ScreenState } from '../../components/ScreenState';
import { Button } from '../../components/Button';
import { Card, Badge, EmptyState } from '../../components/Card';
import { ScreenHeader } from '../../components/ScreenHeader';
import type { AttendanceLog } from '../../models/attendance';
import { formatHours, formatTime, isOpenSession } from '../../models/attendance';
import type { DailySummary } from '../../models/summary';
import { formatMoney } from '../../models/payroll';
import { colors, radius, spacing, typography } from '../../theme';

export function AttendanceScreen() {
  const [logs, setLogs] = useState<AttendanceLog[]>([]);
  const [daily, setDaily] = useState<DailySummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [punching, setPunching] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async (isRefresh = false) => {
    if (isRefresh) setRefreshing(true);
    else setLoading(true);
    setError(null);
    try {
      const [todayLogs, summary] = await Promise.all([
        fetchToday(),
        fetchDailySummary().catch(() => null),
      ]);
      setLogs(todayLogs);
      setDaily(summary);
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

  const openSession = logs.find(isOpenSession) ?? null;
  const isCheckedIn = Boolean(openSession);

  /**
   * Recupere la position si l'utilisateur l'autorise, sans jamais bloquer
   * le pointage : un refus de permission n'empeche pas de pointer.
   */
  async function resolveLocation() {
    try {
      const { status } = await Location.requestForegroundPermissionsAsync();
      if (status !== 'granted') return {};
      const position = await Location.getCurrentPositionAsync({
        accuracy: Location.Accuracy.Balanced,
      });
      return {
        gps_lat: position.coords.latitude,
        gps_lng: position.coords.longitude,
        gps_accuracy: position.coords.accuracy ?? undefined,
      };
    } catch {
      return {};
    }
  }

  async function handlePunch() {
    setPunching(true);
    try {
      const location = await resolveLocation();
      if (isCheckedIn) {
        await checkOut(location);
      } else {
        await checkIn(location);
      }
      await load(true);
    } catch (err) {
      Alert.alert('Pointage impossible', extractErrorMessage(err));
    } finally {
      setPunching(false);
    }
  }

  const currency = daily?.currency ?? 'DA';

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      <ScreenHeader
        title="Pointage"
        subtitle={new Date().toLocaleDateString('fr-FR', {
          weekday: 'long',
          day: 'numeric',
          month: 'long',
        })}
      />

      <ScreenState
        loading={loading}
        error={error}
        onRetry={() => load()}
        refreshing={refreshing}
        onRefresh={() => load(true)}
      >
        <Card style={styles.punchCard}>
          <View style={styles.punchHeader}>
            <Text style={styles.punchLabel}>Statut</Text>
            <Badge
              label={isCheckedIn ? 'En service' : 'Non pointe'}
              color={isCheckedIn ? colors.success : colors.textMuted}
            />
          </View>

          <Text style={styles.punchTime}>
            {isCheckedIn ? formatTime(openSession?.check_in) : '--:--'}
          </Text>
          <Text style={styles.punchHint}>
            {isCheckedIn
              ? `Entree enregistree · session ${openSession?.session_number ?? 1}`
              : "Aucune entree enregistree aujourd'hui"}
          </Text>

          <Button
            label={isCheckedIn ? 'Pointer la sortie' : "Pointer l'entree"}
            onPress={handlePunch}
            loading={punching}
            variant={isCheckedIn ? 'danger' : 'primary'}
            style={styles.punchButton}
          />
        </Card>

        <View style={styles.summaryRow}>
          <View style={styles.summaryItem}>
            <Text style={styles.summaryValue}>
              {formatHours(daily?.hours_worked ?? 0)}
            </Text>
            <Text style={styles.summaryLabel}>Heures du jour</Text>
          </View>
          <View style={styles.summaryItem}>
            <Text style={styles.summaryValue}>
              {formatHours(daily?.overtime_hours ?? 0)}
            </Text>
            <Text style={styles.summaryLabel}>Heures sup.</Text>
          </View>
          <View style={styles.summaryItem}>
            <Text style={styles.summaryValue}>
              {formatMoney(daily?.total_estimated ?? 0, currency)}
            </Text>
            <Text style={styles.summaryLabel}>Estimation</Text>
          </View>
        </View>

        <Text style={styles.sectionTitle}>Sessions du jour</Text>
        {logs.length === 0 ? (
          <EmptyState
            title="Aucune session"
            message="Pointez votre entree pour demarrer la journee."
          />
        ) : (
          logs.map((log) => (
            <Card key={log.id} style={styles.logCard}>
              <View style={styles.logRow}>
                <View style={styles.logSession}>
                  <Text style={styles.logSessionText}>#{log.session_number}</Text>
                </View>
                <View style={styles.logTimes}>
                  <Text style={styles.logTime}>
                    {formatTime(log.check_in)} → {formatTime(log.check_out)}
                  </Text>
                  <Text style={styles.logMeta}>
                    {formatHours(log.hours_worked ?? 0)}
                    {log.late_minutes ? ` · ${log.late_minutes} min de retard` : ''}
                  </Text>
                </View>
                <Badge
                  label={log.status}
                  color={
                    log.status === 'present'
                      ? colors.success
                      : log.status === 'late'
                        ? colors.warning
                        : colors.textMuted
                  }
                />
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
  punchCard: { backgroundColor: colors.surfaceAlt, alignItems: 'center' },
  punchHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    width: '100%',
  },
  punchLabel: {
    color: colors.textMuted,
    fontSize: typography.caption,
    textTransform: 'uppercase',
    letterSpacing: 0.8,
  },
  punchTime: {
    color: colors.text,
    fontSize: 52,
    fontWeight: '800',
    letterSpacing: -2,
    marginTop: spacing.md,
  },
  punchHint: { color: colors.textMuted, fontSize: typography.body, marginTop: spacing.xs },
  punchButton: { width: '100%', marginTop: spacing.xl },
  summaryRow: {
    flexDirection: 'row',
    backgroundColor: colors.surface,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.border,
    padding: spacing.lg,
    marginTop: spacing.lg,
  },
  summaryItem: { flex: 1, alignItems: 'center' },
  summaryValue: { color: colors.text, fontSize: typography.subheading, fontWeight: '700' },
  summaryLabel: {
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
  logCard: { marginBottom: spacing.sm, paddingVertical: spacing.md },
  logRow: { flexDirection: 'row', alignItems: 'center' },
  logSession: {
    width: 34,
    height: 34,
    borderRadius: radius.sm,
    backgroundColor: `${colors.primary}22`,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: spacing.md,
  },
  logSessionText: { color: colors.primary, fontWeight: '700', fontSize: typography.caption },
  logTimes: { flex: 1 },
  logTime: { color: colors.text, fontSize: typography.body, fontWeight: '600' },
  logMeta: { color: colors.textFaint, fontSize: typography.caption, marginTop: 2 },
});
