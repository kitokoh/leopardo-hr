import api from '@/services/api'

/**
 * #8754 [BOS-CHAT] — client de la messagerie horizontale (BC-13 COMMS).
 *
 * Miroir STRICT des contrats servis par `ConversationController` et
 * `ConversationModerationController` (`api/app/Modules/Notification`).
 * Aucune règle d'autorisation ici : le serveur décide (404 pour un
 * non-participant — jamais 403, qui révélerait l'existence du fil —, 422 pour
 * un payload invalide, 429 pour un débit excessif). Ce module ne fait que
 * typer, envoyer et paginer.
 *
 * ⚠️ À ne pas confondre avec `@/views/chat/ChatView.vue`, qui est l'ASSISTANT
 * IA plateforme (Léa, `/admin/ai/*`). Deux produits distincts : les
 * historiques ne se mélangent pas et une instruction écrite dans un message
 * humain ne déclenche aucune action métier.
 *
 * Le miroir TypeScript de référence est `front/web/src/lib/chat.ts` ; toute
 * évolution de contrat doit être répercutée dans les deux.
 */

const BASE = '/conversations'

/**
 * Clé d'idempotence côté client.
 *
 * `crypto.randomUUID()` n'existe pas dans les navigateurs anciens ni dans les
 * contextes non sécurisés ; le repli reste unique EN PRATIQUE (temps + aléa)
 * et, surtout, il est stable pour un même envoi : c'est tout ce dont le
 * serveur a besoin pour ne pas créer deux messages.
 */
export function newClientMessageId() {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }

  return `c_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 10)}`
}

/**
 * Fusionne deux listes de messages par `id`, en conservant l'ordre `seq`.
 *
 * Utilisé par le polling (#8749) : les messages reçus pendant une déconnexion
 * viennent s'INSÉRER dans l'historique, sans doublon et sans casser l'ordre.
 * Le tri se fait sur `seq`, jamais sur `created_at` : deux messages d'une même
 * transaction partagent la seconde.
 */
export function mergeMessages(existing, incoming) {
  const byId = new Map()

  for (const message of existing) byId.set(message.id, message)
  for (const message of incoming) byId.set(message.id, message)

  return Array.from(byId.values()).sort((a, b) => {
    const seqA = a.seq ?? Number.MAX_SAFE_INTEGER
    const seqB = b.seq ?? Number.MAX_SAFE_INTEGER

    return seqA === seqB ? a.id - b.id : seqA - seqB
  })
}

/**
 * Fils visibles par l'acteur authentifié, du plus récent au plus ancien.
 * @returns {Promise<object[]>}
 */
export async function fetchThreads(options = {}) {
  const response = await api.get(BASE, {
    // `_skipAuthRedirect` (#4170) : un 401 (super-admin sans contexte tenant)
    // ne doit PAS détruire la session admin — l'appelant affiche un état
    // explicite. Même pattern que `stores/travel.js`.
    _skipAuthRedirect: true,
    ...options,
  })

  const payload = response.data

  // Le contrôleur renvoie `{ data: [...] }` (collection Laravel paginée) ; on
  // tolère la forme nue pour ne pas casser si le contrat évolue.
  return Array.isArray(payload) ? payload : (payload?.data ?? [])
}

/**
 * Historique paginé par curseur. `before_seq` remonte l'historique,
 * `after_seq` rattrape ce qui est arrivé pendant une déconnexion (#8749).
 * Les deux ne sont jamais combinés.
 */
export async function fetchMessages(threadId, query = {}, options = {}) {
  const params = {}

  if (query.before_seq !== undefined) params.before_seq = query.before_seq
  if (query.after_seq !== undefined) params.after_seq = query.after_seq
  if (query.per_page !== undefined) params.per_page = query.per_page

  const response = await api.get(`${BASE}/${threadId}/messages`, {
    params,
    _skipAuthRedirect: true,
    ...options,
  })

  const payload = response.data ?? {}

  return {
    data: payload.data ?? [],
    meta: {
      has_more: payload.meta?.has_more ?? false,
      next_before_seq: payload.meta?.next_before_seq ?? null,
      last_seq: payload.meta?.last_seq ?? 0,
      per_page: payload.meta?.per_page ?? 30,
      catch_up: payload.meta?.catch_up ?? false,
    },
  }
}

/**
 * Envoie un message (texte et/ou une pièce jointe), avec clé d'idempotence.
 *
 * `created === false` signifie que le serveur a reconnu un REJEU (200 au lieu
 * de 201) : le message existait déjà, aucun doublon n'a été créé.
 */
export async function sendMessage(threadId, input = {}) {
  const form = new FormData()

  if (input.body) form.set('body', input.body)
  if (input.attachment) form.set('attachment', input.attachment)
  if (input.reply_to_message_id != null) {
    form.set('reply_to_message_id', String(input.reply_to_message_id))
  }
  if (input.client_message_id) form.set('client_message_id', input.client_message_id)

  const response = await api.post(`${BASE}/${threadId}/messages`, form, {
    _skipAuthRedirect: true,
  })

  return {
    message: response.data?.data ?? null,
    created: response.status === 201,
  }
}

/** Édition : auteur du message uniquement (le serveur le vérifie). */
export async function editMessage(threadId, messageId, body) {
  const response = await api.patch(
    `${BASE}/${threadId}/messages/${messageId}`,
    { body },
    { _skipAuthRedirect: true },
  )

  return response.data?.data ?? null
}

/** Suppression logique : la ligne survit côté serveur pour préserver l'ordre. */
export async function deleteMessage(threadId, messageId) {
  await api.delete(`${BASE}/${threadId}/messages/${messageId}`, {
    _skipAuthRedirect: true,
  })
}

/** Crée un fil direct (ou ancré à un contexte métier existant). */
export async function createThread(input = {}) {
  const form = new FormData()

  form.set('title', input.title ?? '')
  form.set('body', input.body ?? '')

  if (input.recipient_id != null) form.set('recipient_id', String(input.recipient_id))
  if (input.subject_type) form.set('subject_type', input.subject_type)
  if (input.subject_id != null) form.set('subject_id', String(input.subject_id))
  if (input.attachment) form.set('attachment', input.attachment)

  const response = await api.post(BASE, form, { _skipAuthRedirect: true })

  return response.data?.data ?? null
}

/** Crée un groupe avec ses participants et les rôles associés. */
export async function createGroup(input = {}) {
  const response = await api.post(
    `${BASE}/groups`,
    {
      title: input.title,
      body: input.body ?? '',
      participant_ids: input.participant_ids ?? [],
    },
    { _skipAuthRedirect: true },
  )

  return response.data?.data ?? null
}

/** Bloque un employé (idempotent côté serveur). */
export async function blockEmployee(employeeId, reason) {
  await api.post(
    `${BASE}/blocks`,
    reason ? { employee_id: employeeId, reason } : { employee_id: employeeId },
    { _skipAuthRedirect: true },
  )
}

/** Débloque un employé. */
export async function unblockEmployee(employeeId) {
  await api.delete(`${BASE}/blocks/${employeeId}`, { _skipAuthRedirect: true })
}

/** Signale un message (motif obligatoire, détail optionnel). */
export async function reportMessage(threadId, messageId, reason, details) {
  const response = await api.post(
    `${BASE}/${threadId}/reports`,
    { message_id: messageId, reason, details },
    { _skipAuthRedirect: true },
  )

  return response.data?.data ?? null
}

/**
 * URL de téléchargement d'une pièce jointe.
 *
 * Le fichier n'est PAS servi en public : le serveur rejoue le contrôle
 * d'appartenance au fil à CHAQUE téléchargement. Un 410 signifie que le
 * message existe mais que le fichier a disparu (disque éphémère redéployé,
 * risque R1 de l'audit).
 */
export function attachmentUrl(threadId, messageId) {
  return `${BASE}/${threadId}/messages/${messageId}/attachment`
}

/** Motifs acceptés par `StoreConversationReportRequest`. */
export const REPORT_REASONS = [
  'harassment',
  'spam',
  'inappropriate_content',
  'threat',
  'impersonation',
  'other',
]
