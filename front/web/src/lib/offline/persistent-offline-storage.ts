/**
 * #8448 — persistance de la file hors-ligne du KDS.
 *
 * ## Le trou que ce module ferme
 *
 * `offline-queue.ts` (#8493/#8494) fournit un noyau correct — file, clé
 * d'idempotence stable, règle de conflit — mais son stockage par défaut est
 * `InMemoryOfflineStorage`. Or le hook `useOfflineSync` crée ce stockage dans
 * un `useRef` : en pratique la file vit **le temps de la vie du composant**.
 *
 * Conséquence, exactement contre le critère d'acceptation de #8448
 * (« coupure internet pendant le service → la cuisine continue, resync sans
 * perte ») : un rafraîchissement de page, un crash de l'onglet, une coupure de
 * courant ou une mise en veille de la tablette pendant le service **efface**
 * les transitions déjà saisies. Le cuisinier a coché « prêt », l'écran dit
 * « en attente », et cette attente n'existe plus.
 *
 * Ce module fournit un `OfflineStorage` adossé à `localStorage` :
 *
 *  - **durable** : survit au rechargement, à la fermeture de l'onglet et au
 *    redémarrage de la tablette ;
 *  - **tolérant** : `localStorage` peut être absent (SSR, navigation privée
 *    stricte), plein (`QuotaExceededError`), ou contenir une valeur corrompue
 *    (autre version du schéma, écriture interrompue). Aucun de ces cas ne doit
 *    faire planter le KDS : on dégrade vers une file vide ou en mémoire, et on
 *    le dit (`lastStorageIssue`) au lieu de perdre des gestes en silence ;
 *  - **isolé par terminal** : plusieurs tablettes peuvent partager un
 *    navigateur / un profil ; deux écrans cuisine ne doivent pas se voler
 *    leurs opérations. D'où une clé préfixée par un identifiant de poste.
 *
 * Le module reste sans dépendance et testable en Node : le `Storage` est
 * injectable.
 */

import type { OfflineOperation, OfflineStorage } from '@/lib/offline/offline-queue';

/** Sous-ensemble de l'API Web Storage réellement utilisé. */
export interface KeyValueStore {
  getItem(key: string): string | null;
  setItem(key: string, value: string): void;
  removeItem(key: string): void;
}

export type StorageIssue = 'unavailable' | 'corrupt' | 'quota' | null;

export const DEFAULT_STORAGE_PREFIX = 'leopardo.kds.offline';

/** Identifiant de poste stable, pour ne pas mélanger deux écrans cuisine. */
export function storageKey(prefix: string = DEFAULT_STORAGE_PREFIX, station = 'default'): string {
  return `${prefix}.${station}.v1`;
}

/**
 * File persistée dans un `Storage` synchrone (`localStorage` en production).
 *
 * `lastStorageIssue` expose le dernier incident rencontré : l'écran peut
 * afficher « hors-ligne non persistant » plutôt que de laisser croire à une
 * durabilité qui n'existe pas. On ne perd jamais une opération *en silence*.
 */
export class PersistentOfflineStorage implements OfflineStorage {
  private issue: StorageIssue = null;

  constructor(
    private readonly store: KeyValueStore | null,
    private readonly key: string = storageKey(),
  ) {}

  /** Dernier incident de stockage (`null` si tout va bien). */
  get lastStorageIssue(): StorageIssue {
    return this.issue;
  }

  load(): OfflineOperation[] {
    if (this.store === null) {
      this.issue = 'unavailable';
      return [];
    }

    let raw: string | null;
    try {
      raw = this.store.getItem(this.key);
    } catch {
      this.issue = 'unavailable';
      return [];
    }

    if (raw === null || raw === '') {
      this.issue = null;
      return [];
    }

    try {
      return sanitize(JSON.parse(raw));
    } catch {
      // Valeur illisible : on ne la supprime pas nous-mêmes (un autre onglet
      // peut être en train d'écrire) — on repart d'une file vide et on le
      // signale. La prochaine sauvegarde écrasera la valeur corrompue.
      this.issue = 'corrupt';
      return [];
    }
  }

  save(operations: OfflineOperation[]): void {
    if (this.store === null) {
      this.issue = 'unavailable';
      return;
    }

    try {
      this.store.setItem(this.key, JSON.stringify(operations));
      this.issue = null;
    } catch {
      // Quota dépassé (ou stockage refusé en navigation privée). La file
      // continue de fonctionner en mémoire : on dégrade explicitement.
      this.issue = 'quota';
    }
  }

  /** Purge la file persistée (déconnexion du poste, changement de branche). */
  clear(): void {
    if (this.store === null) {
      return;
    }
    try {
      this.store.removeItem(this.key);
      this.issue = null;
    } catch {
      this.issue = 'unavailable';
    }
  }
}

/**
 * Valide ce qui vient du stockage.
 *
 * `localStorage` est partagé avec tout le domaine et survit aux déploiements :
 * une valeur écrite par une version antérieure (ou par un autre outil) ne doit
 * pas injecter d'objets informes dans le moteur de synchronisation. On ne
 * garde que les opérations dont les champs critiques sont exploitables.
 */
export function sanitize(value: unknown): OfflineOperation[] {
  if (!Array.isArray(value)) {
    throw new TypeError('offline queue is not an array');
  }

  const operations: OfflineOperation[] = [];
  for (const candidate of value) {
    if (candidate === null || typeof candidate !== 'object') continue;
    const op = candidate as Record<string, unknown>;

    if (typeof op.id !== 'string' || op.id === '') continue;
    if (typeof op.kind !== 'string' || op.kind === '') continue;

    const status = op.status;
    if (status !== 'pending' && status !== 'syncing' && status !== 'synced' && status !== 'failed') {
      continue;
    }

    operations.push({
      id: op.id,
      kind: op.kind,
      payload: op.payload,
      createdAt: typeof op.createdAt === 'number' ? op.createdAt : Date.now(),
      attempts: typeof op.attempts === 'number' ? op.attempts : 0,
      // Une opération retrouvée en `syncing` a été interrompue en plein envoi
      // (onglet fermé, coupure). Elle n'est ni en vol ni aboutie : on la remet
      // en `pending` pour qu'elle soit rejouée. L'idempotence côté serveur
      // garantit l'absence de double effet.
      status: status === 'syncing' ? 'pending' : status,
      lastError: typeof op.lastError === 'string' ? op.lastError : undefined,
    });
  }

  return operations;
}

/**
 * Renvoie le `localStorage` du navigateur, ou `null` hors navigateur.
 *
 * L'accès lui-même peut lever (`SecurityError` quand les cookies sont bloqués),
 * d'où le try/catch : un KDS doit démarrer même sans stockage.
 */
export function browserStorage(): KeyValueStore | null {
  if (typeof window === 'undefined') {
    return null;
  }
  try {
    const store = window.localStorage;
    // Sonde d'écriture : Safari en navigation privée expose l'objet mais lève
    // au premier `setItem`.
    const probe = `${DEFAULT_STORAGE_PREFIX}.probe`;
    store.setItem(probe, '1');
    store.removeItem(probe);
    return store;
  } catch {
    return null;
  }
}
