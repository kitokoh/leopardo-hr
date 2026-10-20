/**
 * chat.ts — repli FR du catalogue i18n de la messagerie horizontale (#8751).
 *
 * Même mécanique que `team-roles.ts` : les libellés vivent ICI, jamais en
 * littéral dans un `.tsx` de `src/app/`, parce que le garde CI PA2-I18N-014
 * (`dev-hub/tools/check-i18n-diff.js`) refuse tout nouveau texte visible
 * ajouté directement dans une page.
 *
 * Le repli FR est le 3ᵉ argument de `t(locale, clé, repli)` : `t()` retombe
 * déjà sur le FR quand une clé manque, un seul repli suffit donc. Dès que le
 * namespace `chat.*` sera synchronisé dans
 * `front/web/src/lib/i18n/locales/*.json` via `shared/i18n/sync/sync-web.js`,
 * ces replis deviennent inertes — la clé du catalogue prime.
 */
import type { AppLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

export const CHAT_FR = {
  // En-tête de page
  pageTitle: 'Messagerie',
  pageSubtitle: 'Conversations privées et groupes',
  pageDescription:
    'Échangez avec vos collègues dans un fil privé ou un groupe de travail.',
  menuLabel: 'Messagerie',

  // Liste
  listTitle: 'Conversations',
  listSearchLabel: 'Rechercher une conversation',
  listSearchPlaceholder: 'Nom, titre ou sujet',
  listEmpty: 'Aucune conversation pour le moment.',
  listEmptyBody:
    'Vos échanges avec vos collègues apparaîtront ici dès le premier message.',
  listLoading: 'Chargement des conversations…',
  listError: 'Impossible de charger les conversations.',
  unreadCount: '{count} non lu(s)',
  unreadLabel: 'Non-lus',
  kindDirect: 'Privé',
  kindGroup: 'Groupe',

  // Fil
  threadEmpty: 'Aucun message dans cette conversation.',
  threadEmptyBody: 'Écrivez le premier message pour démarrer l’échange.',
  threadLoading: 'Chargement des messages…',
  threadError: 'Impossible de charger les messages.',
  threadNotSelected: 'Sélectionnez une conversation',
  threadNotSelectedBody:
    'Choisissez un fil à gauche pour afficher les messages échangés.',
  backToList: 'Retour aux conversations',
  loadOlder: 'Charger les messages précédents',
  noOlder: 'Vous êtes arrivé au début de la conversation.',

  // Composeur
  composerLabel: 'Écrire un message',
  composerPlaceholder: 'Écrivez votre message…',
  send: 'Envoyer',
  sending: 'Envoi…',
  attach: 'Joindre un fichier',
  attachmentTooLarge: 'La pièce jointe dépasse 5 Mo.',
  attachmentRejected: 'Ce type de fichier n’est pas autorisé.',
  editMessage: 'Modifier',
  deleteMessage: 'Supprimer',
  replyTo: 'Répondre',
  cancelReply: 'Annuler la réponse',
  save: 'Enregistrer',
  cancel: 'Annuler',
  editedMarker: 'modifié',
  deletedMessage: 'Message supprimé',
  replyingTo: 'En réponse à {name}',

  // États réseau / erreurs
  offlineNotice:
    'Réseau indisponible — les conversations se rechargeront automatiquement.',
  reconnecting: 'Reconnexion…',
  loadError: 'Impossible de charger les conversations.',
  sendError: 'Le message n’a pas pu être envoyé. Réessayez.',
  retry: 'Réessayer',

  // Modération
  block: 'Bloquer',
  unblock: 'Débloquer',
  blockConfirm:
    'Bloquer cette personne ? Vous ne pourrez plus échanger de messages privés avec elle.',
  report: 'Signaler',
  reportReason: 'Motif du signalement',
  reportDetails: 'Précisions (facultatif)',
  reportSent: 'Signalement transmis. Un responsable le traitera.',
  reportError: 'Le signalement n’a pas pu être transmis.',
  reasonHarassment: 'Harcèlement',
  reasonSpam: 'Spam',
  reasonInappropriate: 'Contenu inapproprié',
  reasonThreat: 'Menace',
  reasonImpersonation: 'Usurpation d’identité',
  reasonOther: 'Autre',
} as const;

export type ChatKey = keyof typeof CHAT_FR;

/**
 * Traduit une clé `chat.*` (repli FR).
 *
 * @param locale locale active
 * @param key    clé du catalogue
 * @param vars   variables interpolées (`{count}`, `{name}`…)
 */
export function chatT(
  locale: AppLocale,
  key: ChatKey,
  vars?: Record<string, string | number>,
): string {
  const template = t(locale, `chat.${key}`, CHAT_FR[key]);

  if (!vars) {
    return template;
  }

  return Object.entries(vars).reduce(
    (acc, [name, value]) => acc.replaceAll(`{${name}}`, String(value)),
    template,
  );
}
