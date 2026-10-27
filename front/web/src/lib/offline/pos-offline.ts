/**
 * #8449 — POS offline-first : cache catalogue + encaissement hors-ligne.
 *
 * Le noyau hors-ligne (`offline-queue.ts`, `use-offline-sync.ts`) existait déjà
 * et était branché sur le KDS (#8493/#8494) ; le POS, lui, appelait l'API en
 * direct : sans réseau, le catalogue était vide et un encaissement espèces
 * était perdu. Ce module apporte les deux pièces manquantes, en restant PUR et
 * testable en Node (aucun accès DOM direct, stockage injecté) :
 *
 *  1. `PosCatalogCache` — le catalogue (catégories + produits) est conservé
 *     localement par succursale, avec un TTL. Hors ligne, le POS affiche le
 *     dernier catalogue connu au lieu d'un écran vide.
 *  2. `buildPaymentOperation` / `sendPosOperation` — un encaissement devient une
 *     opération de file dont la clé d'idempotence est STABLE (commande + total
 *     + moyen de paiement). Un rejeu après reconnexion ne crée donc pas de
 *     double encaissement : c'est la garantie « zéro perte, zéro doublon » de
 *     l'issue.
 *
 * Portée honnête : ceci couvre le catalogue et l'encaissement. L'ouverture de
 * caisse et la création de commande restent en ligne (elles exigent un
 * identifiant serveur) ; hors ligne, l'UI le dit explicitement au lieu
 * d'échouer silencieusement.
 */

import { makeOperationId, type OfflineOperation } from './offline-queue';

// ── Cache catalogue ──────────────────────────────────────────────────────────

export interface CachedCatalog<T> {
  branchId: number;
  savedAt: number;
  data: T;
}

/** Stockage clé/valeur minimal (localStorage en production, Map en test). */
export interface KeyValueStorage {
  getItem(key: string): string | null;
  setItem(key: string, value: string): void;
  removeItem(key: string): void;
}

export class InMemoryKeyValueStorage implements KeyValueStorage {
  private readonly map = new Map<string, string>();

  getItem(key: string): string | null {
    return this.map.has(key) ? (this.map.get(key) as string) : null;
  }

  setItem(key: string, value: string): void {
    this.map.set(key, value);
  }

  removeItem(key: string): void {
    this.map.delete(key);
  }
}

/** TTL par défaut : 24 h. Au-delà, le catalogue est « périmé » mais reste
 *  utilisable hors ligne — on ne vide jamais le cache sur simple expiration,
 *  sinon un service du matin sans réseau repartirait d'un écran vide. */
export const CATALOG_TTL_MS = 24 * 60 * 60 * 1000;

export class PosCatalogCache<T> {
  constructor(
    private readonly storage: KeyValueStorage,
    private readonly now: () => number = () => Date.now(),
    private readonly ttlMs: number = CATALOG_TTL_MS,
  ) {}

  private key(branchId: number): string {
    return `leopardo.pos.catalog.${branchId}`;
  }

  save(branchId: number, data: T): void {
    const entry: CachedCatalog<T> = { branchId, savedAt: this.now(), data };
    try {
      this.storage.setItem(this.key(branchId), JSON.stringify(entry));
    } catch {
      // Quota dépassé / mode privé : le cache est un confort, jamais un blocage.
    }
  }

  /** Dernier catalogue connu, même périmé (null si aucun). */
  load(branchId: number): CachedCatalog<T> | null {
    const raw = this.storage.getItem(this.key(branchId));
    if (raw === null) {
      return null;
    }
    try {
      const parsed = JSON.parse(raw) as CachedCatalog<T>;
      if (typeof parsed?.savedAt !== 'number' || parsed.data === undefined) {
        return null;
      }
      return parsed;
    } catch {
      return null;
    }
  }

  /** Le cache est-il encore frais ? (indicatif : l'UI peut afficher l'âge.) */
  isFresh(branchId: number): boolean {
    const entry = this.load(branchId);
    if (entry === null) {
      return false;
    }
    return this.now() - entry.savedAt <= this.ttlMs;
  }

  clear(branchId: number): void {
    this.storage.removeItem(this.key(branchId));
  }
}

// ── Opérations POS ───────────────────────────────────────────────────────────

export const POS_PAYMENT_KIND = 'pos.payment.cash';

export interface PosPaymentPayload {
  orderId: number;
  amountMinor: number;
  providerCode: string;
  /** Référence lisible (ticket) — utile au diagnostic d'un rejeu. */
  reference: string;
}

/**
 * Construit l'opération d'encaissement. La clé d'idempotence ne dépend QUE de
 * (commande, montant, moyen de paiement) : deux clics sur « Encaisser » pour la
 * même addition produisent la même clé, donc un seul encaissement.
 */
export function buildPaymentOperation(payload: PosPaymentPayload): {
  id: string;
  kind: string;
  payload: PosPaymentPayload;
} {
  return {
    id: makeOperationId(POS_PAYMENT_KIND, {
      orderId: payload.orderId,
      amountMinor: payload.amountMinor,
      providerCode: payload.providerCode,
    }),
    kind: POS_PAYMENT_KIND,
    payload,
  };
}

/** Envoi réel d'une opération POS au serveur. Doit lever en cas d'échec. */
export type PosSender = (operation: OfflineOperation) => Promise<void>;

export interface PosApiCall {
  path: string;
  body: unknown;
}

/**
 * Traduit une opération de file en appel API. Fonction PURE : elle ne fait
 * aucun réseau, ce qui permet de vérifier le contrat sans serveur.
 */
export function toApiCall(operation: OfflineOperation): PosApiCall {
  if (operation.kind !== POS_PAYMENT_KIND) {
    throw new Error(`unsupported_pos_operation:${operation.kind}`);
  }

  const payload = operation.payload as PosPaymentPayload;

  return {
    path: `/restaurant/orders/${payload.orderId}/pay`,
    body: {
      provider_code: payload.providerCode,
      amount_minor: payload.amountMinor,
      // Le serveur déduplique sur cette clé : un rejeu est sans effet.
      idempotency_key: operation.id,
    },
  };
}

/**
 * Fabrique un `PosSender` à partir d'une fonction d'appel HTTP injectée.
 * Séparer les deux rend le rejeu testable sans réseau.
 */
export function createPosSender(
  call: (path: string, body: unknown) => Promise<void>,
): PosSender {
  return async (operation: OfflineOperation): Promise<void> => {
    const { path, body } = toApiCall(operation);
    await call(path, body);
  };
}

/** Résumé affichable de la file POS (l'UI ne montre jamais d'objet brut). */
export interface PosQueueSummary {
  pendingCount: number;
  failedCount: number;
  lastError: string | null;
}

export function summarizeQueue(operations: OfflineOperation[]): PosQueueSummary {
  const pending = operations.filter((op) => op.status === 'pending' || op.status === 'syncing');
  const failed = operations.filter((op) => op.status === 'failed');
  const lastError = [...operations].reverse().find((op) => op.lastError)?.lastError ?? null;

  return {
    pendingCount: pending.length,
    failedCount: failed.length,
    lastError,
  };
}
