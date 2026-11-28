import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { StyleSheet, Text, TextInput, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { fetchEmployees } from '../../api/hr';
import { extractErrorMessage } from '../../api/client';
import { ScreenState } from '../../components/ScreenState';
import { Card, Badge, EmptyState } from '../../components/Card';
import { ScreenHeader } from '../../components/ScreenHeader';
import type { Employee } from '../../models/employee';
import { fullName, initials } from '../../models/employee';
import { colors, radius, spacing, typography } from '../../theme';

export function TeamScreen() {
  const [employees, setEmployees] = useState<Employee[]>([]);
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async (isRefresh = false) => {
    if (isRefresh) setRefreshing(true);
    else setLoading(true);
    setError(null);
    try {
      setEmployees(await fetchEmployees());
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

  // Filtrage local : la liste est deja bornee a 100 elements par l'API.
  const filtered = useMemo(() => {
    const term = search.trim().toLowerCase();
    if (!term) return employees;
    return employees.filter((employee) => {
      const haystack = [
        employee.first_name,
        employee.last_name,
        employee.email,
        employee.matricule ?? '',
        employee.department ?? '',
        employee.job_title ?? '',
      ]
        .join(' ')
        .toLowerCase();
      return haystack.includes(term);
    });
  }, [employees, search]);

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      <ScreenHeader
        title="Equipe"
        subtitle={`${employees.length} collaborateur${employees.length > 1 ? 's' : ''}`}
      />

      <View style={styles.searchWrapper}>
        <TextInput
          value={search}
          onChangeText={setSearch}
          placeholder="Rechercher un collaborateur"
          placeholderTextColor={colors.textFaint}
          style={styles.search}
          autoCapitalize="none"
          autoCorrect={false}
        />
      </View>

      <ScreenState
        loading={loading}
        error={error}
        onRetry={() => load()}
        refreshing={refreshing}
        onRefresh={() => load(true)}
      >
        {filtered.length === 0 ? (
          <EmptyState
            title="Aucun collaborateur"
            message={
              search
                ? 'Aucun resultat pour cette recherche.'
                : 'La liste de votre equipe est vide.'
            }
          />
        ) : (
          filtered.map((employee) => (
            <Card key={employee.id} style={styles.card}>
              <View style={styles.row}>
                <View style={styles.avatar}>
                  <Text style={styles.avatarText}>{initials(employee)}</Text>
                </View>
                <View style={styles.texts}>
                  <Text style={styles.name}>{fullName(employee)}</Text>
                  <Text style={styles.meta}>
                    {employee.job_title || employee.role || 'Employe'}
                    {employee.department ? ` · ${employee.department}` : ''}
                  </Text>
                  {employee.matricule ? (
                    <Text style={styles.matricule}>Matricule {employee.matricule}</Text>
                  ) : null}
                </View>
                <Badge
                  label={employee.status}
                  color={employee.status === 'active' ? colors.success : colors.textMuted}
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
  searchWrapper: { paddingHorizontal: spacing.lg, paddingBottom: spacing.md },
  search: {
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius.md,
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.md,
    color: colors.text,
    fontSize: typography.body,
  },
  card: { marginBottom: spacing.sm },
  row: { flexDirection: 'row', alignItems: 'center' },
  avatar: {
    width: 42,
    height: 42,
    borderRadius: radius.pill,
    backgroundColor: colors.surfaceAlt,
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: spacing.md,
  },
  avatarText: { color: colors.text, fontWeight: '700', fontSize: 14 },
  texts: { flex: 1 },
  name: { color: colors.text, fontSize: typography.body, fontWeight: '700' },
  meta: { color: colors.textMuted, fontSize: typography.caption, marginTop: 2 },
  matricule: { color: colors.textFaint, fontSize: 10, marginTop: 2 },
});
