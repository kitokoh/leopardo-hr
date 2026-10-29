/**
 * Leopardo Edge — file d'attente locale du Kitchen Display System (#8448).
 *
 * Le problème réel : pendant le service, une coupure internet ne doit pas
 * arrêter la cuisine. Un ticket qui arrive doit pouvoir être « commencé » puis
 * « prêt » même si l'API est injoignable, et ces gestes doivent remonter au
 * retour du réseau **sans perte et sans doublon**.
 *
 * Trois décisions structurent ce module :
 *
 * 1. **La file locale est la source de vérité pendant la coupure.** L'UI ne
 *    lit jamais l'API directement : elle lit cet état, qui est réconcilié avec
 *    le serveur quand il revient. Sinon un écran vide pendant la coupure
 *    donnerait l'impression que la commande a disparu.
 *
 * 2. **Chaque geste porte un `clientActionId` généré localement.** C'est ce qui
 *    rend le rejeu idempotent : si la requête part, que le réseau tombe avant
 *    la réponse, et qu'on rejoue, le serveur reconnaît l'id et ne double pas la
 *    transition. Un compteur incrémental ne suffirait pas — il repart à zéro
 *    au rechargement de la page.
 *
 * 3. **Un geste n'est retiré de la file qu'après un accusé de réception.**
 *    « Envoyé » n'est pas « appliqué » : retirer trop tôt perd le geste si la
 *    réponse n'arrive jamais.
 *
 * Le stockage est injectable (`KdsStorage`) pour que tout ceci soit testable
 * sans navigateur — c'est la partie qui casse en silence si elle est fausse.
 */

export type KdsTicketStatus = 'received' | 'in_preparation' | 'ready' | 'served';

export type KdsActionKind = 'start' | 'ready' | 'serve';

export interface KdsTicketLine {
  id: string;
  name: string;
  quantity: number;
  notes?: string | null;
}

export interface KdsTicket {
  /** Identifiant serveur de la commande. */
  orderId: string;
  /** Numéro lisible affiché en cuisine (ex. « T-14 »). */
  reference: string;
  branchId: number;
  status: KdsTicketStatus;
  lines: KdsTicketLine[];
  /** Horodatage ISO de création côté serveur — sert à l'ordre d'affichage. */
  createdAt: string;
  /** Dernière modification locale, pour arbitrer un conflit au retour réseau. */
  updatedAt: string;
}

export interface KdsPendingAction {
  /** Idempotence : généré localement, rejoué tel quel. */
  clientActionId: string;
  orderId: string;
  kind: KdsActionKind;
  /** Statut visé — permet de rejouer sans recalculer la machine à états. */
  targetStatus: KdsTicketStatus;
  createdAt: string;
  /** Nombre de tentatives déjà effectuées (backoff côté appelant). */
  attempts: number;
}

export interface KdsState {
  tickets: KdsTicket[];
  pending: KdsPendingAction[];
}

export interface KdsStorage {
  read(): string | null;
  write(value: string): void;
}

export const KDS_STORAGE_KEY = 'leopardo.kds.v1';

/** Statut suivant d'un geste, ou `null` si le geste n'a pas de sens. */
export function nextStatusFor(kind: KdsActionKind): KdsTicketStatus {
  switch (kind) {
    case 'start':
      return 'in_preparation';
    case 'ready':
      return 'ready';
    case 'serve':
      return 'served';
  }
}

/**
 * Ordre canonique de la machine à états. Un geste qui ferait RECULER un ticket
 * est refusé : en cuisine, un « prêt » qui redevient « reçu » parce qu'un
 * message est arrivé en retard est un ticket perdu.
 */
const STATUS_RANK: Record<KdsTicketStatus, number> = {
  received: 0,
  in_preparation: 1,
  ready: 2,
  served: 3,
};

export function canAdvance(from: KdsTicketStatus, to: KdsTicketStatus): boolean {
  return STATUS_RANK[to] > STATUS_RANK[from];
}

/**
 * Générateur d'id d'action. `crypto.randomUUID` n'existe pas partout (vieux
 * WebView de tablette cuisine) : on retombe sur un id horodaté + aléatoire,
 * qui reste unique pour un poste donné.
 */
export function newClientActionId(now: () => number = Date.now): string {
  const cryptoObj = typeof globalThis !== 'undefined' ? globalThis.crypto : undefined;

  if (cryptoObj && typeof cryptoObj.randomUUID === 'function') {
    return cryptoObj.randomUUID();
  }

  return `kds-${now()}-${Math.random().toString(36).slice(2, 10)}`;
}

export function emptyState(): KdsState {
  return { tickets: [], pending: [] };
}

export function parseState(raw: string | null): KdsState {
  if (!raw) {
    return emptyState();
  }

  try {
    const parsed = JSON.parse(raw) as Partial<KdsState>;

    return {
      tickets: Array.isArray(parsed.tickets) ? parsed.tickets : [],
      pending: Array.isArray(parsed.pending) ? parsed.pending : [],
    };
  } catch {
    // Un état corrompu ne doit pas bloquer le service : on repart d'une file
    // vide plutôt que de laisser l'écran cuisine en erreur permanente.
    return emptyState();
  }
}

export class KdsQueue {
  constructor(
    private readonly storage: KdsStorage,
    private readonly now: () => number = Date.now,
  ) {}

  load(): KdsState {
    return parseState(this.storage.read());
  }

  private save(state: KdsState): KdsState {
    this.storage.write(JSON.stringify(state));

    return state;
  }

  /**
   * Remplace la liste des tickets par ce que le serveur vient de renvoyer,
   * **sans écraser un geste local non encore acquitté**.
   *
   * C'est le point délicat de la réconciliation : si la cuisine a marqué un
   * ticket « prêt » hors ligne et que le serveur renvoie encore « en
   * préparation », appliquer la réponse serveur ferait reculer le ticket sous
   * les yeux du cuisinier. On garde donc le statut local le plus avancé tant
   * qu'un geste pour ce ticket est en attente.
   */
  reconcile(serverTickets: KdsTicket[]): KdsState {
    const state = this.load();
    const pendingByOrder = new Map<string, KdsTicketStatus>();

    for (const action of state.pending) {
      const current = pendingByOrder.get(action.orderId);
      if (!current || STATUS_RANK[action.targetStatus] > STATUS_RANK[current]) {
        pendingByOrder.set(action.orderId, action.targetStatus);
      }
    }

    const merged = serverTickets.map((serverTicket) => {
      const local = state.tickets.find((t) => t.orderId === serverTicket.orderId);
      const pendingStatus = pendingByOrder.get(serverTicket.orderId);

      if (!local) {
        return serverTicket;
      }

      // Le statut local le plus avancé gagne tant qu'un geste est en attente.
      const localWins = pendingStatus !== undefined
        && STATUS_RANK[local.status] > STATUS_RANK[serverTicket.status];

      return localWins ? { ...serverTicket, status: local.status } : serverTicket;
    });

    // Les tickets absents du serveur mais avec un geste en attente sont
    // conservés : le serveur ne les connaît peut-être pas encore (créés hors
    // ligne) et les effacer perdrait le travail de la cuisine.
    for (const local of state.tickets) {
      if (!merged.some((t) => t.orderId === local.orderId) && pendingByOrder.has(local.orderId)) {
        merged.push(local);
      }
    }

    return this.save({ tickets: merged, pending: state.pending });
  }

  /**
   * Applique un geste localement et l'enfile pour synchronisation.
   *
   * Retourne `null` si le geste est refusé (ticket inconnu, ou transition qui
   * ferait reculer le statut) — l'appelant peut alors afficher un message
   * honnête plutôt qu'un succès mensonger.
   */
  enqueue(orderId: string, kind: KdsActionKind): KdsState | null {
    const state = this.load();
    const ticket = state.tickets.find((t) => t.orderId === orderId);

    if (!ticket) {
      return null;
    }

    const targetStatus = nextStatusFor(kind);

    if (!canAdvance(ticket.status, targetStatus)) {
      return null;
    }

    const timestamp = new Date(this.now()).toISOString();

    const action: KdsPendingAction = {
      clientActionId: newClientActionId(this.now),
      orderId,
      kind,
      targetStatus,
      createdAt: timestamp,
      attempts: 0,
    };

    const tickets = state.tickets.map((t) =>
      t.orderId === orderId ? { ...t, status: targetStatus, updatedAt: timestamp } : t,
    );

    return this.save({ tickets, pending: [...state.pending, action] });
  }

  /** Geste acquitté par le serveur : on le retire de la file. */
  acknowledge(clientActionId: string): KdsState {
    const state = this.load();

    return this.save({
      tickets: state.tickets,
      pending: state.pending.filter((a) => a.clientActionId !== clientActionId),
    });
  }

  /** Geste définitivement refusé (4xx) : on le retire aussi, il ne repassera pas. */
  reject(clientActionId: string): KdsState {
    return this.acknowledge(clientActionId);
  }

  /** Échec transitoire : on incrémente le compteur pour le backoff. */
  markAttempt(clientActionId: string): KdsState {
    const state = this.load();

    return this.save({
      tickets: state.tickets,
      pending: state.pending.map((a) =>
        a.clientActionId === clientActionId ? { ...a, attempts: a.attempts + 1 } : a,
      ),
    });
  }

  /** Tickets à afficher, dans l'ordre d'arrivée, hors « servi ». */
  visibleTickets(): KdsTicket[] {
    return this.load()
      .tickets.filter((t) => t.status !== 'served')
      .sort((a, b) => a.createdAt.localeCompare(b.createdAt));
  }
}
