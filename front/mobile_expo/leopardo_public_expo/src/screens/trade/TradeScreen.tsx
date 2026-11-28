import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { ScreenHeader } from '../../components/ScreenHeader';
import { ScreenState } from '../../components/ScreenState';
import { Card, Section } from '../../components/Card';
import { Button } from '../../components/Button';
import { colors, spacing, typography } from '../../theme';
import { useAsync } from '../../hooks/useAsync';
import { fetchTradeCompany, fetchTradeProducts } from '../../api/public';
import { formatMoney } from '../../utils/format';
import { blockerFor, journeyByCode } from '../../verticals/registry';
import type { ScreenProps } from '../../navigation/types';

/**
 * FICHE ENTREPRISE — deux endpoints, pas un.
 *
 * `GET /public/trade/companies/{slug}` renvoie la fiche et des
 * `product_categories` (des chaines). Les PRODUITS vivent sur
 * `GET .../companies/{slug}/products` (#9768). Les chercher sur la fiche
 * renverrait toujours une liste vide — c'est l'ecart que ce fichier corrige.
 *
 * Le prix du catalogue est INDICATIF : le catalogue n'est pas une offre ferme.
 * Un prix ferme n'existe que dans un devis negocie, et le devis structure n'a
 * pas de contrat public. L'ecran le dit et propose une demande de contact.
 */
export function TradeScreen({ navigation, route }: ScreenProps<'Trade'>) {
  const { slug } = route.params;
  const company = useAsync(() => fetchTradeCompany(slug), [slug]);
  const products = useAsync(() => fetchTradeProducts(slug), [slug]);
  const quoteBlocker = blockerFor(journeyByCode('trade')!, 'quote');

  const data = company.data;
  const items = products.data ?? [];

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      <ScreenHeader
        title={data?.name ?? 'Entreprise'}
        subtitle={data?.city ?? undefined}
        onBack={() => navigation.goBack()}
      />
      <ScreenState
        loading={company.loading}
        failure={company.failure}
        onRetry={company.retry}
        refreshing={company.refreshing}
        onRefresh={company.reload}
      >
        {data ? (
          <>
            <Card style={styles.hero}>
              <Text style={styles.heroName}>{data.name}</Text>
              <Text style={styles.heroMeta}>
                {[data.city, data.country].filter(Boolean).join(' \u00b7 ') || 'International'}
              </Text>
              {data.description ? <Text style={styles.heroDesc}>{data.description}</Text> : null}
              <View style={styles.flags}>
                {data.import_enabled ? <Text style={styles.flag}>Import</Text> : null}
                {data.export_enabled ? <Text style={styles.flag}>Export</Text> : null}
              </View>
              {data.product_categories && data.product_categories.length > 0 ? (
                <Text style={styles.categories}>{data.product_categories.join(' \u00b7 ')}</Text>
              ) : null}
            </Card>

            <Section title="Catalogue">
              {products.loading ? (
                <Card>
                  <Text style={styles.muted}>Chargement du catalogue\u2026</Text>
                </Card>
              ) : products.failure ? (
                <Card style={styles.failureCard}>
                  <Text style={styles.failureTitle}>Catalogue indisponible</Text>
                  <Text style={styles.failureText}>{products.failure.message}</Text>
                  <Button
                    label="Reessayer"
                    variant="secondary"
                    onPress={products.retry}
                    style={styles.retryButton}
                  />
                </Card>
              ) : items.length === 0 ? (
                <Card>
                  <Text style={styles.emptyTitle}>Catalogue non publie</Text>
                  <Text style={styles.emptyText}>
                    Cette entreprise n&apos;a pas encore publie ses produits.
                  </Text>
                </Card>
              ) : (
                items.map((product, index) => (
                  <Card key={`${product.name}-${index}`} style={styles.productCard}>
                    <Text style={styles.productName}>{product.name}</Text>
                    {product.description ? (
                      <Text style={styles.productDesc} numberOfLines={2}>
                        {product.description}
                      </Text>
                    ) : null}
                    <View style={styles.productMeta}>
                      {product.unit ? (
                        <Text style={styles.productMetaText}>Unite : {product.unit}</Text>
                      ) : null}
                      {product.moq_quantity !== null ? (
                        <Text style={styles.productMetaText}>MOQ : {product.moq_quantity}</Text>
                      ) : null}
                      {product.origin_country ? (
                        <Text style={styles.productMetaText}>Origine : {product.origin_country}</Text>
                      ) : null}
                    </View>
                    {product.indicative_unit_price_minor !== null ? (
                      <Text style={styles.productPrice}>
                        {formatMoney(product.indicative_unit_price_minor, product.currency)}{' '}
                        <Text style={styles.indicative}>indicatif</Text>
                      </Text>
                    ) : null}
                  </Card>
                ))
              )}
            </Section>

            {quoteBlocker ? (
              <Card style={styles.blockerCard}>
                <Text style={styles.blockerTitle}>Devis en ligne indisponible</Text>
                <Text style={styles.blockerText}>{quoteBlocker}</Text>
              </Card>
            ) : null}

            <Button
              label="Demander un contact"
              onPress={() =>
                navigation.navigate('Contact', {
                  targetType: 'trade_company',
                  targetSlug: data.slug,
                  targetName: data.name,
                })
              }
              style={styles.cta}
            />
          </>
        ) : null}
      </ScreenState>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.background },
  hero: { gap: spacing.xs },
  heroName: { color: colors.text, fontSize: typography.title, fontWeight: '800', letterSpacing: -0.5 },
  heroMeta: { color: colors.textMuted, fontSize: typography.body },
  heroDesc: { color: colors.textMuted, fontSize: typography.body, lineHeight: 20, marginTop: spacing.sm },
  flags: { flexDirection: 'row', gap: spacing.sm, marginTop: spacing.sm },
  flag: {
    color: colors.primary,
    fontSize: typography.caption,
    fontWeight: '700',
    backgroundColor: colors.primarySoft,
    borderRadius: 999,
    paddingHorizontal: spacing.md,
    paddingVertical: 2,
    overflow: 'hidden',
  },
  categories: { color: colors.textFaint, fontSize: typography.caption, marginTop: spacing.sm },
  muted: { color: colors.textMuted, fontSize: typography.body },
  emptyTitle: { color: colors.text, fontSize: typography.subheading, fontWeight: '700' },
  emptyText: { color: colors.textMuted, fontSize: typography.body, marginTop: spacing.xs, lineHeight: 19 },
  productCard: { marginBottom: spacing.sm, gap: 2 },
  productName: { color: colors.text, fontSize: typography.subheading, fontWeight: '600' },
  productDesc: { color: colors.textMuted, fontSize: typography.caption },
  productMeta: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.md, marginTop: spacing.xs },
  productMetaText: { color: colors.textFaint, fontSize: typography.caption },
  productPrice: { color: colors.primary, fontSize: typography.body, fontWeight: '700', marginTop: spacing.xs },
  indicative: { color: colors.textFaint, fontSize: typography.caption, fontWeight: '400' },
  failureCard: { borderColor: colors.danger, backgroundColor: '#FEF2F2', gap: spacing.xs },
  failureTitle: { color: colors.danger, fontSize: typography.subheading, fontWeight: '700' },
  failureText: { color: colors.danger, fontSize: typography.body, lineHeight: 19 },
  retryButton: { marginTop: spacing.sm },
  blockerCard: { marginTop: spacing.lg, backgroundColor: colors.surfaceAlt, borderColor: colors.border },
  blockerTitle: { color: colors.text, fontSize: typography.subheading, fontWeight: '700' },
  blockerText: { color: colors.textMuted, fontSize: typography.body, marginTop: spacing.xs, lineHeight: 19 },
  cta: { marginTop: spacing.xl },
});
