import React, { useState } from 'react';
import {
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Button } from '../../components/Button';
import { TextField } from '../../components/TextField';
import { useAuth } from '../../api/AuthContext';
import { extractErrorMessage } from '../../api/client';
import { colors, radius, spacing, typography } from '../../theme';

export function LoginScreen() {
  const { signIn } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const canSubmit = email.trim().length > 3 && password.length > 0 && !loading;

  async function handleSubmit() {
    if (!canSubmit) return;
    setLoading(true);
    setError(null);
    try {
      await signIn(email, password);
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setLoading(false);
    }
  }

  return (
    <SafeAreaView style={styles.safe}>
      <KeyboardAvoidingView
        style={styles.flex}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      >
        <ScrollView
          contentContainerStyle={styles.content}
          keyboardShouldPersistTaps="handled"
        >
          <View style={styles.brand}>
            <View style={styles.logo}>
              <Text style={styles.logoText}>L</Text>
            </View>
            <Text style={styles.title}>Leopardo</Text>
            <Text style={styles.subtitle}>Espace RH — connexion employe</Text>
          </View>

          <View style={styles.form}>
            <TextField
              label="Adresse e-mail"
              value={email}
              onChangeText={setEmail}
              placeholder="prenom.nom@entreprise.com"
              keyboardType="email-address"
              autoCapitalize="none"
              autoCorrect={false}
              autoComplete="email"
              textContentType="emailAddress"
              returnKeyType="next"
            />

            <TextField
              label="Mot de passe"
              value={password}
              onChangeText={setPassword}
              placeholder="••••••••"
              secureTextEntry
              autoCapitalize="none"
              autoComplete="password"
              textContentType="password"
              returnKeyType="go"
              onSubmitEditing={handleSubmit}
            />

            {error ? (
              <View style={styles.errorBox}>
                <Text style={styles.errorText}>{error}</Text>
              </View>
            ) : null}

            <Button
              label="Se connecter"
              onPress={handleSubmit}
              loading={loading}
              disabled={!canSubmit}
            />

            <Text style={styles.hint}>
              Utilisez vos identifiants Leopardo. Si votre compte est protege par
              la double authentification, un code vous sera demande a l'etape
              suivante.
            </Text>
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.background },
  flex: { flex: 1 },
  content: {
    flexGrow: 1,
    justifyContent: 'center',
    paddingHorizontal: spacing.xl,
    paddingVertical: spacing.xxl,
  },
  brand: { alignItems: 'center', marginBottom: spacing.xxl },
  logo: {
    width: 72,
    height: 72,
    borderRadius: radius.xl,
    backgroundColor: colors.primary,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: spacing.lg,
  },
  logoText: {
    color: colors.white,
    fontSize: 36,
    fontWeight: '800',
  },
  title: {
    color: colors.text,
    fontSize: 30,
    fontWeight: '800',
    letterSpacing: -1,
  },
  subtitle: {
    color: colors.textMuted,
    fontSize: typography.body,
    marginTop: spacing.xs,
  },
  form: {
    backgroundColor: colors.surface,
    borderRadius: radius.xl,
    borderWidth: 1,
    borderColor: colors.border,
    padding: spacing.xl,
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
  hint: {
    color: colors.textFaint,
    fontSize: typography.caption,
    lineHeight: 18,
    marginTop: spacing.lg,
    textAlign: 'center',
  },
});
