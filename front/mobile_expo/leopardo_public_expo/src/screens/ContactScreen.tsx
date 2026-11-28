import React, { useRef, useState } from 'react';
import { KeyboardAvoidingView, Platform, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { ScreenHeader } from '../components/ScreenHeader';
import { Card, Section } from '../components/Card';
import { Button } from '../components/Button';
import { TextField } from '../components/TextField';
import { colors, spacing, typography } from '../theme';
import { newIdempotencyKey, sendContactRequest } from '../api/public';
import { classifyFailure, type PublicApiFailure } from '../api/client';
import { CONTACT_TARGET_TYPES } from '../verticals/registry';
import type { ScreenProps } from '../navigation/types';

/**
 * DEMANDE DE CONTACT — `POST /public/contact-requests`.
 *
 * ## Le contrat reel, pas celui qu'on imagine
 *
 * `StorePublicContactRequest` exige :
 *  - `sender_name`, `sender_email` (**obligatoire**), `sender_phone` ;
 *  - `consent` (`required|accepted`) ;
 *  - `idempotency_key` (8 a 80 caracteres).
 *
 * Envoyer `name`/`email`/`phone` comme on le ferait naturellement produirait
 * un 422. Les noms de champs ci-dessous sont ceux du FormRequest.
 *
 * ## Le type est verifie AVANT l'envoi
 *
 * `target_type` doit appartenir a `PublicProfileLocator::types()`. Un type
 * absent (ex. `pharmacy`) est refuse en 422 : l'ecran le detecte donc en
 * amont et affiche un motif, plutot que de laisser remplir un formulaire pour
 * rien.
 */
export function ContactScreen({ navigation, route }: ScreenProps<'Contact'>) {
  const { targetType, targetSlug, targetName } = route.params;
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [message, setMessage] = useState('');
  const [consent, setConsent] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [failure, setFailure] = useState<PublicApiFailure | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});

  // Conservee jusqu'au succes : un retry reseau renvoie la meme demande.
  const idempotencyKey = useRef(newIdempotencyKey());

  const supported = CONTACT_TARGET_TYPES.has(targetType);

  const submit = async () => {
    const next: Record<string, string> = {};
    if (name.trim().length < 2) next.name = 'Indiquez votre nom.';
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim())) {
      next.email = 'Un email valide est obligatoire pour vous repondre.';
    }
    if (message.trim().length < 10) next.message = 'Precisez votre demande (10 caracteres minimum).';
    if (!consent) next.consent = 'Cochez l\u2019accord pour transmettre vos coordonnees.';
    setErrors(next);
    if (Object.keys(next).length > 0) return;

    setSubmitting(true);
    setFailure(null);
    try {
      const result = await sendContactRequest({
        targetType,
        targetSlug,
        senderName: name.trim(),
        senderEmail: email.trim(),
        senderPhone: phone.trim() || undefined,
        message: message.trim(),
        idempotencyKey: idempotencyKey.current,
      });
      navigation.replace('Confirmation', {
        title: 'Demande envoyee',
        reference: result.reference ?? '\u2014',
        message: `${targetName} a recu votre demande et vous repondra par email.`,
      });
    } catch (error) {
      setFailure(classifyFailure(error));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      <ScreenHeader title="Contacter" subtitle={targetName} onBack={() => navigation.goBack()} />
      <KeyboardAvoidingView style={styles.flex} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <View style={styles.content}>
          {!supported ? (
            <Card style={styles.blockerCard}>
              <Text style={styles.blockerTitle}>Contact indisponible</Text>
              <Text style={styles.blockerText}>
                Ce type d&apos;etablissement n&apos;accepte pas encore les demandes depuis
                l&apos;application.
              </Text>
            </Card>
          ) : (
            <>
              <Section title="Votre demande">
                <TextField
                  label="Nom"
                  required
                  value={name}
                  onChangeText={setName}
                  placeholder="Votre nom"
                  autoCapitalize="words"
                  error={errors.name}
                />
                <TextField
                  label="Email"
                  required
                  value={email}
                  onChangeText={setEmail}
                  placeholder="vous@exemple.com"
                  keyboardType="email-address"
                  autoCapitalize="none"
                  autoCorrect={false}
                  error={errors.email}
                  hint="Obligatoire : c'est par la que la reponse vous parvient."
                />
                <TextField
                  label="Telephone"
                  value={phone}
                  onChangeText={setPhone}
                  placeholder="+225 07 00 00 00 00"
                  keyboardType="phone-pad"
                />
                <TextField
                  label="Message"
                  required
                  value={message}
                  onChangeText={setMessage}
                  placeholder="Decrivez votre besoin\u2026"
                  multiline
                  numberOfLines={5}
                  style={styles.multiline}
                  error={errors.message}
                />
              </Section>

              <Card style={styles.consentCard}>
                <Button
                  label={consent ? '\u2713 J\u2019accepte d\u2019etre recontacte' : 'J\u2019accepte d\u2019etre recontacte'}
                  variant={consent ? 'primary' : 'secondary'}
                  onPress={() => setConsent((v) => !v)}
                />
                <Text style={styles.consentText}>
                  Vos coordonnees sont transmises uniquement a cet etablissement, pour repondre a
                  votre demande.
                </Text>
                {errors.consent ? <Text style={styles.error}>{errors.consent}</Text> : null}
              </Card>

              {failure ? (
                <Card style={styles.failureCard}>
                  <Text style={styles.failureTitle}>Demande non envoyee</Text>
                  <Text style={styles.failureText}>{failure.message}</Text>
                </Card>
              ) : null}

              <Button
                label={submitting ? 'Envoi\u2026' : 'Envoyer ma demande'}
                onPress={submit}
                loading={submitting}
                style={styles.submit}
              />
            </>
          )}
        </View>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.background },
  flex: { flex: 1 },
  content: { paddingHorizontal: spacing.lg, paddingBottom: spacing.xxl },
  multiline: { minHeight: 110, textAlignVertical: 'top' },
  consentCard: { marginTop: spacing.lg, gap: spacing.sm },
  consentText: { color: colors.textFaint, fontSize: typography.caption, lineHeight: 16 },
  error: { color: colors.danger, fontSize: typography.caption },
  blockerCard: { backgroundColor: colors.surfaceAlt, borderColor: colors.border, marginTop: spacing.lg },
  blockerTitle: { color: colors.text, fontSize: typography.subheading, fontWeight: '700' },
  blockerText: { color: colors.textMuted, fontSize: typography.body, marginTop: spacing.xs, lineHeight: 19 },
  failureCard: { borderColor: colors.danger, backgroundColor: '#FEF2F2', marginTop: spacing.lg },
  failureTitle: { color: colors.danger, fontSize: typography.subheading, fontWeight: '700' },
  failureText: { color: colors.danger, fontSize: typography.body, marginTop: spacing.xs, lineHeight: 19 },
  submit: { marginTop: spacing.xl },
});
