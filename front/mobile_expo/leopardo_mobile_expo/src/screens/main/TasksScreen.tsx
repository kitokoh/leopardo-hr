import React, { useCallback, useEffect, useState } from 'react';
import { Alert, Pressable, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { completeTask, fetchTodayTasks } from '../../api/hr';
import { extractErrorMessage } from '../../api/client';
import { ScreenState } from '../../components/ScreenState';
import { Card, Badge, EmptyState } from '../../components/Card';
import { ScreenHeader } from '../../components/ScreenHeader';
import type { ProjectTask } from '../../models/task';
import { isTaskDone } from '../../models/task';
import { formatDate } from '../../utils/format';
import { colors, radius, spacing, typography } from '../../theme';

export function TasksScreen() {
  const [tasks, setTasks] = useState<ProjectTask[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [busyId, setBusyId] = useState<number | null>(null);

  const load = useCallback(async (isRefresh = false) => {
    if (isRefresh) setRefreshing(true);
    else setLoading(true);
    setError(null);
    try {
      setTasks(await fetchTodayTasks());
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

  async function handleComplete(task: ProjectTask) {
    setBusyId(task.id);
    try {
      await completeTask(task.id);
      await load(true);
    } catch (err) {
      Alert.alert('Impossible de terminer la tache', extractErrorMessage(err));
    } finally {
      setBusyId(null);
    }
  }

  const open = tasks.filter((task) => !isTaskDone(task));
  const done = tasks.filter(isTaskDone);

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      <ScreenHeader
        title="Taches"
        subtitle={`${open.length} en cours · ${done.length} terminee${done.length > 1 ? 's' : ''}`}
      />

      <ScreenState
        loading={loading}
        error={error}
        onRetry={() => load()}
        refreshing={refreshing}
        onRefresh={() => load(true)}
      >
        {tasks.length === 0 ? (
          <EmptyState
            title="Aucune tache aujourd'hui"
            message="Les taches qui vous sont assignees apparaitront ici."
          />
        ) : (
          tasks.map((task) => {
            const completed = isTaskDone(task);
            return (
              <Card key={task.id} style={styles.card}>
                <View style={styles.row}>
                  <Pressable
                    accessibilityRole="checkbox"
                    accessibilityState={{ checked: completed }}
                    accessibilityLabel={`Terminer ${task.title}`}
                    disabled={completed || busyId === task.id}
                    onPress={() => handleComplete(task)}
                    style={[styles.checkbox, completed && styles.checkboxDone]}
                  >
                    {completed ? <Text style={styles.checkmark}>✓</Text> : null}
                  </Pressable>

                  <View style={styles.texts}>
                    <Text style={[styles.title, completed && styles.titleDone]}>
                      {task.title}
                    </Text>
                    {task.description ? (
                      <Text style={styles.description} numberOfLines={2}>
                        {task.description}
                      </Text>
                    ) : null}
                    <View style={styles.metaRow}>
                      {task.project?.name ? (
                        <Text style={styles.meta}>{task.project.name}</Text>
                      ) : null}
                      {task.due_date ? (
                        <Text style={styles.meta}>· {formatDate(task.due_date)}</Text>
                      ) : null}
                    </View>
                  </View>

                  {task.priority ? (
                    <Badge
                      label={task.priority}
                      color={
                        task.priority === 'high'
                          ? colors.danger
                          : task.priority === 'medium'
                            ? colors.warning
                            : colors.textMuted
                      }
                    />
                  ) : null}
                </View>
              </Card>
            );
          })
        )}
      </ScreenState>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.background },
  card: { marginBottom: spacing.sm },
  row: { flexDirection: 'row', alignItems: 'flex-start' },
  checkbox: {
    width: 24,
    height: 24,
    borderRadius: radius.sm,
    borderWidth: 2,
    borderColor: colors.border,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: spacing.md,
    marginTop: 1,
  },
  checkboxDone: { backgroundColor: colors.success, borderColor: colors.success },
  checkmark: { color: colors.white, fontSize: 14, fontWeight: '800' },
  texts: { flex: 1 },
  title: { color: colors.text, fontSize: typography.body, fontWeight: '600' },
  titleDone: { color: colors.textFaint, textDecorationLine: 'line-through' },
  description: { color: colors.textMuted, fontSize: typography.caption, marginTop: 2 },
  metaRow: { flexDirection: 'row', gap: spacing.xs, marginTop: spacing.xs },
  meta: { color: colors.textFaint, fontSize: typography.caption },
});
