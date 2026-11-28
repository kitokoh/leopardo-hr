import React, { useMemo, useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { ScreenHeader } from '../../components/ScreenHeader';
import { ScreenState } from '../../components/ScreenState';
import { Badge, Card, Section } from '../../components/Card';
import { Button } from '../../components/Button';
import { colors, radius, spacing, typography } from '../../theme';
import { useAsync } from '../../hooks/useAsync';
import { useCart } from '../../hooks/useCart';
import { fetchRestaurant } from '../../api/public';
import { formatMoney } from '../../utils/format';
import type { ScreenProps } from '../../navigation/types';

/**
 * FICHE RESTAURANT + PANIER.
 *
 * Le menu vient de `GET /public/restaurants/{slug}` : les produits y sont
 * identifies par leur `code` metier, jamais par un ID interne. Le panier
 * local n'est qu'une estimation — le total contractuel est celui que le
 * serveur renvoie a la commande.
 */
export function RestaurantScreen({ navigation, route }: ScreenProps<'Restaurant'>) {
  const { slug } = route.params;
  const state = useAsync(() => fetchRestaurant(slug), [slug]);
  const cart = useCart();
  const [openCategory, setOpenCategory] = useState<string | null>(null);

  const restaurant = state.data;

  const activeCategory = useMemo(() => {
    if (!restaurant) return null;
    if (openCategory) return restaurant.menu.find((c) => c.name === openCategory) ?? null;
    return restaurant.menu[0] ?? null;
  }, [restaurant, openCategory]);

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      <ScreenHeader
        title={restaurant?.name ?? 'Restaurant'}
        subtitle={restaurant?.city ?? undefined}
        onBack={() => navigation.goBack()}
      />

      <ScreenState
        loading={state.loading}
        failure={state.failure}
        onRetry={state.retry}
        refreshing={state.refreshing}
        onRefresh={state.reload}
      >
        {restaurant ? (
          <>
            <Card style={styles.heroCard}>
              <View style={styles.heroHead}>
                <Text style={styles.heroName}>{restaurant.name}</Text>
                {restaurant.rating_avg !== null ? (
                  <Badge
                    label={`\u2605 ${restaurant.rating_avg.toFixed(1)} (${restaurant.reviews_count})`}
                    color={colors.warning}
                  />
                ) : null}
              </View>
              {restaurant.description ? (
                <Text style={styles.heroDesc}>{restaurant.description}</Text>
              ) : null}
              <Text style={styles.heroMeta}>
                {[restaurant.address, restaurant.city].filter(Boolean).join(' \u00b7 ')}
              </Text>
              {restaurant.cuisine_types && restaurant.cuisine_types.length > 0 ? (
                <Text style={styles.heroMeta}>{restaurant.cuisine_types.join(' \u00b7 ')}</Text>
              ) : null}
            </Card>

            {restaurant.menu.length === 0 ? (
              <Card style={styles.emptyCard}>
                <Text style={styles.emptyTitle}>Carte non publiee</Text>
                <Text style={styles.emptyText}>
                  Ce restaurant n'a pas encore publie sa carte en ligne. Vous pouvez le contacter
                  pour connaitre ses plats du jour.
                </Text>
                <Button
                  label="Contacter le restaurant"
                  variant="secondary"
                  onPress={() =>
                    navigation.navigate('Contact', {
                      targetType: 'restaurant',
                      targetSlug: restaurant.slug,
                      targetName: restaurant.name,
                    })
                  }
                  style={styles.emptyButton}
                />
              </Card>
            ) : (
              <>
                <Section title="La carte">
                  <View style={styles.categoryRow}>
                    {restaurant.menu.map((category) => {
                      const active = activeCategory?.name === category.name;
                      return (
                        <Pressable
                          key={category.name}
                          accessibilityRole="button"
                          accessibilityState={{ selected: active }}
                          onPress={() => setOpenCategory(category.name)}
                          style={[styles.categoryChip, active && styles.categoryChipActive]}
                        >
                          <Text
                            style={[styles.categoryText, active && styles.categoryTextActive]}
                          >
                            {category.name}
                          </Text>
                        </Pressable>
                      );
                    })}
                  </View>

                  {activeCategory?.products.map((product) => {
                    const inCart = cart.lines.find((line) => line.key === product.code);
                    return (
                      <Card key={product.code} style={styles.productCard}>
                        <View style={styles.productTexts}>
                          <Text style={styles.productName}>{product.name}</Text>
                          {product.description ? (
                            <Text style={styles.productDesc} numberOfLines={2}>
                              {product.description}
                            </Text>
                          ) : null}
                          <Text style={styles.productPrice}>
                            {formatMoney(product.price_minor, product.currency)}
                          </Text>
                        </View>
                        <View style={styles.stepper}>
                          {inCart ? (
                            <>
                              <Pressable
                                accessibilityRole="button"
                                accessibilityLabel={`Retirer un ${product.name}`}
                                onPress={() => cart.decrement(product.code)}
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
                            onPress={() =>
                              cart.add({
                                key: product.code,
                                label: product.name,
                                unitMinor: product.price_minor,
                              })
                            }
                            style={[styles.stepButton, styles.stepAdd]}
                          >
                            <Text style={[styles.stepGlyph, styles.stepAddGlyph]}>+</Text>
                          </Pressable>
                        </View>
                      </Card>
                    );
                  })}
                </Section>

                {cart.count > 0 ? (
                  <View style={styles.cartBar}>
                    <View style={styles.cartTexts}>
                      <Text style={styles.cartCount}>
                        {cart.count} article{cart.count > 1 ? 's' : ''}
                      </Text>
                      <Text style={styles.cartTotal}>
                        {formatMoney(cart.totalMinor, restaurant.currency)} estimé
                      </Text>
                    </View>
                    <Button
                      label="Commander"
                      onPress={() =>
                        navigation.navigate('RestaurantCheckout', {
                          slug: restaurant.slug,
                          restaurantName: restaurant.name,
                        })
                      }
                      style={styles.cartButton}
                    />
                  </View>
                ) : null}
              </>
            )}
          </>
        ) : null}
      </ScreenState>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.background },
  heroCard: { gap: spacing.sm },
  heroHead: { flexDirection: 'row', alignItems: 'center', gap: spacing.md },
  heroName: {
    flex: 1,
    color: colors.text,
    fontSize: typography.title,
    fontWeight: '800',
    letterSpacing: -0.5,
  },
  heroDesc: { color: colors.textMuted, fontSize: typography.body, lineHeight: 20 },
  heroMeta: { color: colors.textFaint, fontSize: typography.caption },
  emptyCard: { marginTop: spacing.lg, gap: spacing.sm },
  emptyTitle: { color: colors.text, fontSize: typography.subheading, fontWeight: '700' },
  emptyText: { color: colors.textMuted, fontSize: typography.body, lineHeight: 20 },
  emptyButton: { marginTop: spacing.md },
  categoryRow: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm, marginBottom: spacing.md },
  categoryChip: {
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius.pill,
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.sm,
  },
  categoryChipActive: { backgroundColor: colors.text, borderColor: colors.text },
  categoryText: { color: colors.textMuted, fontSize: typography.body, fontWeight: '600' },
  categoryTextActive: { color: colors.white },
  productCard: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    marginBottom: spacing.sm,
  },
  productTexts: { flex: 1 },
  productName: { color: colors.text, fontSize: typography.subheading, fontWeight: '600' },
  productDesc: { color: colors.textMuted, fontSize: typography.caption, marginTop: 2 },
  productPrice: {
    color: colors.primary,
    fontSize: typography.body,
    fontWeight: '700',
    marginTop: spacing.xs,
  },
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
