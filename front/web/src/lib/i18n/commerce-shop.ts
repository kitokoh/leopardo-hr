/**
 * commerce-shop.ts — repli FR des libellés longs de la page « Boutique en
 * ligne » (/commerce/boutique, #7810 — Leopardo Marché).
 *
 * Source de vérité des traductions : `shared/i18n/locales/{fr,en,ar,tr}.json`
 * (namespace `commerce.shop.*`), catalogues web régénérés par
 * `shared/i18n/sync/sync-web.js`. Ce module porte le REPLI FR passé en 3ᵉ
 * argument de `t(locale, clé, repli)`.
 *
 * Pourquoi ce repli n'est pas inline dans le JSX : le garde CI PA2-I18N-014
 * (`dev-hub/tools/check-i18n-diff.js`) refuse tout nouveau littéral de texte
 * visible ajouté dans un `.tsx` de `src/app/` — il ne distingue pas un repli
 * de traduction (appel `t()` multi-ligne) d'une chaîne en dur. Le texte reste
 * centralisé ici (même mécanique que `src/lib/i18n/support-tickets.ts`).
 */
export const COMMERCE_SHOP_FR = {
  settingsEnabledHint:
    "Tant que la boutique est désactivée, aucun de vos produits n'est visible sur la marketplace.",
  productsHint:
    "Un produit n'apparaît sur la marketplace que s'il est publié, mis en ligne et que la boutique est activée.",
  ordersInvalidTransition:
    "Transition impossible : la commande a déjà changé d'état. La liste a été rechargée.",
  pageSubtitle:
    'Activez votre boutique sur la marketplace, publiez vos produits et gérez les commandes web.',
} as const;
