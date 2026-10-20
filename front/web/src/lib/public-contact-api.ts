/**
 * SUPERAPP-P1 (#8760) — client du contrat public de contact.
 *
 * `POST /api/v1/public/contact-requests` (sans auth) : un visiteur contacte un
 * professionnel depuis son profil public. Le tenant destinataire n'est JAMAIS
 * transmis — l'API le résout côté serveur depuis (`target_type`, `target_slug`),
 * fail-closed. Le navigateur ne peut donc pas viser une entreprise arbitraire.
 *
 * Le contrat est volontairement minimal (name/email/message/consent) : aucune
 * donnée RH, aucun identifiant interne, aucune permission n'est échangée ici.
 *
 * Idempotence : `idempotency_key` est généré une fois par tentative logique et
 * réutilisé en cas de retry, pour qu'un double-clic ne crée pas deux demandes
 * (et ne notifie pas deux fois le professionnel).
 */

/** Bornes miroir du backend (`StorePublicContactRequest`). */
export const CONTACT_MESSAGE_MIN = 10;
export const CONTACT_MESSAGE_MAX = 2000;
export const CONTACT_NAME_MIN = 2;
export const CONTACT_NAME_MAX = 120;

export interface PublicContactPayload {
  target_type: string;
  target_slug: string;
  sender_name: string;
  sender_email: string;
  sender_phone?: string | null;
  message: string;
  consent: boolean;
  /** Honeypot : doit rester vide côté humain. */
  company_website?: string;
}

export type PublicContactResult =
  | { status: 'sent'; reference: string }
  | { status: 'unavailable' }
  | { status: 'invalid'; fields?: Record<string, string[]> }
  | { status: 'error' };

/**
 * Génère une clé d'idempotence. `crypto.randomUUID` n'existe pas partout
 * (connexions limitées / navigateurs anciens) : repli explicite.
 */
export function createIdempotencyKey(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return `pc-${crypto.randomUUID()}`;
  }
  return `pc-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 12)}`;
}

interface ContactResponseBody {
  data?: { status?: string; reference?: string };
  error?: string;
  message?: string;
  errors?: Record<string, string[]>;
}

/**
 * Envoie une demande de contact.
 *
 * - `201`/`200` → `sent` (200 = rejeu idempotent, même demande) ;
 * - `404`       → `unavailable` (profil inexistant, dépublié ou suspendu —
 *                 indistinguables par conception, anti-énumération) ;
 * - `422`       → `invalid` (champs) ;
 * - autre/5xx   → `error` (état explicite, jamais un faux succès).
 */
export async function submitPublicContact(
  payload: PublicContactPayload,
  idempotencyKey: string,
  fetcher: typeof fetch = fetch,
): Promise<PublicContactResult> {
  try {
    const response = await fetcher('/api/v1/public/contact-requests', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify({ ...payload, idempotency_key: idempotencyKey }),
    });

    if (response.status === 201 || response.status === 200) {
      const body = (await response.json().catch(() => ({}))) as ContactResponseBody;
      return { status: 'sent', reference: body.data?.reference ?? idempotencyKey };
    }

    if (response.status === 404) {
      return { status: 'unavailable' };
    }

    if (response.status === 422) {
      const body = (await response.json().catch(() => ({}))) as ContactResponseBody;
      return { status: 'invalid', fields: body.errors };
    }

    return { status: 'error' };
  } catch {
    // Réseau coupé (connexions limitées) : état d'erreur explicite + retry.
    return { status: 'error' };
  }
}
