import { NextRequest, NextResponse } from 'next/server';
import {
  FORM_RENDERED_AT_FIELD,
  HONEYPOT_FIELD,
  MIN_FILL_DURATION_MS,
} from '@/modules/vitrine/lib/antispam-fields';

export { FORM_RENDERED_AT_FIELD, HONEYPOT_FIELD, MIN_FILL_DURATION_MS };

/**
 * #7594 — barrières anti-bot des routes publiques `/api/forms/*`.
 *
 * Constat de l'audit : les 5 routes marketing étaient spammables en POST
 * direct. Le honeypot était absent, le contrôle d'origine absent, et le
 * « time-trap » **factice** — les fronts envoyaient bien un `timestamp`, mais
 * aucune route ne le comparait jamais.
 *
 * Ce module ajoute trois signaux, évalués AVANT toute persistance :
 *
 * 1. **Honeypot** — un champ que seul un robot remplit (il est masqué et hors
 *    du flux de tabulation côté front). Non vide ⇒ spam.
 * 2. **Time-trap réel** — `form_rendered_at` marque le moment où le FORMULAIRE A
 *    ÉTÉ RENDU (champ dédié, voir `antispam-fields.ts`). Un délai de remplissage
 *    inférieur à `MIN_FILL_DURATION_MS` est humainement impossible ⇒ spam.
 * 3. **Contrôle d'origine** — un `Origin`/`Referer` présent ET étranger au site
 *    ⇒ rejet.
 *
 * ## Deux choix assumés, écrits noir sur blanc
 *
 * **Un signal ABSENT n'est pas un signal négatif.** Un `form_rendered_at`
 * manquant ou un `Origin` absent ne rejettent pas : sans jeton signé côté serveur, une
 * requête sans champ est indistinguable d'un appel non-navigateur (e2e,
 * intégration). Refuser ces cas casserait des parcours réels pour un gain
 * incertain. Le honeypot, lui, ne rejette QUE s'il est rempli : les appelants
 * qui ne le rendent pas encore ne sont donc pas pénalisés, et l'ajouter à un
 * formulaire est un gain strict.
 *
 * **Le rejet est SILENCIEUX.** Une soumission jugée spam reçoit la même réponse
 * 201 qu'un succès, mais n'est **jamais persistée**. Un rejet explicite (400,
 * message dédié) apprendrait au robot qu'il a été détecté et lui permettrait de
 * sonder la règle. Corollaire à ne pas oublier : ce 201 ne doit jamais être
 * pris pour une preuve de capture côté appelant.
 */

/** Au-delà, l'horloge cliente est aberrante (fuseau, appareil mal réglé). */
const MAX_CLOCK_SKEW_MS = 60 * 60 * 1000;

export type SpamReason = 'honeypot' | 'too-fast' | 'bad-timestamp' | 'bad-origin';

export type SpamVerdict =
  | { spam: false }
  | { spam: true; reason: SpamReason };

/** Signaux lus sur le corps BRUT, avant validation zod (qui les supprimerait). */
export interface SpamSignals {
  honeypot: string;
  /** Rendu du formulaire. Absent = non mesurable, donc non bloquant (voir plus haut). */
  renderedAt?: string;
}

/**
 * Extrait les signaux du corps brut. À appeler sur l'objet `await request.json()`
 * — donc une seule lecture du corps par route — et AVANT `schema.parse`, car les
 * schémas zod ne déclarent pas le honeypot (zod supprime les clés inconnues).
 */
export function readSpamSignals(body: unknown): SpamSignals {
  if (!body || typeof body !== 'object' || Array.isArray(body)) {
    return { honeypot: '' };
  }
  const record = body as Record<string, unknown>;
  const honeypot = record[HONEYPOT_FIELD];
  const renderedAt = record[FORM_RENDERED_AT_FIELD];
  return {
    honeypot: typeof honeypot === 'string' ? honeypot.trim() : '',
    renderedAt: typeof renderedAt === 'string' ? renderedAt : undefined,
  };
}

function hostOf(value: string): string | null {
  try {
    return new URL(value).host;
  } catch {
    return null;
  }
}

/**
 * Hôtes légitimes : l'origine de la requête elle-même (`request.nextUrl.origin`)
 * plus les surcharges d'environnement. On ne code AUCUN domaine en dur — le
 * dépôt se déploie sur plusieurs environnements (dev, staging, prod, previews).
 */
export function allowedHosts(request: NextRequest): string[] {
  const hosts = new Set<string>();
  const self = hostOf(request.nextUrl.origin);
  if (self) hosts.add(self);

  for (const raw of [
    process.env.NEXT_PUBLIC_SITE_URL,
    process.env.NEXT_PUBLIC_APP_URL,
    process.env.VERCEL_URL ? `https://${process.env.VERCEL_URL}` : undefined,
  ]) {
    if (!raw) continue;
    const host = hostOf(raw.startsWith('http') ? raw : `https://${raw}`);
    if (host) hosts.add(host);
  }
  return [...hosts];
}

export function evaluateSpam(
  signals: SpamSignals,
  request: NextRequest,
  now: number = Date.now(),
): SpamVerdict {
  // 1. Honeypot — seul un robot (ou l'autocomplétion d'un navigateur malchanceux)
  //    remplit un champ masqué. Le `trim` est refait ici, et pas seulement dans
  //    `readSpamSignals` : la fonction est exportée, elle ne doit pas dépendre de
  //    l'ordre dans lequel l'appelant a utilisé les helpers.
  if (signals.honeypot.trim().length > 0) {
    return { spam: true, reason: 'honeypot' };
  }

  // 2. Contrôle d'origine — n'agit que si l'en-tête est présent.
  const originHeader = request.headers.get('origin') ?? request.headers.get('referer');
  if (originHeader) {
    const host = hostOf(originHeader);
    if (host && !allowedHosts(request).includes(host)) {
      return { spam: true, reason: 'bad-origin' };
    }
  }

  // 3. Time-trap — le contrôle qui était factice avant #7594.
  if (signals.renderedAt !== undefined) {
    const sent = Date.parse(signals.renderedAt);
    if (Number.isNaN(sent)) {
      return { spam: true, reason: 'bad-timestamp' };
    }
    const elapsed = now - sent;
    // Horloge dans le futur au-delà de la tolérance : signal d'automatisation.
    // ⚠️ À tester AVANT `too-fast` : un horodatage futur donne un `elapsed`
    // négatif, donc « inférieur au délai minimal » — la branche de skew serait
    // sinon inatteignable et le motif rapporté faux.
    if (elapsed < 0 && Math.abs(elapsed) > MAX_CLOCK_SKEW_MS) {
      return { spam: true, reason: 'bad-timestamp' };
    }
    if (elapsed < MIN_FILL_DURATION_MS) {
      return { spam: true, reason: 'too-fast' };
    }
  }

  return { spam: false };
}

/**
 * Réponse d'un rejet silencieux. Même forme qu'un succès, mais RIEN n'a été
 * persisté — et `data.id` est volontairement absent.
 */
export function spamResponse(): NextResponse {
  return NextResponse.json(
    { success: true, message: 'Demande enregistree.' },
    { status: 201 },
  );
}

export function forbiddenOriginResponse(): NextResponse {
  return NextResponse.json(
    {
      success: false,
      message: 'Origine non autorisee.',
      error: 'ORIGIN_NOT_ALLOWED',
    },
    { status: 403 },
  );
}
