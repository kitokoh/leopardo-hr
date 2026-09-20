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
    'Encaissez vos factures clients sur VOS comptes : clés Stripe propres, coordonnées bancaires (IBAN) ou mobile money.',
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
  configured: 'Renseigné',
  notConfigured: 'Non renseigné',
} as const;

export type PaymentProfilesKey = keyof typeof PAYMENT_PROFILES_FR;

export function paymentProfilesT(locale: AppLocale, key: PaymentProfilesKey): string {
  return t(locale, `paymentProfiles.${key}`, PAYMENT_PROFILES_FR[key]);
}

/** Seul le manager principal gère les encaissements (parité API `api.manager:principal`). */
export function isPrincipalUser(user: StoredAuthUser | null): boolean {
  return user?.manager_role === 'principal';
}
