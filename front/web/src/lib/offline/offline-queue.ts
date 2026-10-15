/**
 * #8493 / #8494 (I-06, I-07) — socle hors-ligne partage POS / KDS.
 *
 * Exigences de la mission (Partie 7) que ce module rend VERIFIABLES :
 *  - operations hors ligne enregistrees localement ;
 *  - operations en attente visibles ;
 *  - traitements IDEMPOTENTS (pas de double commande) ;
 *  - conflits traites selon une regle metier explicite ;
 *  - erreurs diagnostiquables (derniere erreur conservee).
 *
 * Ce module est PUR et sans dependance : aucun reseau, aucun acces DOM, et un
 * stockage injectable (memoire par defaut ; IndexedDB/localStorage en
 * production). Il est donc testable de bout en bout en Node — condition pour
 * ne PAS annoncer une capacite hors ligne sans preuve (Partie 7).
 *
 * Portee honnete : ceci est le NOYAU de correction (file + idempotence +
 * conflits + reprise). Le branchement des ecrans POS/KDS et l'enrolement des
 * appareils restent a faire (issues #8493/#8494/#8499 suivies separement).
 */

export type OfflineOperationStatus = 'pending' | 'syncing' | 'synced' | 'failed';

export interface OfflineOperation<TPayload = unknown> {
  /** Cle d'idempotence STABLE : deux operations logiques identiques -> meme id. */
  id: string;
  kind: string;
  payload: TPayload;
  createdAt: number;
  attempts: number;
  status: OfflineOperationStatus;
  lastError?: string;
}

export interface OfflineStorage {
  load(): OfflineOperation[];
  save(operations: OfflineOperation[]): void;
}

export class InMemoryOfflineStorage implements OfflineStorage {
  private operations: OfflineOperation[] = [];

  load(): OfflineOperation[] {
    return this.operations.map((op) => ({ ...op }));
  }

  save(operations: OfflineOperation[]): void {
    this.operations = operations.map((op) => ({ ...op }));
  }
}

/** Hash deterministe (djb2) — sans dependance crypto, stable entre sessions. */
export function stableHash(input: string): string {
  let hash = 5381;
  for (let i = 0; i < input.length; i += 1) {
    hash = ((hash << 5) + hash + input.charCodeAt(i)) >>> 0;
  }
  return hash.toString(36);
}

/** Serialisation canonique : l'ordre des cles ne change pas la cle produite. */
function canonicalize(value: unknown): string {
  if (value === null || typeof value !== 'object') {
    return JSON.stringify(value) ?? 'null';
  }
  if (Array.isArray(value)) {
    return `[${value.map(canonicalize).join(',')}]`;
  }
  const entries = Object.entries(value as Record<string, unknown>)
    .sort(([a], [b]) => a.localeCompare(b))
    .map(([key, val]) => `${JSON.stringify(key)}:${canonicalize(val)}`);
  return `{${entries.join(',')}}`;
}

/**
 * Cle d'idempotence d'une operation : `kind` + charge utile canonique.
 * Deux commandes identiques (meme table, memes lignes) produisent la MEME
 * cle -> la file refuse le doublon (pas de double commande, Partie 7).
 */
export function makeOperationId(kind: string, payload: unknown): string {
  return `${kind}:${stableHash(canonicalize(payload))}`;
}

export type EnqueueResult = 'enqueued' | 'duplicate';

export class OfflineQueue {
  private operations: OfflineOperation[];

  constructor(
    private readonly storage: OfflineStorage = new InMemoryOfflineStorage(),
    private readonly now: () => number = () => Date.now(),
  ) {
    this.operations = storage.load();
  }

  private persist(): void {
    this.storage.save(this.operations);
  }

  /** Ajoute une operation ; IDEMPOTENT : un id deja present n'est pas duplique. */
  enqueue(kind: string, payload: unknown, id = makeOperationId(kind, payload)): EnqueueResult {
    if (this.operations.some((op) => op.id === id)) {
      return 'duplicate';
    }
    this.operations.push({
      id,
      kind,
      payload,
      createdAt: this.now(),
      attempts: 0,
      status: 'pending',
    });
    this.persist();
    return 'enqueued';
  }

  all(): OfflineOperation[] {
    return this.operations.map((op) => ({ ...op }));
  }

  /** Operations a (re)jouer : en attente ou en echec (reprise apres reconnexion). */
  pending(): OfflineOperation[] {
    return this.operations.filter((op) => op.status === 'pending' || op.status === 'failed');
  }

  size(): number {
    return this.operations.length;
  }

  private update(id: string, patch: Partial<OfflineOperation>): void {
    const index = this.operations.findIndex((op) => op.id === id);
    if (index === -1) {
      return;
    }
    this.operations[index] = { ...this.operations[index], ...patch };
    this.persist();
  }

  markSyncing(id: string): void {
    this.update(id, { status: 'syncing' });
  }

  markSynced(id: string): void {
    this.update(id, { status: 'synced', lastError: undefined });
  }

  markFailed(id: string, error: string): void {
    const op = this.operations.find((o) => o.id === id);
    this.update(id, { status: 'failed', attempts: (op?.attempts ?? 0) + 1, lastError: error });
  }

  /** Remet une operation echouee en attente (reprise apres reconnexion). */
  retry(id: string): void {
    this.update(id, { status: 'pending' });
  }
}

/**
 * Regle de conflit explicite : le SERVEUR fait autorite pour un meme id
 * d'operation. Si le serveur connait deja l'operation, le client accepte la
 * version serveur (l'operation locale n'est jamais rejouee -> pas de doublon) ;
 * sinon il n'y a pas de conflit et l'operation locale est conservee.
 */
export type ConflictResolution = 'keep-local' | 'accept-server';

export function resolveConflict(
  local: Pick<OfflineOperation, 'id' | 'status'>,
  serverHasOperation: boolean,
): ConflictResolution {
  if (!serverHasOperation) {
    return 'keep-local';
  }
  return 'accept-server';
}
