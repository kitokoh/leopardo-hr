/**
 * commerce-pos.ts — repli FR des libellés du POS retail / commerce (#9272).
 *
 * Source de vérité : shared/i18n/locales/{fr,en,ar,tr}.json (namespace commerce.pos.*).
 * Centralisé ici pour respecter le garde PA2-I18N-014 (check-i18n-diff.js).
 */
export const COMMERCE_POS_FR = {
  searchPlaceholder: 'Rechercher par nom ou référence SKU...',
  cashMovement: 'Mouvement de caisse',
  cashMovementTitle: 'Tiroir-caisse : Apport ou Décaissement',
  movementType: 'Type de mouvement',
  cashIn: 'Apport (entrée d’espèces)',
  cashOut: 'Décaissement (sortie d’espèces)',
  movementReason: 'Motif du mouvement (obligatoire)',
  movementAmount: 'Montant du mouvement',
  recordMovement: 'Enregistrer le mouvement',
  movementSuccess: 'Mouvement de caisse enregistré avec succès.',
  refund: 'Rembourser',
  refundTitle: 'Remboursement de la commande',
  refundAmount: 'Montant à rembourser',
  refundReason: 'Motif du remboursement (obligatoire)',
  confirmRefund: 'Confirmer le remboursement',
  refundSuccess: 'Remboursement effectué avec succès.',
} as const;
