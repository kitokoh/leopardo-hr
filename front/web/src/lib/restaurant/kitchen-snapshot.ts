/**
 * #8448 (KDS cuisine) — instantané local de la file cuisine.
 *
 * ## Le trou que ce module comble
 *
 * Le KDS a déjà une file d'opérations hors ligne (#8493) : hors réseau, une
 * transition `start`/`ready` est mise en file et rejouée à la reconnexion.
 * Mais RIEN ne conserve la file des COMMANDES elle-même : elle vit uniquement
 * dans l'état React, alimenté par `GET /restaurant/kitchen/orders` toutes les
 * 15 s. Conséquences réelles, constatées sur le critère d'acceptation
 * (« coupure internet pendant le service : la cuisine continue ») :
 *
 *  - **Un rechargement de page pendant la coupure vide l'écran.** Le cuisinier
 *    perd la vue de son service au pire moment — geste banal (tablette qui
 *    s'endort, onglet tué par le système) et non rattrapable.
 *  - **Une coupure qui commence AVANT le premier chargement** donne un écran
 *    vide, alors que le service tourne.
 *
 * Ce module est le MISSING PIECE le plus rentable : il rend la file visible
 * hors réseau sans toucher au modèle de données ni au backend.
 *
 * ## Ce que ce module ne fait PAS
 *
 * Il ne décide pas du statut d'une commande : l'instantané est une photo de
 * ce que le serveur a dit. La vérité reste serveur ; au retour du réseau le
 * rafraîchissement normal (polling) remplace l'instantané. Il ne fusionne pas
 * non plus les transitions locales non confirmées dans les commandes : la
 * file d'opérations (#8493) s'en charge déjà, et dupliquer cette logique
 * créerait deux sources de vérité.
 *
 * ## Choix de stockage
 *
 * `localStorage` et non IndexedDB : l'instantané pèse quelques dizaines de
 * kilo-octets (une file de service), l'API synchrone évite une couche
 * asynchrone dans un écran déjà complexe, et un échec de quota se dégrade
 * proprement (on n'écrit rien, l'écran marche comme avant). Le stockage est
 * INJECTABLE pour être testable sans DOM.
 */

/** Une commande telle que le KDS l'affiche (sous-ensemble suffisant pour peindre). */
export interface KitchenOrderSnapshot<TOrder = unknown> {
  orders: TOrder[];
  /** Horodatage de l'instantané (ms epoch) — affiché pour ne pas mentir au cuisinier. */
  capturedAt: number;
  /** Branche à laquelle l'instantané appartient : jamais mélanger deux branches. */
  branchId: number;
}

export interface SnapshotStorage {
  getItem(key: string): string | null;
  setItem(key: string, value: string): void;
  removeItem(key: string): void;
}

/** Clé versionnée : un changement de forme n'écrase pas un instantané d'un autre format. */
export const SNAPSHOT_KEY_PREFIX = 'leopardo.kds.snapshot.v1';

/**
 * Au-delà, un instantané n'est plus « l'état du service » mais une archive :
 * l'afficher sans réserve induirait le cuisinier en erreur sur des tables
 * depuis longtemps servies. 12 h couvre un service très long (double service
 * + mise en place) sans couvrir une journée entière.
 */
export const MAX_SNAPSHOT_AGE_MS = 12 * 60 * 60 * 1000;

export const snapshotKey = (branchId: number): string => `${SNAPSHOT_KEY_PREFIX}:${branchId}`;

/**
 * Écrit l'instantané. Retourne `true` si l'écriture a eu lieu.
 *
 * Un échec (quota dépassé, mode privé Safari, stockage désactivé par
 * politique) n'est pas une erreur du KDS : l'écran doit continuer à
 * fonctionner en ligne. On purge d'abord l'ancien instantané de la branche
 * pour ne pas empiler des versions qui saturent le quota.
 */
export function saveSnapshot<TOrder>(
  storage: SnapshotStorage,
  branchId: number,
  orders: TOrder[],
  now: number = Date.now(),
): boolean {
  const key = snapshotKey(branchId);
  const payload: KitchenOrderSnapshot<TOrder> = { orders, capturedAt: now, branchId };

  try {
    storage.removeItem(key);
    storage.setItem(key, JSON.stringify(payload));
    return true;
  } catch {
    return false;
  }
}

/**
 * Lit l'instantané d'une branche.
 *
 * Retourne `null` — jamais une exception, jamais un objet partiel — si :
 *  - rien n'est stocké ;
 *  - le JSON est illisible (écriture interrompue, format d'une version
 *    précédente) ;
 *  - la forme ne correspond pas (pas un tableau `orders`) ;
 *  - l'instantané appartient à une AUTRE branche (une tablette partagée entre
 *    deux points de vente ne doit jamais afficher la file du voisin) ;
 *  - l'instantané est plus vieux que `maxAgeMs`.
 *
 * `maxAgeMs = 0` désactive le contrôle d'âge (utile aux tests et à un appelant
 * qui veut afficher un instantané explicitement étiqueté comme ancien).
 */
export function loadSnapshot<TOrder>(
  storage: SnapshotStorage,
  branchId: number,
  now: number = Date.now(),
  maxAgeMs: number = MAX_SNAPSHOT_AGE_MS,
): KitchenOrderSnapshot<TOrder> | null {
  let raw: string | null;
  try {
    raw = storage.getItem(snapshotKey(branchId));
  } catch {
    return null;
  }

  if (raw === null || raw === '') return null;

  let parsed: unknown;
  try {
    parsed = JSON.parse(raw);
  } catch {
    // Instantané corrompu : on le supprime pour ne pas le relire à chaque tour.
    clearSnapshot(storage, branchId);
    return null;
  }

  if (typeof parsed !== 'object' || parsed === null) return null;
  const candidate = parsed as Partial<KitchenOrderSnapshot<TOrder>>;

  if (!Array.isArray(candidate.orders)) return null;
  if (typeof candidate.capturedAt !== 'number' || !Number.isFinite(candidate.capturedAt)) return null;
  if (candidate.branchId !== branchId) return null;

  if (maxAgeMs > 0 && now - candidate.capturedAt > maxAgeMs) return null;

  return {
    orders: candidate.orders,
    capturedAt: candidate.capturedAt,
    branchId,
  };
}

/** Supprime l'instantané d'une branche (déconnexion, changement de tenant…). */
export function clearSnapshot(storage: SnapshotStorage, branchId: number): void {
  try {
    storage.removeItem(snapshotKey(branchId));
  } catch {
    // Rien à faire : l'absence de purge n'empêche pas l'écran de fonctionner.
  }
}

/**
 * L'instantané doit-il être AFFICHÉ à la place de la file serveur ?
 *
 * Règle unique, volontairement stricte : on n'affiche un instantané que si le
 * serveur ne nous a rien donné pour cette branche. Un instantané ne doit
 * jamais RECOUVRIR une réponse serveur — même partielle — car il pourrait
 * masquer une commande annulée ou un changement de table.
 */
export function shouldDisplaySnapshot(
  serverOrdersCount: number,
  snapshot: KitchenOrderSnapshot<unknown> | null,
): boolean {
  return snapshot !== null && serverOrdersCount === 0 && snapshot.orders.length > 0;
}

/**
 * Message d'ancienneté destiné au cuisinier.
 *
 * Afficher un instantané sans dire son âge est le vrai danger : le cuisinier
 * croirait voir le service en direct. On rend donc l'âge lisible et on
 * distingue explicitement le cas « instantané » du cas « en direct ».
 */
export function describeSnapshotAge(capturedAt: number, now: number = Date.now()): string {
  const deltaMs = Math.max(0, now - capturedAt);
  const minutes = Math.floor(deltaMs / 60_000);

  if (minutes < 1) return 'hors ligne — instantané de moins d’une minute';
  if (minutes < 60) return `hors ligne — instantané de ${minutes} min`;

  const hours = Math.floor(minutes / 60);
  const remainingMinutes = minutes % 60;
  if (remainingMinutes === 0) return `hors ligne — instantané de ${hours} h`;

  return `hors ligne — instantané de ${hours} h ${remainingMinutes} min`;
}
