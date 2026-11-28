import React, { useRef, useState } from 'react';
import { KeyboardAvoidingView, Platform, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { ScreenHeader } from '../../components/ScreenHeader';
import { Card, InfoRow, Section } from '../../components/Card';
import { Button } from '../../components/Button';
import { TextField } from '../../components/TextField';
import { colors, spacing, typography } from '../../theme';
import { useCart } from '../../hooks/useCart';
import { createMarketOrder, newIdempotencyKey } from '../../api/public';
import { classifyFailure, type PublicApiFailure } from '../../api/client';
import { formatMoney } from '../../utils/format';
import type { ScreenProps } from '../../navigation/types';

/**
 * CHECKOUT BOUTIQUE — `POST /public/market/orders`.
 *
 * Contrat : 1 commande = 1 vendeur, `payment_method` limite a `cash|online`,
 * `idempotency_key` OBLIGATOIRE. Le paiement en ligne renvoie un
 * `checkout_url` : l'app l'affiche comme lien a ouvrir, elle ne pretend pas
 * avoir encaisse.
 */
export function ShopCheckoutScreen({ navigation, route }: ScreenProps<'ShopCheckout'>) {
  const { sellerSlug, sellerName } = route.params;
  const cart = useCart();
  const [name, setName] = useState('');
  const [phone, setPhone] = useState('');
  const [email, setEmail] = useState('');
  const [address, setAddress] = useState('');
  const [city, setCity] = useState('');
  const [notes, setNotes] = useState('');
  const [payment, setPayment] = useState<'cash' | 'online'>('cash');
  const [submitting, setSubmitting] = useState(false);
  const [failure, setFailure] = useState<PublicApiFailure | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});

  const idempotencyKey = useRef(newIdempotencyKey());

  const submit = async () => {
    const next: Record<string, string> = {};
    if (name.trim().length < 2) next.name = 'Indiquez votre nom.';
    if (phone.trim().length < 6) next.phone = 'Indiquez un numero joignable.';
    if (address.trim().length < 5) next.address = 'Indiquez une adresse de livraison.';
    if (city.trim().length < 2) next.city = 'Indiquez la ville.';
    setErrors(next);
    if (Object.keys(next).length > 0) return;
    if (cart.lines.length === 0) return;

    setSubmitting(true);
    setFailure(null);
    try {
      const result = await createMarketOrder({
        seller: sellerSlug,
        items: cart.lines.map((line) => ({ product_id: Number(line.key), quantity: line.quantity })),
        customer: { name: name.trim(), phone: phone.trim(), email: email.trim() || undefined },
        delivery: { address: address.trim(), city: city.trim(), notes: notes.trim() || undefined },
        paymentMethod: payment,
        idempotencyKey: idempotencyKey.current,
      });
      cart.clear();
      navigation.replace('Confirmation', {
        title: 'Commande enregistree',
        reference: result.reference,
        code: result.tracking_token,
        message:
          payment === 'online' && result.checkout_url
            ? `${sellerName} a recu votre commande. Reglez-la avec le lien de paiement securise.`
            : `${sellerName} a recu votre commande. Le paiement se fera a la livraison.`,
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
      <ScreenHeader title="Commande" subtitle={sellerName} onBack={() => navigation.goBack()} />
      <KeyboardAvoidingView style={styles.flex} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <View style={styles.content}>
          <Section title="Panier">
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
                Le montant definitif est calcule par la boutique (prix a jour).
              </Text>
            </Card>
          </Section>

          <Section title="Livraison">
            <TextField label="Adresse" required value={address} onChangeText={setAddress} placeholder="Rue, quartier, repere" error={errors.address} />
            <TextField label="Ville" required value={city} onChangeText={setCity} placeholder="Abidjan" error={errors.city} />
            <TextField label="Instructions" value={notes} onChangeText={setNotes} placeholder="Etage, gardien\u2026" multiline numberOfLines={2} style={styles.multiline} />
          </Section>

          <Section title="Vos coordonnees">
            <TextField label="Nom" required value={name} onChangeText={setName} placeholder="Votre nom" autoCapitalize="words" error={errors.name} />
            <TextField label="Telephone" required value={phone} onChangeText={setPhone} placeholder="+225 07 00 00 00 00" keyboardType="phone-pad" error={errors.phone} />
            <TextField label="Email" value={email} onChangeText={setEmail} placeholder="vous@exemple.com" keyboardType="email-address" autoCapitalize="none" />
          </Section>

          <Section title="Paiement">
            <View style={styles.payRow}>
              <Button label="A la livraison" variant={payment === 'cash' ? 'primary' : 'secondary'} onPress={() => setPayment('cash')} style={styles.payButton} />
              <Button label="En ligne" variant={payment === 'online' ? 'primary' : 'secondary'} onPress={() => setPayment('online')} style={styles.payButton} />
            </View>
            {payment === 'online' ? (
              <Text style={styles.payHint}>
                Un lien de paiement securise vous sera transmis apres validation de la commande.
              </Text>
            ) : null}
          </Section>

          {failure ? (
            <Card style={styles.failureCard}>
              <Text style={styles.failureTitle}>Commande non envoyee</Text>
              <Text style={styles.failureText}>{failure.message}</Text>
            </Card>
          ) : null}

          <Button
            label={submitting ? 'Envoi\u2026' : 'Valider la commande'}
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
  multiline: { minHeight: 64, textAlignVertical: 'top' },
  payRow: { flexDirection: 'row', gap: spacing.md },
  payButton: { flex: 1 },
  payHint: { color: colors.textMuted, fontSize: typography.caption, marginTop: spacing.md, lineHeight: 17 },
  failureCard: { borderColor: colors.danger, backgroundColor: '#FEF2F2', marginTop: spacing.lg },
  failureTitle: { color: colors.danger, fontSize: typography.subheading, fontWeight: '700' },
  failureText: { color: colors.danger, fontSize: typography.body, marginTop: spacing.xs, lineHeight: 19 },
  submit: { marginTop: spacing.xl },
});
