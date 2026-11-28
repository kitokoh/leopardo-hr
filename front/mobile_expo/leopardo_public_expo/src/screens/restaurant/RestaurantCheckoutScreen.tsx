import React, { useRef, useState } from 'react';
import { KeyboardAvoidingView, Platform, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { ScreenHeader } from '../../components/ScreenHeader';
import { Card, InfoRow, Section } from '../../components/Card';
import { Button } from '../../components/Button';
import { TextField } from '../../components/TextField';
import { colors, radius, spacing, typography } from '../../theme';
import { useCart } from '../../hooks/useCart';
import { createRestaurantOrder, newIdempotencyKey } from '../../api/public';
import { classifyFailure, type PublicApiFailure } from '../../api/client';
import { formatMoney } from '../../utils/format';
import type { ScreenProps } from '../../navigation/types';

/**
 * CHECKOUT RESTAURANT.
 *
 * ## La cle d'idempotence survit au retry
 *
 * Elle est generee UNE fois (`useRef`) et reutilisee tant que la commande
 * n'a pas abouti. Un double tap ou un retry reseau renvoie donc la meme
 * commande (201 puis 200), jamais deux repas prepares.
 *
 * ## Le total affiche n'est pas le total contractuel
 *
 * Le panier local donne une estimation ; le serveur relit les prix en base.
 * L'ecran le dit explicitement pour ne pas laisser croire a un prix garanti.
 */
export function RestaurantCheckoutScreen({ navigation, route }: ScreenProps<'RestaurantCheckout'>) {
  const { slug, restaurantName } = route.params;
  const cart = useCart();
  const [name, setName] = useState('');
  const [phone, setPhone] = useState('');
  const [note, setNote] = useState('');
  const [orderType, setOrderType] = useState<'pickup' | 'delivery'>('pickup');
  const [submitting, setSubmitting] = useState(false);
  const [failure, setFailure] = useState<PublicApiFailure | null>(null);
  const [errors, setErrors] = useState<{ name?: string; phone?: string }>({});

  const idempotencyKey = useRef(newIdempotencyKey());

  const submit = async () => {
    const nextErrors: { name?: string; phone?: string } = {};
    if (name.trim().length < 2) nextErrors.name = 'Indiquez votre nom.';
    if (phone.trim().length < 6) nextErrors.phone = 'Indiquez un numero joignable.';
    setErrors(nextErrors);
    if (Object.keys(nextErrors).length > 0) return;
    if (cart.lines.length === 0) return;

    setSubmitting(true);
    setFailure(null);
    try {
      const result = await createRestaurantOrder({
        slug,
        customerName: name.trim(),
        customerPhone: phone.trim(),
        orderType,
        note: note.trim() || undefined,
        items: cart.lines.map((line) => ({ product_code: line.key, quantity: line.quantity })),
        idempotencyKey: idempotencyKey.current,
      });
      cart.clear();
      navigation.replace('Confirmation', {
        title: 'Commande envoyee',
        reference: result.reference,
        code: result.tracking_secret,
        message: `${restaurantName} a recu votre commande. Elle sera preparee des que possible.`,
        totalMinor: result.total_minor,
        currency: result.currency,
      });
    } catch (error) {
      setFailure(classifyFailure(error));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      <ScreenHeader title="Votre commande" subtitle={restaurantName} onBack={() => navigation.goBack()} />
      <KeyboardAvoidingView
        style={styles.flex}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      >
        <View style={styles.content}>
          <Section title="Recapitulatif">
            <Card>
              {cart.lines.map((line) => (
                <InfoRow
                  key={line.key}
                  label={`${line.quantity} \u00d7 ${line.label}`}
                  value={formatMoney(line.unitMinor * line.quantity, 'XOF')}
                />
              ))}
              <View style={styles.divider} />
              <InfoRow label="Estimation" value={formatMoney(cart.totalMinor, 'XOF')} strong />
              <Text style={styles.disclaimer}>
                Le montant definitif est calcule par le restaurant (prix et taxes a jour).
              </Text>
            </Card>
          </Section>

          <Section title="Retrait ou livraison">
            <View style={styles.typeRow}>
              {(['pickup', 'delivery'] as const).map((value) => {
                const active = orderType === value;
                return (
                  <Button
                    key={value}
                    label={value === 'pickup' ? 'A emporter' : 'Livraison'}
                    variant={active ? 'primary' : 'secondary'}
                    onPress={() => setOrderType(value)}
                    style={styles.typeButton}
                  />
                );
              })}
            </View>
          </Section>

          <Section title="Vos coordonnees">
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
              label="Telephone"
              required
              value={phone}
              onChangeText={setPhone}
              placeholder="+225 07 00 00 00 00"
              keyboardType="phone-pad"
              error={errors.phone}
            />
            <TextField
              label="Note pour la cuisine"
              value={note}
              onChangeText={setNote}
              placeholder="Sans piment, allergie\u2026"
              multiline
              numberOfLines={3}
              style={styles.multiline}
            />
          </Section>

          {failure ? (
            <Card style={styles.failureCard}>
              <Text style={styles.failureTitle}>Commande non envoyee</Text>
              <Text style={styles.failureText}>{failure.message}</Text>
            </Card>
          ) : null}

          <Button
            label={submitting ? 'Envoi\u2026' : 'Envoyer la commande'}
            onPress={submit}
            loading={submitting}
            disabled={cart.lines.length === 0}
            style={styles.submit}
          />
        </View>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.background },
  flex: { flex: 1 },
  content: { paddingHorizontal: spacing.lg, paddingBottom: spacing.xxl },
  divider: { height: 1, backgroundColor: colors.border, marginVertical: spacing.sm },
  disclaimer: { color: colors.textFaint, fontSize: typography.caption, marginTop: spacing.sm, lineHeight: 16 },
  typeRow: { flexDirection: 'row', gap: spacing.md },
  typeButton: { flex: 1 },
  multiline: { minHeight: 80, textAlignVertical: 'top' },
  failureCard: { borderColor: colors.danger, backgroundColor: '#FEF2F2', marginTop: spacing.lg },
  failureTitle: { color: colors.danger, fontSize: typography.subheading, fontWeight: '700' },
  failureText: { color: colors.danger, fontSize: typography.body, marginTop: spacing.xs, lineHeight: 19 },
  submit: { marginTop: spacing.xl },
});
