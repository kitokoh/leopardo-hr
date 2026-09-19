/**
 * support-tickets.ts — repli FR + helpers de libellés pour l'espace client
 * « Support » (#7759).
 *
 * Source de vérité des traductions : `shared/i18n/locales/{fr,en,ar,tr}.json`
 * (namespace `support_tickets.*`). Les catalogues consommés par `t()`
 * (`front/web/src/lib/i18n/locales/*.json`) sont régénérés par
 * `shared/i18n/sync/sync-web.js` (fait dans ce lot). Ce module porte le REPLI
 * FR utilisé en 3ᵉ argument de `t(locale, clé, repli)` : `t()` retombe déjà
 * sur le FR quand une clé manque pour une autre locale, un seul repli suffit.
 *
 * Pourquoi ce repli n'est pas inline dans le JSX : le garde CI PA2-I18N-014
 * (`dev-hub/tools/check-i18n-diff.js`) refuse tout nouveau littéral de texte
 * visible ajouté dans un `.tsx` de `src/app/` — il ne distingue pas un repli
 * de traduction d'une chaîne en dur. Le texte reste centralisé ici (même
 * mécanique que `src/lib/i18n/team-roles.ts`) et n'est jamais recopié dans
 * les composants.
 */
import type { AppLocale } from '@/lib/i18n';
import { t } from '@/lib/i18n/locale-catalog';

export const SUPPORT_TICKETS_FR = {
  title: 'Support client',
  subtitle: 'Tickets tenant',
  menu_label: 'Support',
  filter_all: 'Tous',
  filter_open: 'Ouverts',
  filter_in_progress: 'En cours',
  filter_resolved: 'Résolus',
  filter_closed: 'Fermés',
  empty: 'Aucun ticket pour ce filtre.',
  loading: 'Chargement tickets',
  loading_ticket: 'Chargement ticket',
  reply_sent: 'Réponse envoyée.',
  load_error: 'Impossible de charger les tickets.',
  retry: 'Réessayer',
  new_ticket: 'Nouveau ticket',
  field_subject: 'Sujet',
  field_category: 'Catégorie',
  field_priority: 'Priorité',
  field_message: 'Message',
  create_submit: 'Ouvrir le ticket',
  create_success: 'Ticket créé. Notre équipe vous répond au plus vite.',
  form_incomplete: 'Renseignez le sujet et le message.',
  category_general: 'Général',
  category_billing: 'Facturation',
  category_technical: 'Technique',
  category_onboarding: 'Prise en main',
  category_other: 'Autre',
  priority_low: 'Basse',
  priority_normal: 'Normale',
  priority_high: 'Haute',
  priority_urgent: 'Urgente',
  status_open: 'Ouvert',
  status_pending: 'En cours',
  status_resolved: 'Résolu',
  status_closed: 'Clos',
  col_status: 'Statut',
  col_last_activity: 'Dernière activité',
  messages_count: '{count} message(s)',
  back_to_list: 'Retour à la liste',
  reply_label: 'Votre réponse',
  reply_placeholder: 'Écrivez votre message…',
  reply_submit: 'Envoyer la réponse',
  closed_notice: 'Ce ticket est clos. Ouvrez un nouveau ticket si vous avez encore besoin d’aide.',
  close_action: 'Clôturer le ticket',
  close_confirm_title: 'Clôturer ce ticket ?',
  close_confirm: 'Confirmer',
  close_success: 'Ticket clôturé.',
  cancel: 'Annuler',
  from_platform: 'Équipe Leopardo',
  from_company: 'Votre entreprise',
  pagination_prev: 'Précédent',
  pagination_next: 'Suivant',
  pagination_status: 'Page {page} sur {last}',
  action_failed: 'L’action a échoué. Réessayez.',
} as const;

export type SupportTicketsKey = keyof typeof SUPPORT_TICKETS_FR;

/** Traduit une clé `support_tickets.*` avec repli FR (cf. en-tête du module). */
export function supportTicketsT(locale: AppLocale, key: SupportTicketsKey): string {
  return t(locale, `support_tickets.${key}`, SUPPORT_TICKETS_FR[key]);
}

/** Catégories acceptées par `POST /support-tickets` (contrat API PA2-COMM-012). */
export const SUPPORT_TICKET_CATEGORIES = [
  'general',
  'billing',
  'technical',
  'onboarding',
  'other',
] as const;

export type SupportTicketCategory = (typeof SUPPORT_TICKET_CATEGORIES)[number];

/** Priorités acceptées par `POST /support-tickets`. */
export const SUPPORT_TICKET_PRIORITIES = ['low', 'normal', 'high', 'urgent'] as const;

export type SupportTicketPriority = (typeof SUPPORT_TICKET_PRIORITIES)[number];

/** Statuts renvoyés par l'API (`open|pending|resolved|closed`). */
export const SUPPORT_TICKET_STATUSES = ['open', 'pending', 'resolved', 'closed'] as const;

export type SupportTicketStatus = (typeof SUPPORT_TICKET_STATUSES)[number];

const CATEGORY_KEYS: Record<SupportTicketCategory, SupportTicketsKey> = {
  general: 'category_general',
  billing: 'category_billing',
  technical: 'category_technical',
  onboarding: 'category_onboarding',
  other: 'category_other',
};

const PRIORITY_KEYS: Record<SupportTicketPriority, SupportTicketsKey> = {
  low: 'priority_low',
  normal: 'priority_normal',
  high: 'priority_high',
  urgent: 'priority_urgent',
};

const STATUS_KEYS: Record<SupportTicketStatus, SupportTicketsKey> = {
  open: 'status_open',
  pending: 'status_pending',
  resolved: 'status_resolved',
  closed: 'status_closed',
};

/** Libellé localisé d'une catégorie ; la valeur brute si elle est inconnue. */
export function supportCategoryLabel(locale: AppLocale, category: string | null | undefined): string {
  const key = CATEGORY_KEYS[category as SupportTicketCategory];
  return key ? supportTicketsT(locale, key) : category ?? '';
}

/** Libellé localisé d'une priorité ; la valeur brute si elle est inconnue. */
export function supportPriorityLabel(locale: AppLocale, priority: string | null | undefined): string {
  const key = PRIORITY_KEYS[priority as SupportTicketPriority];
  return key ? supportTicketsT(locale, key) : priority ?? '';
}

/** Libellé localisé d'un statut ; la valeur brute si elle est inconnue. */
export function supportStatusLabel(locale: AppLocale, status: string | null | undefined): string {
  const key = STATUS_KEYS[status as SupportTicketStatus];
  return key ? supportTicketsT(locale, key) : status ?? '';
}

/** Classes de badge par statut (mêmes tonalités que les pastilles billing). */
export function supportStatusBadgeClass(status: string | null | undefined): string {
  switch (status) {
    case 'open':
      return 'bg-emerald-100 text-emerald-800';
    case 'pending':
      return 'bg-amber-100 text-amber-800';
    case 'resolved':
      return 'bg-sky-100 text-sky-800';
    case 'closed':
      return 'bg-slate-200 text-slate-600';
    default:
      return 'bg-slate-100 text-slate-600';
  }
}

/**
 * Message d'erreur d'action : privilégie le message métier de l'API quand il
 * est exploitable, sinon un libellé générique localisé.
 */
export function supportTicketsErrorMessage(locale: AppLocale, error: unknown): string {
  const status = (error as { status?: unknown } | null)?.status;

  if (status === 422) {
    // Ticket clos → l'API refuse la réponse (TICKET_ALREADY_CLOSED / message
    // de validation) : l'UI affiche l'explication localisée.
    return supportTicketsT(locale, 'closed_notice');
  }

  return supportTicketsT(locale, 'action_failed');
}
