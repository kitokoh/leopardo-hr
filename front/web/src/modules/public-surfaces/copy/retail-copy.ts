/**
 * Catalogue de copie des parcours transactionnels — verticales Retail et Emploi.
 *
 * ## Pourquoi ici et pas dans `surfaces-copy.ts`
 *
 * `surfaces-copy.ts` porte la copie **éditoriale** des vitrines : nom, accroche,
 * libellés d'annuaire. Ce fichier-ci porte la copie **opérationnelle** des
 * parcours : messages d'erreur, libellés de formulaire, états de chargement.
 *
 * Les deux vivent sous `copy/` parce que c'est le mécanisme de localisation du
 * module (cf. `check-i18n-diff.js`, exemption `/public-surfaces/copy/`) : une
 * chaîne visible par un visiteur ne doit jamais être en dur dans un composant.
 *
 * ## Ce qui a changé avec #9627/#9628
 *
 * Ces chaînes venaient de `front/marketplace` et `front/travel-web`, où elles
 * étaient écrites en dur dans les composants. La reprise les a déplacées ici
 * sans changer le rendu : c'est une mise en conformité, pas une réécriture
 * éditoriale.
 *
 * ## Limite assumée
 *
 * Seul le français est fourni. Les deux apps d'origine n'étaient pas
 * localisées sur ces parcours (contrairement aux vitrines, qui le sont ×4) :
 * inventer des traductions non relues serait pire que l'absence. Le type est
 * donc volontairement monolingue et documenté comme tel — la localisation de
 * ces parcours est un travail éditorial distinct.
 */

/** Copie du compte acheteur (verticale Retail). */
export const RETAIL_ACCOUNT_COPY = {
  loading: 'Chargement de votre compte…',
  greeting: (name: string) => `Bonjour, ${name}`,
  ordersTitle: 'Mes commandes',
  ordersHint: 'Historique, suivi et avis après livraison',
  favoritesTitle: 'Mes favoris',
  favoritesHint: 'Vos produits mis de côté',
  signOut: 'Se déconnecter',
  loginTitle: 'Connexion',
  registerTitle: 'Créer un compte',
  loginHint: 'Retrouvez vos commandes, favoris et avis.',
  registerHint: 'Inscription rapide : nom, e-mail et mot de passe suffisent.',
  tablistLabel: 'Connexion ou inscription',
  tabLogin: 'Se connecter',
  tabRegister: "S'inscrire",
  fieldName: 'Nom complet',
  fieldEmail: 'Adresse e-mail',
  fieldPhone: 'Téléphone (optionnel)',
  fieldPassword: 'Mot de passe',
  submitLogin: 'Se connecter',
  submitRegister: 'Créer mon compte',
  privacy:
    'Vos données restent minimales (RGPD) : elles servent uniquement à suivre vos commandes, favoris et avis.',
  errorInvalidCredentials: 'E-mail ou mot de passe incorrect.',
  errorRegisterFields:
    'Vérifiez les champs : e-mail déjà utilisé ou mot de passe trop court (8 caractères minimum).',
  errorFields: 'Vérifiez les champs saisis.',
  errorGeneric: 'Une erreur est survenue.',
} as const;

/** Copie du checkout (verticale Retail). */
export const RETAIL_CHECKOUT_COPY = {
  errorRequired: 'Renseignez les champs obligatoires.',
  successRecorded:
    'Vos commandes ont été enregistrées. Retrouvez-les sur la page de confirmation.',
  emptyCart: 'Votre panier est vide — ajoutez des articles avant de commander.',
  paymentOnDelivery: 'Paiement à la livraison',
  paymentOnDeliveryHint: 'Vous réglez en espèces, directement au livreur.',
  paymentOnline: 'Paiement en ligne',
  paymentOnlineHint: 'Carte ou mobile money, sur une page sécurisée.',
  submitting: 'Envoi en cours…',
  errorGeneric: 'Une erreur est survenue.',
} as const;

/** Copie des listes acheteur (commandes, favoris). */
export const RETAIL_LISTS_COPY = {
  ordersLoadFailed: 'Impossible de charger vos commandes pour le moment.',
  favoritesLoadFailed: 'Impossible de charger vos favoris pour le moment.',
  favoriteRemoveFailed: 'Le retrait a échoué — réessayez.',
} as const;
