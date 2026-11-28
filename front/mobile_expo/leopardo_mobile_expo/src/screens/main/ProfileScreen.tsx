import React from 'react';
import { Alert, Pressable, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useAuth } from '../../api/AuthContext';
import { API_BASE_URL } from '../../api/client';
import { Card, Badge } from '../../components/Card';
import { ScreenHeader } from '../../components/ScreenHeader';
import { fullName, initials } from '../../models/employee';
import { formatDate } from '../../utils/format';
import { colors, radius, spacing, typography } from '../../theme';

type Props = {
  onNavigate: (route: 'Schedules' | 'SalaryAdvances' | 'Notifications' | 'ModulesPlan') => void;
};

export function ProfileScreen({ onNavigate }: Props) {
  const { employee, signOut } = useAuth();

  function confirmSignOut() {
    Alert.alert('Se deconnecter', 'Voulez-vous vraiment vous deconnecter ?', [
      { text: 'Annuler', style: 'cancel' },
      { text: 'Se deconnecter', style: 'destructive', onPress: () => void signOut() },
    ]);
  }

  const rows: { label: string; value: string }[] = [
    { label: 'Matricule', value: employee?.matricule ?? '—' },
    { label: 'E-mail', value: employee?.email ?? '—' },
    { label: 'Telephone', value: employee?.phone ?? '—' },
    { label: 'Poste', value: employee?.job_title ?? employee?.role ?? '—' },
    { label: 'Departement', value: employee?.department ?? '—' },
    { label: 'Site', value: employee?.work_location ?? '—' },
    { label: 'Embauche', value: formatDate(employee?.hire_date) },
    { label: 'Planning', value: employee?.schedule?.name ?? '—' },
  ];

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      <ScreenHeader title="Mon compte" subtitle="Profil et parametres" />

      <View style={styles.content}>
        <Card style={styles.identityCard}>
          <View style={styles.avatar}>
            <Text style={styles.avatarText}>{initials(employee)}</Text>
          </View>
          <Text style={styles.name}>{fullName(employee)}</Text>
          <Text style={styles.role}>{employee?.job_title ?? employee?.role ?? 'Employe'}</Text>
          <Badge
            label={employee?.status ?? 'inconnu'}
            color={employee?.status === 'active' ? colors.success : colors.textMuted}
          />
        </Card>

        <Card style={styles.infoCard}>
          {rows.map((row, index) => (
            <View key={row.label} style={[styles.infoRow, index > 0 && styles.infoRowBorder]}>
              <Text style={styles.infoLabel}>{row.label}</Text>
              <Text style={styles.infoValue} numberOfLines={1}>
                {row.value}
              </Text>
            </View>
          ))}
        </Card>

        <Text style={styles.sectionTitle}>Raccourcis</Text>
        <Card style={styles.linksCard}>
          <LinkRow label="Mes plannings" onPress={() => onNavigate('Schedules')} />
          <LinkRow label="Mes avances" onPress={() => onNavigate('SalaryAdvances')} />
          <LinkRow label="Notifications" onPress={() => onNavigate('Notifications')} />
          <LinkRow label="Modules & plan" onPress={() => onNavigate('ModulesPlan')} />
        </Card>

        <Text style={styles.sectionTitle}>Application</Text>
        <Card style={styles.infoCard}>
          <View style={styles.infoRow}>
            <Text style={styles.infoLabel}>Version</Text>
            <Text style={styles.infoValue}>1.0.0</Text>
          </View>
          <View style={[styles.infoRow, styles.infoRowBorder]}>
            <Text style={styles.infoLabel}>API</Text>
            <Text style={styles.infoValue} numberOfLines={1}>
              {API_BASE_URL}
            </Text>
          </View>
        </Card>

        <Pressable
          accessibilityRole="button"
          onPress={confirmSignOut}
          style={({ pressed }) => [styles.signOut, pressed && styles.signOutPressed]}
        >
          <Text style={styles.signOutText}>Se deconnecter</Text>
        </Pressable>
      </View>
    </SafeAreaView>
  );
}

function LinkRow({ label, onPress }: { label: string; onPress: () => void }) {
  return (
    <Pressable
      accessibilityRole="button"
      onPress={onPress}
      style={({ pressed }) => [styles.linkRow, pressed && styles.linkRowPressed]}
    >
      <Text style={styles.linkLabel}>{label}</Text>
      <Text style={styles.linkChevron}>›</Text>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.background },
  content: { paddingHorizontal: spacing.lg, paddingBottom: spacing.xxl },
  identityCard: { alignItems: 'center', paddingVertical: spacing.xl },
  avatar: {
    width: 72,
    height: 72,
    borderRadius: radius.pill,
    backgroundColor: colors.primary,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: spacing.md,
  },
  avatarText: { color: colors.white, fontSize: 26, fontWeight: '800' },
  name: { color: colors.text, fontSize: typography.heading, fontWeight: '700' },
  role: { color: colors.textMuted, fontSize: typography.body, marginTop: 2, marginBottom: spacing.md },
  infoCard: { marginTop: spacing.lg, paddingVertical: spacing.xs },
  infoRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: spacing.md,
    gap: spacing.lg,
  },
  infoRowBorder: { borderTopWidth: 1, borderTopColor: colors.border },
  infoLabel: { color: colors.textMuted, fontSize: typography.caption },
  infoValue: { color: colors.text, fontSize: typography.body, fontWeight: '600', flexShrink: 1, textAlign: 'right' },
  sectionTitle: {
    color: colors.text,
    fontSize: typography.subheading,
    fontWeight: '700',
    marginTop: spacing.xl,
    marginBottom: spacing.md,
  },
  linksCard: { paddingVertical: spacing.xs },
  linkRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: spacing.md,
  },
  linkRowPressed: { opacity: 0.7 },
  linkLabel: { color: colors.text, fontSize: typography.body },
  linkChevron: { color: colors.textFaint, fontSize: 20 },
  signOut: {
    marginTop: spacing.xl,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: `${colors.danger}66`,
    backgroundColor: `${colors.danger}14`,
    paddingVertical: spacing.lg,
    alignItems: 'center',
  },
  signOutPressed: { opacity: 0.8 },
  signOutText: { color: colors.danger, fontSize: typography.subheading, fontWeight: '600' },
});
