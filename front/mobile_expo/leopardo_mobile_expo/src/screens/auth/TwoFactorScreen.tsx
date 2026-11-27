import React, { useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Button } from '../../components/Button';
import { TextField } from '../../components/TextField';
import { useAuth } from '../../api/AuthContext';
import { extractErrorMessage } from '../../api/client';
import { colors, radius, spacing, typography } from '../../theme';

export function TwoFactorScreen() {
  const { submitTwoFactor, cancelTwoFactor } = useAuth();
  const [code, setCode] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const canSubmit = code.trim().length >= 6 && !loading;

  async function handleSubmit() {
    if (!canSubmit) return;
    setLoading(true);
    setError(null);
    try {
      await submitTwoFactor(code);
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setLoading(false);
    }
  }

  return (
    <SafeAreaView style={styles.safe}>
      <View style={styles.content}>
        <Text style={styles.title}>Verification en deux etapes</Text>
        <Text style={styles.subtitle}>
          Saisissez le code a 6 chiffres genere par votre application
          d'authentification.
        </Text>

        <View style={styles.form}>
          <TextField
            label="Code de verification"
            value={code}
            onChangeText={(value) => setCode(value.replace(/[^0-9]/g, ''))}
            placeholder="000000"
            keyboardType="number-pad"
            maxLength={6}
            autoFocus
            style={styles.codeInput}
            onSubmitEditing={handleSubmit}
          />

          {error ? (
            <View style={styles.errorBox}>
              <Text style={styles.errorText}>{error}</Text>
            </View>
          ) : null}

          <Button
            label="Valider"
            onPress={handleSubmit}
            loading={loading}
            disabled={!canSubmit}
          />
          <Button
            label="Revenir a la connexion"
            onPress={cancelTwoFactor}
            variant="ghost"
            style={styles.back}
          />
        </View>
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.background },
  content: {
    flex: 1,
    justifyContent: 'center',
    paddingHorizontal: spacing.xl,
  },
  title: {
    color: colors.text,
    fontSize: 24,
    fontWeight: '700',
    letterSpacing: -0.5,
  },
  subtitle: {
    color: colors.textMuted,
    fontSize: typography.body,
    lineHeight: 20,
    marginTop: spacing.sm,
    marginBottom: spacing.xl,
  },
  form: {
    backgroundColor: colors.surface,
    borderRadius: radius.xl,
    borderWidth: 1,
    borderColor: colors.border,
    padding: spacing.xl,
  },
  codeInput: {
    fontSize: 28,
    letterSpacing: 10,
    textAlign: 'center',
    fontWeight: '700',
  },
  errorBox: {
    backgroundColor: `${colors.danger}18`,
    borderWidth: 1,
    borderColor: `${colors.danger}55`,
    borderRadius: radius.md,
    padding: spacing.md,
    marginBottom: spacing.lg,
  },
  errorText: { color: colors.danger, fontSize: typography.body },
  back: { marginTop: spacing.sm },
});
