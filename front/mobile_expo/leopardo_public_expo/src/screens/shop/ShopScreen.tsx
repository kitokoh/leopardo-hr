import React, { useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { ScreenHeader } from '../../components/ScreenHeader';
import { ScreenState } from '../../components/ScreenState';
import { Card } from '../../components/Card';
import { Button } from '../../components/Button';
import { colors, radius, spacing, typography } from '../../theme';
import { useAsync } from '../../hooks/useAsync';
import { useCart } from '../../hooks/useCart';
import { fetchProducts } from '../../api/public';
import { formatMoney } from '../../utils/format';
import type { ScreenProps } from '../../navigation/types';

/**
 * CATALOGUE D'UNE BOUTIQUE — `GET /public/market/products?seller=`.
 *
 * Un produit indisponible (`available: false`) reste visible mais ne peut pas
 * etre ajoute : le masquer ferait croire qu'il n'existe pas, l'ajouter
 * produirait un refus au checkout.
 */
export function ShopScreen({ navigation, route }: ScreenProps<'Shop'>) {
  const { sellerSlug, sellerName } = route.params;
  const state = useAsync(() => fetchProducts({ seller: sellerSlug }), [sellerSlug]);
  const cart = useCart();
  const [onlyAvailable, setOnlyAvailable] = useState(false);

  const products = (state.data ?? []).filter((p) => (onlyAvailable ? p.available : true));

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      <ScreenHeader
        title={sellerName}
        subtitle={state.data ? `${state.data.length} produit(s)` : undefined}
        onBack={() => navigation.goBack()}
      />
      <ScreenState
        loading={state.loading}
        failure={state.failure}
        onRetry={state.retry}
        refreshing={state.refreshing}
        onRefresh={state.reload}
        isEmpty={products.length === 0}
        emptyTitle="Catalogue vide"
        emptyMessage="Cette boutique n'a pas encore publie de produits."
      >
        <Pressable
          accessibilityRole="switch"
          accessibilityState={{ checked: onlyAvailable }}
          accessibilityLabel="Afficher seulement les produits disponibles"
          onPress={() => setOnlyAvailable((v) => !v)}
          style={styles.toggle}
        >
          <View style={[styles.checkbox, onlyAvailable && styles.checkboxOn]}>
            {onlyAvailable ? <Text style={styles.checkGlyph}>\u2713</Text> : null}
          </View>
          <Text style={styles.toggleLabel}>Seulement les produits disponibles</Text>
        </Pressable>

        {products.map((product) => {
          const inCart = cart.lines.find((line) => line.key === String(product.id));
          return (
            <Card key={product.id} style={styles.card}>
              <View style={styles.texts}>
                <Text style={styles.name}>{product.name}</Text>
                {product.category ? (
                  <Text style={styles.category}>{product.category.name}</Text>
                ) : null}
                {product.description ? (
                  <Text style={styles.desc} numberOfLines={2}>
                    {product.description}
                  </Text>
                ) : null}
                <Text style={styles.price}>
                  {formatMoney(product.price_minor, product.currency)}
                </Text>
                {!product.available ? (
                  <Text style={styles.unavailable}>Indisponible</Text>
                ) : null}
              </View>
              <View style={styles.stepper}>
                {inCart ? (
                  <>
                    <Pressable
                      accessibilityRole="button"
                      accessibilityLabel={`Retirer un ${product.name}`}
                      onPress={() => cart.decrement(String(product.id))}
                      style={styles.stepButton}
                    >
                      <Text style={styles.stepGlyph}>\u2212</Text>
                    </Pressable>
                    <Text style={styles.stepCount}>{inCart.quantity}</Text>
                  </>
                ) : null}
                <Pressable
                  accessibilityRole="button"
                  accessibilityLabel={`Ajouter ${product.name}`}
                  accessibilityState={{ disabled: !product.available }}
                  disabled={!product.available}
                  onPress={() =>
                    cart.add({
                      key: String(product.id),
                      label: product.name,
                      unitMinor: product.price_minor,
                    })
                  }
                  style={[
                    styles.stepButton,
                    styles.stepAdd,
                    !product.available && styles.stepDisabled,
                  ]}
                >
                  <Text style={[styles.stepGlyph, styles.stepAddGlyph]}>+</Text>
                </Pressable>
              </View>
            </Card>
          );
        })}

        {cart.count > 0 ? (
          <View style={styles.cartBar}>
            <View style={styles.cartTexts}>
              <Text style={styles.cartCount}>
                {cart.count} article{cart.count > 1 ? 's' : ''}
              </Text>
              <Text style={styles.cartTotal}>
                {formatMoney(cart.totalMinor, products[0]?.currency ?? 'XOF')} estimé
              </Text>
            </View>
            <Button
              label="Commander"
              onPress={() =>
                navigation.navigate('ShopCheckout', { sellerSlug, sellerName })
              }
              style={styles.cartButton}
            />
          </View>
        ) : null}
      </ScreenState>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.background },
  toggle: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    paddingVertical: spacing.md,
  },
  checkbox: {
    width: 22,
    height: 22,
    borderRadius: 6,
    borderWidth: 1.5,
    borderColor: colors.border,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.surface,
  },
  checkboxOn: { backgroundColor: colors.primary, borderColor: colors.primary },
  checkGlyph: { color: colors.white, fontSize: 13, fontWeight: '700' },
  toggleLabel: { color: colors.textMuted, fontSize: typography.body },
  card: { flexDirection: 'row', alignItems: 'center', gap: spacing.md, marginBottom: spacing.sm },
  texts: { flex: 1 },
  name: { color: colors.text, fontSize: typography.subheading, fontWeight: '600' },
  category: { color: colors.textFaint, fontSize: typography.caption, marginTop: 1 },
  desc: { color: colors.textMuted, fontSize: typography.caption, marginTop: 2 },
  price: { color: colors.primary, fontSize: typography.body, fontWeight: '700', marginTop: spacing.xs },
  unavailable: { color: colors.danger, fontSize: typography.caption, marginTop: 2, fontWeight: '600' },
  stepper: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm },
  stepButton: {
    width: 34,
    height: 34,
    borderRadius: 17,
    borderWidth: 1,
    borderColor: colors.border,
    alignItems: 'center',
    justifyContent: 'center',
  },
  stepAdd: { backgroundColor: colors.primary, borderColor: colors.primary },
  stepDisabled: { backgroundColor: colors.surfaceAlt, borderColor: colors.border },
  stepGlyph: { fontSize: 18, color: colors.text, fontWeight: '600', lineHeight: 20 },
  stepAddGlyph: { color: colors.white },
  stepCount: { color: colors.text, fontSize: typography.subheading, fontWeight: '700', minWidth: 16, textAlign: 'center' },
  cartBar: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    backgroundColor: colors.surface,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.border,
    padding: spacing.lg,
    marginTop: spacing.lg,
  },
  cartTexts: { flex: 1 },
  cartCount: { color: colors.text, fontSize: typography.subheading, fontWeight: '700' },
  cartTotal: { color: colors.textMuted, fontSize: typography.caption, marginTop: 2 },
  cartButton: { paddingHorizontal: spacing.xl },
});
