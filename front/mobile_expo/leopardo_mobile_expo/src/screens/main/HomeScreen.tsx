import React, { useCallback, useEffect, useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useAuth } from '../../api/AuthContext';
import { fetchDashboardSummary, fetchRecentActivity, fetchTodayTasks } from '../../api/hr';
import { fetchDailySummary } from '../../api/attendance';
import { extractErrorMessage } from '../../api/client';
import { ScreenState } from '../../components/ScreenState';
import { Card, StatCard, Badge, EmptyState } from '../../components/Card';
import { fullName, initials, canManageTeam } from '../../models/employee';
import type { DashboardSummary, RecentActivity } from '../../models/dashboard';
import { activityLabel } from '../../models/dashboard';
import type { DailySummary } from '../../models/summary';
import type { ProjectTask } from '../../models/task';
import { isTaskDone } from '../../models/task';
import { formatMoney } from '../../models/payroll';
import { formatHours } from '../../models/attendance';
import { relativeTime } from '../../utils/format';
import { colors, radius, spacing, typography } from '../../theme';

type Props = {
  onNavigate: (route: 'Attendance' | 'Absences' | 'Payroll' | 'Tasks' | 'Team') => void;
};

export function HomeScreen({ onNavigate }: Props) {
  const { employee, refreshProfile } = useAuth();
  const [summary, setSummary] = useState<DashboardSummary | null>(null);
  const [daily, setDaily] = useState<DailySummary | null>(null);
  const [activity, setActivity] = useState<RecentActivity[]>([]);
  const [tasks, setTasks] = useState<ProjectTask[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async (isRefresh = false) => {
    if (isRefresh) setRefreshing(true);
    else setLoading(true);
    setError(null);
    try {
      const [summaryData, dailyData, activityData, taskData] = await Promise.all([
        fetchDashboardSummary().catch(() => null),
        fetchDailySummary().catch(() => null),
        fetchRecentActivity(5).catch(() => []),
        fetchTodayTasks().catch(() => []),
      ]);
      setSummary(summaryData);
      setDaily(dailyData);
      setActivity(activityData);
      setTasks(taskData);
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

  const isManager = canManageTeam(employee);
  const openTasks = tasks.filter((task) => !isTaskDone(task));
  const currency = daily?.currency ?? employee?.currency ?? 'DA';

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      <View style={styles.topBar}>
        <View style={styles.identity}>
          <View style={styles.avatar}>
            <Text style={styles.avatarText}>{initials(employee)}</Text>
          </View>
          <View style={styles.identityTexts}>
            <Text style={styles.greeting}>
              Bonjour, {employee?.first_name || 'collaborateur'}
            </Text>
            <Text style={styles.role}>
              {employee?.job_title || employee?.role || 'Employe'}
              {employee?.department ? ` · ${employee.department}` : ''}
            </Text>
          </View>
        </View>
        <Badge
          label={employee?.work_state_label ?? 'Hors ligne'}
          color={employee?.work_state === 'online' ? colors.success : colors.textMuted}
        />
      </View>

      <ScreenState
        loading={loading}
        error={error}
        onRetry={() => load()}
        refreshing={refreshing}
        onRefresh={() => {
          load(true);
          refreshProfile().catch(() => undefined);
        }}
      >
        {/* Resume du jour */}
        <Card style={styles.heroCard}>
          <Text style={styles.heroLabel}>Aujourd'hui</Text>
          <Text style={styles.heroValue}>
            {formatHours(daily?.hours_worked ?? 0)}
          </Text>
          <View style={styles.heroRow}>
            <View style={styles.heroItem}>
              <Text style={styles.heroItemLabel}>Heures sup.</Text>
              <Text style={styles.heroItemValue}>
                {formatHours(daily?.overtime_hours ?? 0)}
              </Text>
            </View>
            <View style={styles.heroDivider} />
            <View style={styles.heroItem}>
              <Text style={styles.heroItemLabel}>Estimation</Text>
              <Text style={styles.heroItemValue}>
                {formatMoney(daily?.total_estimated ?? 0, currency)}
              </Text>
            </View>
          </View>
        </Card>

        {/* Actions rapides */}
        <Text style={styles.sectionTitle}>Actions rapides</Text>
        <View style={styles.quickGrid}>
          <QuickAction
            label="Pointer"
            hint="Entree / sortie"
            color={colors.primary}
            onPress={() => onNavigate('Attendance')}
          />
          <QuickAction
            label="Absence"
            hint="Declarer / consulter"
            color={colors.warning}
            onPress={() => onNavigate('Absences')}
          />
          <QuickAction
            label="Paie"
            hint="Bulletins & avances"
            color={colors.accent}
            onPress={() => onNavigate('Payroll')}
          />
          <QuickAction
            label="Taches"
            hint={`${openTasks.length} en cours`}
            color={colors.success}
            onPress={() => onNavigate('Tasks')}
          />
        </View>

        {/* Compteurs equipe (manager/RH) */}
        {isManager && summary ? (
          <>
            <Text style={styles.sectionTitle}>Vue equipe</Text>
            <View style={styles.statsRow}>
              <StatCard
                label="Employes actifs"
                value={summary.employees_active ?? 0}
                hint={`${summary.employees_total ?? 0} au total`}
                accent={colors.primary}
              />
              <StatCard
                label="Pointages du jour"
                value={summary.today_attendance ?? 0}
                accent={colors.success}
              />
            </View>
            <View style={[styles.statsRow, styles.statsRowSpaced]}>
              <StatCard
                label="Absences en attente"
                value={summary.pending_absences ?? 0}
                accent={colors.warning}
              />
              <StatCard
                label="Departements"
                value={summary.departments ?? 0}
                accent={colors.accent}
              />
            </View>
            <Pressable style={styles.teamLink} onPress={() => onNavigate('Team')}>
              <Text style={styles.teamLinkText}>Voir mon equipe →</Text>
            </Pressable>
          </>
        ) : null}

        {/* Taches du jour */}
        <Text style={styles.sectionTitle}>Taches du jour</Text>
        {openTasks.length === 0 ? (
          <EmptyState
            title="Aucune tache en cours"
            message="Vos taches assignees apparaitront ici."
          />
        ) : (
          openTasks.slice(0, 4).map((task) => (
            <Card key={task.id} style={styles.taskCard}>
              <View style={styles.taskRow}>
                <View style={styles.taskDot} />
                <View style={styles.taskTexts}>
                  <Text style={styles.taskTitle}>{task.title}</Text>
                  {task.project?.name ? (
                    <Text style={styles.taskMeta}>{task.project.name}</Text>
                  ) : null}
                </View>
              </View>
            </Card>
          ))
        )}

        {/* Activite recente */}
        <Text style={styles.sectionTitle}>Activite recente</Text>
        {activity.length === 0 ? (
          <EmptyState title="Aucune activite recente" />
        ) : (
          <Card>
            {activity.map((item, index) => (
              <View
                key={`${item.id ?? index}`}
                style={[styles.activityRow, index > 0 && styles.activityRowBorder]}
              >
                <View style={styles.activityDot} />
                <View style={styles.activityTexts}>
                  <Text style={styles.activityLabel}>{activityLabel(item)}</Text>
                  <Text style={styles.activityTime}>
                    {relativeTime(item.created_at)}
                  </Text>
                </View>
              </View>
            ))}
          </Card>
        )}
      </ScreenState>
    </SafeAreaView>
  );
}

function QuickAction({
  label,
  hint,
  color,
  onPress,
}: {
  label: string;
  hint: string;
  color: string;
  onPress: () => void;
}) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={`${label} — ${hint}`}
      onPress={onPress}
      style={({ pressed }) => [styles.quickCard, pressed && styles.quickCardPressed]}
    >
      <View style={[styles.quickIcon, { backgroundColor: `${color}22` }]}>
        <View style={[styles.quickIconInner, { backgroundColor: color }]} />
      </View>
      <Text style={styles.quickLabel}>{label}</Text>
      <Text style={styles.quickHint}>{hint}</Text>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.background },
  topBar: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: spacing.lg,
    paddingTop: spacing.md,
    paddingBottom: spacing.sm,
  },
  identity: { flexDirection: 'row', alignItems: 'center', flex: 1 },
  avatar: {
    width: 44,
    height: 44,
    borderRadius: radius.pill,
    backgroundColor: colors.primary,
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarText: { color: colors.white, fontWeight: '700', fontSize: 16 },
  identityTexts: { marginLeft: spacing.md, flex: 1 },
  greeting: { color: colors.text, fontSize: typography.subheading, fontWeight: '700' },
  role: { color: colors.textMuted, fontSize: typography.caption, marginTop: 1 },
  heroCard: {
    backgroundColor: colors.surfaceAlt,
    marginBottom: spacing.lg,
  },
  heroLabel: {
    color: colors.textMuted,
    fontSize: typography.caption,
    textTransform: 'uppercase',
    letterSpacing: 0.8,
  },
  heroValue: {
    color: colors.text,
    fontSize: 40,
    fontWeight: '800',
    letterSpacing: -1.5,
    marginTop: spacing.xs,
  },
  heroRow: { flexDirection: 'row', alignItems: 'center', marginTop: spacing.md },
  heroItem: { flex: 1 },
  heroItemLabel: { color: colors.textFaint, fontSize: typography.caption },
  heroItemValue: {
    color: colors.text,
    fontSize: typography.subheading,
    fontWeight: '600',
    marginTop: 2,
  },
  heroDivider: {
    width: 1,
    height: 32,
    backgroundColor: colors.border,
    marginHorizontal: spacing.lg,
  },
  sectionTitle: {
    color: colors.text,
    fontSize: typography.subheading,
    fontWeight: '700',
    marginTop: spacing.lg,
    marginBottom: spacing.md,
  },
  quickGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.md,
  },
  quickCard: {
    flexBasis: '47%',
    flexGrow: 1,
    backgroundColor: colors.surface,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.border,
    padding: spacing.lg,
  },
  quickCardPressed: { opacity: 0.8 },
  quickIcon: {
    width: 36,
    height: 36,
    borderRadius: radius.md,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: spacing.md,
  },
  quickIconInner: { width: 14, height: 14, borderRadius: 4 },
  quickLabel: { color: colors.text, fontSize: typography.body, fontWeight: '700' },
  quickHint: { color: colors.textFaint, fontSize: typography.caption, marginTop: 2 },
  statsRow: { flexDirection: 'row', gap: spacing.md },
  statsRowSpaced: { marginTop: spacing.md },
  teamLink: { paddingVertical: spacing.md },
  teamLinkText: { color: colors.primary, fontSize: typography.body, fontWeight: '600' },
  taskCard: { marginBottom: spacing.sm, paddingVertical: spacing.md },
  taskRow: { flexDirection: 'row', alignItems: 'center' },
  taskDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: colors.primary,
    marginRight: spacing.md,
  },
  taskTexts: { flex: 1 },
  taskTitle: { color: colors.text, fontSize: typography.body, fontWeight: '600' },
  taskMeta: { color: colors.textFaint, fontSize: typography.caption, marginTop: 2 },
  activityRow: { flexDirection: 'row', alignItems: 'flex-start', paddingVertical: spacing.md },
  activityRowBorder: { borderTopWidth: 1, borderTopColor: colors.border },
  activityDot: {
    width: 6,
    height: 6,
    borderRadius: 3,
    backgroundColor: colors.accent,
    marginTop: 6,
    marginRight: spacing.md,
  },
  activityTexts: { flex: 1 },
  activityLabel: { color: colors.text, fontSize: typography.body },
  activityTime: { color: colors.textFaint, fontSize: typography.caption, marginTop: 2 },
});
