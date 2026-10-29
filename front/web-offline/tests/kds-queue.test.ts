import { describe, it, expect, beforeEach } from 'vitest';
import {
  KdsQueue,
  canAdvance,
  nextStatusFor,
  parseState,
  newClientActionId,
  type KdsStorage,
  type KdsTicket,
} from '../src/lib/kds-queue';

/**
 * #8448 — la file locale du KDS est la partie qui casse en silence.
 *
 * Ces tests couvrent les trois propriétés dont dépend le scénario
 * d'acceptation (« coupure internet pendant le service → la cuisine continue,
 * resync sans perte ») : pas de perte, pas de doublon, pas de recul de statut.
 */

class MemoryStorage implements KdsStorage {
  private value: string | null = null;

  read(): string | null {
    return this.value;
  }

  write(value: string): void {
    this.value = value;
  }
}

function ticket(overrides: Partial<KdsTicket> = {}): KdsTicket {
  return {
    orderId: 'order-1',
    reference: 'T-14',
    branchId: 7,
    status: 'received',
    lines: [{ id: 'l1', name: 'Poulet DG', quantity: 2 }],
    createdAt: '2026-10-29T10:00:00.000Z',
    updatedAt: '2026-10-29T10:00:00.000Z',
    ...overrides,
  };
}

describe('machine à états', () => {
  it('maps each action to its target status', () => {
    expect(nextStatusFor('start')).toBe('in_preparation');
    expect(nextStatusFor('ready')).toBe('ready');
    expect(nextStatusFor('serve')).toBe('served');
  });

  it('refuses a transition that would move a ticket backwards', () => {
    // Un « prêt » qui redevient « reçu » parce qu'un message est arrivé en
    // retard est un ticket perdu en cuisine.
    expect(canAdvance('ready', 'received')).toBe(false);
    expect(canAdvance('in_preparation', 'received')).toBe(false);
    expect(canAdvance('received', 'in_preparation')).toBe(true);
  });
});

describe('parseState', () => {
  it('returns an empty state for null', () => {
    expect(parseState(null)).toEqual({ tickets: [], pending: [] });
  });

  it('returns an empty state for corrupted JSON instead of throwing', () => {
    // Un état corrompu ne doit pas bloquer le service.
    expect(parseState('{not json')).toEqual({ tickets: [], pending: [] });
  });

  it('tolerates a payload missing one of the two arrays', () => {
    expect(parseState(JSON.stringify({ tickets: [ticket()] }))).toEqual({
      tickets: [ticket()],
      pending: [],
    });
  });
});

describe('newClientActionId', () => {
  it('produces distinct ids even without crypto.randomUUID', () => {
    const ids = new Set(Array.from({ length: 50 }, () => newClientActionId()));

    expect(ids.size).toBe(50);
  });
});

describe('KdsQueue', () => {
  let storage: MemoryStorage;
  let queue: KdsQueue;

  beforeEach(() => {
    storage = new MemoryStorage();
    queue = new KdsQueue(storage, () => Date.parse('2026-10-29T10:05:00.000Z'));
  });

  it('applies a gesture locally and enqueues it for sync', () => {
    queue.reconcile([ticket()]);

    const state = queue.enqueue('order-1', 'start');

    expect(state).not.toBeNull();
    expect(state!.tickets[0].status).toBe('in_preparation');
    expect(state!.pending).toHaveLength(1);
    expect(state!.pending[0].targetStatus).toBe('in_preparation');
  });

  it('refuses a gesture on an unknown ticket', () => {
    queue.reconcile([ticket()]);

    expect(queue.enqueue('order-unknown', 'start')).toBeNull();
  });

  it('refuses a gesture that would move the ticket backwards', () => {
    queue.reconcile([ticket({ status: 'ready' })]);

    expect(queue.enqueue('order-1', 'start')).toBeNull();
  });

  it('keeps the local status when the server still reports the old one', () => {
    // Le cœur du scénario hors ligne : la cuisine a marqué « prêt », le
    // serveur renvoie encore « en préparation ». Appliquer la réponse serveur
    // ferait reculer le ticket sous les yeux du cuisinier.
    queue.reconcile([ticket()]);
    queue.enqueue('order-1', 'start');
    queue.enqueue('order-1', 'ready');

    const state = queue.reconcile([ticket({ status: 'in_preparation' })]);

    expect(state.tickets[0].status).toBe('ready');
  });

  it('accepts the server status once the gesture is acknowledged', () => {
    queue.reconcile([ticket()]);
    const state = queue.enqueue('order-1', 'start');
    const actionId = state!.pending[0].clientActionId;

    queue.acknowledge(actionId);
    const reconciled = queue.reconcile([ticket({ status: 'in_preparation' })]);

    expect(reconciled.tickets[0].status).toBe('in_preparation');
    expect(reconciled.pending).toHaveLength(0);
  });

  it('keeps a locally-created ticket the server does not know yet', () => {
    // Un ticket créé hors ligne n'existe pas côté serveur : l'effacer parce
    // qu'il est absent de la réponse perdrait le travail de la cuisine.
    queue.reconcile([ticket()]);
    queue.enqueue('order-1', 'start');

    const state = queue.reconcile([]);

    expect(state.tickets).toHaveLength(1);
    expect(state.tickets[0].orderId).toBe('order-1');
  });

  it('drops a ticket the server no longer returns when nothing is pending', () => {
    queue.reconcile([ticket()]);

    const state = queue.reconcile([]);

    expect(state.tickets).toHaveLength(0);
  });

  it('removes an acknowledged gesture from the queue', () => {
    queue.reconcile([ticket()]);
    const state = queue.enqueue('order-1', 'start');

    const after = queue.acknowledge(state!.pending[0].clientActionId);

    expect(after.pending).toHaveLength(0);
  });

  it('increments attempts on a transient failure without losing the gesture', () => {
    queue.reconcile([ticket()]);
    const state = queue.enqueue('order-1', 'start');
    const actionId = state!.pending[0].clientActionId;

    const after = queue.markAttempt(actionId);

    expect(after.pending).toHaveLength(1);
    expect(after.pending[0].attempts).toBe(1);
  });

  it('hides served tickets from the board', () => {
    queue.reconcile([ticket(), ticket({ orderId: 'order-2', reference: 'T-15' })]);
    queue.enqueue('order-1', 'start');
    queue.enqueue('order-1', 'ready');
    queue.enqueue('order-1', 'serve');

    const visible = queue.visibleTickets();

    expect(visible.map((t) => t.orderId)).toEqual(['order-2']);
  });

  it('orders visible tickets by arrival', () => {
    queue.reconcile([
      ticket({ orderId: 'b', createdAt: '2026-10-29T10:02:00.000Z' }),
      ticket({ orderId: 'a', createdAt: '2026-10-29T10:01:00.000Z' }),
    ]);

    expect(queue.visibleTickets().map((t) => t.orderId)).toEqual(['a', 'b']);
  });

  it('propagates a storage failure so the caller can degrade', () => {
    // La file ne masque PAS l'échec de stockage : c'est l'adaptateur de la page
    // (`browserStorage`) qui l'avale, pour que la cuisine continue sans
    // persistance plutôt que d'afficher un écran d'erreur. Si la file
    // l'avalait elle-même, un quota plein passerait inaperçu et les gestes
    // seraient perdus au rechargement sans que personne ne le sache.
    const throwing: KdsStorage = {
      read: () => null,
      write: () => {
        throw new Error('quota exceeded');
      },
    };
    const fragile = new KdsQueue(throwing);

    expect(() => fragile.reconcile([ticket()])).toThrow('quota exceeded');
  });
});
