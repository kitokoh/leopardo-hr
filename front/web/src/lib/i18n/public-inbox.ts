/**
 * public-inbox.ts — repli FR du catalogue i18n de la boîte de réception publique
 * (#8760).
 *
 * Même mécanique que `chat.ts` : les libellés vivent ICI, jamais en littéral
 * dans un `.tsx` de `src/app/`, parce que le garde CI PA2-I18N-014
 * (`dev-hub/tools/check-i18n-diff.js`) refuse tout nouveau texte visible ajouté
 * directement dans une page.
 *
 * Le repli FR est le 3ᵉ argument de `t(locale, clé, repli)` : `t()` retombe
 * déjà sur le FR quand une clé manque, un seul repli suffit donc. Dès que le
 * namespace `publicInbox.*` sera synchronisé dans
 * `front/web/src/lib/i18n/locales/*.json` via `shared/i18n/sync/sync-web.js`,
 * ces replis deviennent inertes — la clé du catalogue prime.
 */
import type { AppLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

export const PUBLIC_INBOX_FR = {
  // En-tête de page
  pageTitle: 'Demandes publiques',
  pageSubtitle: 'Clients venus de Leopardo Public',
  pageDescription:
    'Traitez ici les messages reçus depuis votre fiche publique.',

  // Liste
  listTitle: 'Conversations',
  listEmpty: 'Aucune demande pour le moment.',
  listEmptyBody:
    'Les messages envoyés par les clients depuis votre fiche publique apparaîtront ici.',
  listLoading: 'Chargement des demandes…',
  listError: 'Impossible de charger les demandes. Réessayez.',
  listRetry: 'Réessayer',
  unread: 'Nouveau',
  fromClient: 'Client',
  noSlug: 'Fiche publique',

  // Fil
  threadEmpty: 'Aucun message dans cette conversation.',
  threadEmptyBody: 'Le premier message du client apparaîtra ici.',
  threadLoading: 'Chargement des messages…',
  threadError: 'Impossible de charger les messages. Réessayez.',
  threadNotSelected: 'Sélectionnez une demande',
  threadNotSelectedBody:
    'Choisissez une conversation à gauche pour afficher les échanges.',
  threadUnavailable: 'Cette conversation n’est plus disponible.',
  threadUnavailableBody:
    'Elle a peut-être été supprimée, ou vous n’y avez plus accès.',
  backToList: 'Retour aux demandes',
  loadOlder: 'Charger les messages précédents',
  noOlder: 'Vous êtes arrivé au début de la conversation.',

  // Composeur
  composerLabel: 'Répondre au client',
  composerPlaceholder: 'Écrivez votre réponse…',
  send: 'Envoyer',
  sending: 'Envoi…',
  sent: 'Message envoyé.',
  sendError: 'Le message n’a pas pu être envoyé. Vérifiez votre connexion puis réessayez.',
  emptyBody: 'Le message ne peut pas être vide.',
  tooLong: 'Le message ne peut pas dépasser {max} caractères.',
  unauthenticated: 'Votre session a expiré. Reconnectez-vous pour continuer.',
  overloaded: 'Trop de messages envoyés. Patientez un instant puis réessayez.',
} as const;

export type PublicInboxKey = keyof typeof PUBLIC_INBOX_FR;

/**
 * Traduit une clé `publicInbox.*` (repli FR).
 *
 * @param locale locale active
 * @param key    clé du catalogue
 * @param vars   variables interpolées (`{max}`…)
 */
export function publicInboxT(
  locale: AppLocale,
  key: PublicInboxKey,
  vars?: Record<string, string | number>,
): string {
  const template = t(locale, `publicInbox.${key}`, PUBLIC_INBOX_FR[key]);

  if (!vars) {
    return template;
  }

  return Object.entries(vars).reduce(
    (acc, [name, value]) => acc.replaceAll(`{${name}}`, String(value)),
    template,
  );
}
