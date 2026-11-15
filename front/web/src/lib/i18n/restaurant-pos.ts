/**
 * restaurant-pos.ts — repli FR des libellés du POS restaurant (#9210).
 *
 * Source de vérité des traductions : `shared/i18n/locales/{fr,en,ar,tr}.json`
 * (namespace `restaurant.pos.*`), catalogues web régénérés par
 * `shared/i18n/sync/sync-web.js`. Ce module porte le REPLI FR passé en 3ᵉ
 * argument de `t(locale, clé, repli)`.
 *
 * Pourquoi ce repli n'est pas inline dans le JSX : le garde CI PA2-I18N-014
 * (`dev-hub/tools/check-i18n-diff.js`) refuse tout nouveau littéral de texte
 * visible ajouté dans un `.tsx` de `src/app/` — il ne distingue pas un repli
 * de traduction d'une chaîne en dur. Le texte reste centralisé ici (même
 * mécanique que `src/lib/i18n/commerce-shop.ts`).
 */
export const RESTAURANT_POS_FR = {
  orderTypeDineIn: 'Sur place',
  orderTypeTakeaway: 'A emporter',
  orderTypeDelivery: 'Livraison',
  openingCashLabel: 'Fond de caisse initial',
  closeSession: 'Clôturer la caisse',
  closeSessionTitle: 'Clôture de session de caisse',
  countedCashLabel: 'Montant compté en caisse',
  varianceReasonLabel: "Motif de l'écart (obligatoire si écart)",
  confirmClose: 'Confirmer la clôture',
  cancel: 'Annuler',
  paymentMethod: 'Moyen de paiement',
  paymentCash: 'Espèces',
  paymentCard: 'Carte bancaire',
  paymentMobile: 'Mobile Money',
  payWithMethod: 'Encaisser',
  tenderedAmount: 'Montant reçu',
  changeDue: 'Monnaie à rendre',
  printReceipt: 'Imprimer le ticket',
  printBill: "Imprimer l'addition",
} as const;
