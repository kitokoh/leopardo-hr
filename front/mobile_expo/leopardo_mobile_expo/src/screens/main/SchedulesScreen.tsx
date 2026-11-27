import React, { useCallback, useEffect, useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { fetchSchedules } from '../../api/hr';
import { extractErrorMessage } from '../../api/client';
import { ScreenState } from '../../components/ScreenState';
import { Card, EmptyState } from '../../components/Card';
import { ScreenHeader } from '../../components/ScreenHeader';
import type { Schedule } from '../../models/schedule';
import { scheduleHours } from '../../models/schedule';
import { colors, radius, spacing, typography } from '../../theme';

export function SchedulesScreen() {
  const [schedules, setSchedules] = useState<Schedule[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async (isRefresh = false) => {
    if (isRefresh) setRefreshing(true);
    else setLoading(true);
    setError(null);
    try {
      setSchedules(await fetchSchedules());
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
      <ScreenHeader title="Plannings" subtitle="Horaires de travail" />

      <ScreenState
        loading={loading}
        error={error}
        onRetry={() => load()}
        refreshing={refreshing}
        onRefresh={() => load(true)}
      >
        {schedules.length === 0 ? (
          <EmptyState
            title="Aucun planning"
            message="Les horaires configures par votre entreprise apparaitront ici."
          />
        ) : (
          schedules.map((schedule) => (
            <Card key={schedule.id} style={styles.card}>
              <View style={styles.row}>
                <View style={styles.timeBlock}>
                  <Text style={styles.time}>{scheduleHours(schedule)}</Text>
                </View>
                <View style={styles.texts}>
                  <Text style={styles.name}>{schedule.name}</Text>
                  <Text style={styles.meta}>
                    {schedule.break_minutes ? `Pause ${schedule.break_minutes} min` : 'Sans pause'}
                    {schedule.late_tolerance_minutes
                      ? ` · Tolerance ${schedule.late_tolerance_minutes} min`
                      : ''}
                  </Text>
                  {schedule.days && schedule.days.length > 0 ? (
                    <Text style={styles.days}>{schedule.days.join(' · ')}</Text>
                  ) : null}
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
  card: { marginBottom: spacing.sm },
  row: { flexDirection: 'row', alignItems: 'center' },
  timeBlock: {
    backgroundColor: `${colors.primary}22`,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    marginRight: spacing.md,
  },
  time: { color: colors.primary, fontSize: typography.caption, fontWeight: '700' },
  texts: { flex: 1 },
  name: { color: colors.text, fontSize: typography.body, fontWeight: '700' },
  meta: { color: colors.textFaint, fontSize: typography.caption, marginTop: 2 },
  days: { color: colors.textMuted, fontSize: typography.caption, marginTop: 2 },
});
