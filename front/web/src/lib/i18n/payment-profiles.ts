/**
 * payment-profiles.ts — repli FR + helpers pour l'espace client
 * « Encaissements » (#7727, BC-21) : profils de paiement du tenant (clés
 * Stripe propres, coordonnées bancaires, mobile money).
 *
 * Même mécanique que `team-roles.ts` (#7555) : le texte visible vit ici (pas
 * dans le JSX — garde CI PA2-I18N-014) et sert de repli FR au catalogue
 * `t(locale, clé, repli)`. La régénération des catalogues web
 * (`shared/i18n/sync/sync-web.js`) rendra ces replis inertes.
 */
import type { AppLocale, StoredAuthUser } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

export const PAYMENT_PROFILES_FR = {
  title: 'Encaissements',
  subtitle:
    'Encaissez vos factures clients sur VOS comptes : clés Stripe propres, coordonnées bancaires (IBAN), mobile money ou encaissement au local (espèces / comptoir).',
  menuLabel: 'Encaissements',
  reservedTitle: 'Accès réservé',
  reservedBody:
    'Cette page est réservée au manager principal de l’entreprise.',
  loading: 'Chargement des profils…',
  loadError: 'Impossible de charger les profils de paiement.',
  retry: 'Réessayer',
  listEmpty: 'Aucun profil de paiement pour le moment.',
  securityNotice:
    'Vos clés et coordonnées sont chiffrées au repos et ne sont jamais réaffichées en clair. Un profil Stripe actif route les encaissements de vos factures vers votre propre compte.',
  addProfile: 'Ajouter un profil',
  typeLabel: 'Type de profil',
  typeStripe: 'Clés Stripe propres',
  typeBank: 'Compte bancaire (IBAN)',
  typeMobile: 'Mobile money',
  typeCash: 'Au local (espèces / comptoir)',
  familyStripeTitle: 'En ligne (Stripe)',
  familyStripeBody:
    'Paiement en ligne des factures par carte : vos propres clés Stripe routent les encaissements vers votre compte.',
  familyBankTitle: 'Virement bancaire',
  familyBankBody:
    'Vos coordonnées bancaires (IBAN) s’affichent sur vos factures pour le règlement par virement.',
  familyMobileTitle: 'Mobile money',
  familyMobileBody:
    'Encaissement via un compte mobile money (opérateur local) : le numéro est chiffré, seul un masque est réaffiché.',
  familyCashTitle: 'Au local (espèces / comptoir)',
  familyCashBody:
    'Encaissement sur place : espèces ou TPE au comptoir. Aucun secret à configurer — déclarez simplement le mode et confirmez vos encaissements.',
  familyEmpty: 'Aucun profil dans cette famille.',
  addProfileForFamily: 'Ajouter',
  labelField: 'Nom du profil',
  labelPlaceholder: 'Ex. Compte principal',
  statusDraft: 'Brouillon',
  statusVerified: 'Vérifié',
  statusActive: 'Actif',
  defaultBadge: 'Par défaut',
  activate: 'Activer',
  activated: 'Profil activé — les encaissements utilisent désormais ce compte.',
  deleteAction: 'Supprimer',
  editAction: 'Modifier',
  deleteConfirm: 'Supprimer ce profil de paiement ? Cette action est définitive.',
  deleteConfirmAction: 'Confirmer la suppression',
  deleted: 'Profil supprimé.',
  cancel: 'Annuler',
  save: 'Enregistrer',
  saved: 'Profil enregistré.',
  saveError: 'Enregistrement impossible.',
  secretKeepHint: 'Laisser vide pour conserver la valeur en place.',
  fieldStripeSecretKey: 'Clé secrète Stripe (sk_…)',
  fieldStripePublishableKey: 'Clé publiable (pk_…)',
  fieldStripeWebhookSecret: 'Webhook secret (whsec_…)',
  fieldIban: 'IBAN / RIB',
  fieldAccountHolder: 'Titulaire du compte',
  fieldBankName: 'Banque',
  fieldBic: 'BIC (optionnel)',
  fieldOperator: 'Opérateur mobile money',
  fieldPhoneNumber: 'Numéro mobile money',
  fieldCashLocation: 'Point d’encaissement (optionnel)',
  fieldCashLocationPlaceholder: 'Ex. Comptoir principal',
  configured: 'Renseigné',
  notConfigured: 'Non renseigné',
  cashNoSecretHint: 'Aucune clé ni coordonnée à saisir pour ce mode.',
  collectionsTitle: 'Encaissements enregistrés',
  collectionsSubtitle:
    'Confirmez ici vos encaissements reçus au local (espèces, TPE au comptoir) et retrouvez les derniers montants enregistrés.',
  collectionsAmount: 'Montant',
  collectionsCurrency: 'Devise',
  collectionsMethod: 'Mode',
  collectionsMethodCash: 'Espèces',
  collectionsMethodCardTerminal: 'TPE au comptoir',
  collectionsNote: 'Note (optionnelle)',
  collectionsNotePlaceholder: 'Ex. Table 4, service du midi',
  collectionsSubmit: 'Enregistrer l’encaissement',
  collectionsSaved: 'Encaissement enregistré.',
  collectionsSaveError: 'Enregistrement de l’encaissement impossible.',
  collectionsInvalidAmount: 'Saisissez un montant supérieur à zéro.',
  collectionsLoading: 'Chargement des encaissements…',
  collectionsLoadError: 'Impossible de charger les encaissements.',
  collectionsEmpty: 'Aucun encaissement enregistré pour le moment.',
  collectionsDate: 'Date',
} as const;

export type PaymentProfilesKey = keyof typeof PAYMENT_PROFILES_FR;

export function paymentProfilesT(locale: AppLocale, key: PaymentProfilesKey): string {
  return t(locale, `paymentProfiles.${key}`, PAYMENT_PROFILES_FR[key]);
}

/** Seul le manager principal gère les encaissements (parité API `api.manager:principal`). */
export function isPrincipalUser(user: StoredAuthUser | null): boolean {
  return user?.manager_role === 'principal';
}
