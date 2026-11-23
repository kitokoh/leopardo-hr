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

/** Copie du retour de paiement (verticale Retail). */
export const RETAIL_PAYMENT_COPY = {
  verifyFailed: 'Vérification impossible pour le moment.',
  paidTitle: 'Paiement confirmé',
  paidBody:
    'Merci ! Votre paiement a bien été reçu. La boutique prépare votre commande.',
  pendingTitle: 'Confirmation en cours…',
  pendingBody:
    'Votre paiement est en cours de confirmation par le prestataire. Cette page se met à jour automatiquement.',
  stillPendingTitle: 'Confirmation toujours en attente',
  stillPendingBody:
    'Nous n’avons pas encore reçu la confirmation du prestataire. Si votre paiement a abouti, il sera pris en compte automatiquement.',
  refundedTitle: 'Paiement remboursé',
  refundedBody:
    'Cette commande a été remboursée par la boutique. Le délai dépend de votre moyen de paiement.',
  errorTitle: 'Vérification impossible',
  errorBody: 'Une erreur est survenue.',
} as const;

/** Copie des avis produits (verticale Retail). */
export const RETAIL_REVIEWS_COPY = {
  invalidRating: 'Choisissez une note entre 1 et 5.',
  submitFailed: "L'avis n'a pas pu être envoyé.",
  submit: 'Publier mon avis',
} as const;

/** Copie de la confirmation de commande (verticale Retail). */
export const RETAIL_CONFIRMATION_COPY = {
  paymentOnline: 'Paiement en ligne',
  paymentOnDelivery: 'À la livraison',
  orderLabel: 'la commande',
} as const;

/** Copie de la réservation (verticale Billets). */
export const TRAVEL_BOOKING_COPY = {
  searching: 'Recherche…',
  booking: 'Réservation en cours…',
  book: 'Réserver',
  track: 'Suivre ma réservation',
  bookingFailed: 'La réservation a échoué.',
  loadFailed: 'Impossible de charger vos réservations pour le moment.',
  passengerNamesRequired: 'Renseignez le nom de chaque passager.',
  contactRequired: 'Indiquez un e-mail ou un téléphone pour être prévenu.',
  seatTaken:
    'Un siège que vous aviez choisi vient d’être pris. Il a été retiré de votre sélection.',
  autoAssignHint: '— sinon, attribution automatique',
  submitting: 'Envoi…',
  // Paiement en ligne (TRAVEL-1002) — la réservation seule ne délivre pas de billet.
  pay: 'Payer',
  paymentPending: 'Paiement en cours…',
  paymentFailed: 'Le paiement n’a pas pu être initié.',
  downloadTicket: 'Télécharger le billet',
  ticketDownloadFailed: 'Le billet n’a pas pu être téléchargé.',
} as const;

/**
 * Libellés des moyens de paiement en ligne proposés au voyageur.
 *
 * `cash` n'y figure pas : il n'est encaissable qu'au guichet.
 */
export const PAYMENT_PROVIDER_LABELS: Readonly<Record<string, string>> = {
  momo: 'Mobile money',
  card: 'Carte bancaire',
  pvit: 'PVit',
};

/** Copie du compte voyageur (verticale Billets). */
export const TRAVEL_ACCOUNT_COPY = {
  invalidCredentials: 'E-mail ou mot de passe incorrect.',
  errorGeneric: 'Une erreur est survenue.',
  tablistLabel: 'Connexion ou inscription',
  tabLogin: 'Se connecter',
  tabRegister: "S'inscrire",
  submitLogin: 'Se connecter',
  submitRegister: 'Créer mon compte',
} as const;

/** Copie de la demande de course (verticale Mobilité). */
export const MOBILITY_RIDE_COPY = {
  invalidPickup: 'Le point de départ est invalide.',
  invalidDropoff: 'La destination est invalide.',
  missingName: 'Indiquez le nom du passager.',
  missingPhone: 'Indiquez un téléphone joignable.',
  invalidCoordinates: 'Saisissez les coordonnées au format « latitude, longitude ».',
  quoteFailed: 'Le devis a échoué.',
  requestFailed: 'La demande de course a échoué.',
} as const;

/** Copie de l'envoi d'avis (verticale Retail). */
export const RETAIL_REVIEW_SUBMIT_COPY = {
  submitting: 'Envoi…',
} as const;

/**
 * Libellés des états de siège (verticale Billets).
 *
 * Ils vivent ici et non dans `travel/domain/seat-layout.ts` : ce sont des
 * chaînes visibles par un visiteur, donc du ressort du catalogue de copie.
 * Le domaine garde la logique (ordre de légende, symbole, repli) et reçoit la
 * table — il n'a plus de texte en dur.
 */
export const SEAT_STATUS_LABELS: Readonly<Record<string, string>> = {
  free: 'Libre',
  selected: 'Sélectionné',
  held: 'Réservation en cours',
  reserved: 'Réservé',
  pending_payment: 'Paiement en attente',
  sold: 'Vendu',
  blocked: 'Bloqué',
  unavailable: 'Indisponible',
  aisle: 'Couloir',
  absent: 'Siège absent',
};

/** Copie du suivi de course (verticale Mobilité). */
export const MOBILITY_TRACKING_COPY = {
  searching: 'Recherche…',
  track: 'Suivre ma course',
  pickup: 'Départ',
  priceTbc: 'Prix à confirmer',
  submitting: 'Envoi…',
  request: 'Demander la course',
  /** Libellés des états de course renvoyés par l'API. */
  statuses: {
    requested: 'Demande envoyée',
    dispatching: 'Recherche d’un chauffeur',
    accepted: 'Chauffeur en route',
    arrived: 'Chauffeur sur place',
    in_progress: 'Course en cours',
    completed: 'Course terminée',
    expired: 'Demande expirée',
    cancelled: 'Course annulée',
  } as Readonly<Record<string, string>>,
} as const;
