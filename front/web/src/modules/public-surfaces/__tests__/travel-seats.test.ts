import {
  buildSeatMapLayout,
  countSeatsByStatus,
  isSeatSelectable,
  resolveSeatLayout,
  resolveSeatsPerRow,
  seatAriaLabel,
  seatStatusLabel,
  seatStatusSymbol,
  selectableSeatNumbers,
  type SeatInput,
} from '../travel/domain/seat-layout';
import type { SeatLabels } from '../travel/domain/seat-layout';
import {
  reconcileSeatSelection,
  submittableSeatNumbers,
  unavailableSeatNumbers,
} from '../travel/domain/seat-selection';

/**
 * #9628 — domaine Billets repris de `front/travel-web`.
 *
 * Ces règles décident de ce que le client voit et de ce qu'il peut acheter :
 * un siège absent ne doit pas décaler la numérotation, un siège `held` ne doit
 * pas redevenir sélectionnable, et un siège perdu entre deux rafraîchissements
 * ne doit jamais partir dans la commande.
 */

/**
 * Libellés de test — repris tels quels du catalogue de production.
 *
 * Le domaine n'importe plus `copy/` (#9653) : c'est au test de nommer ce qu'il
 * vérifie. Les valeurs sont celles du catalogue, pour que l'assertion porte sur
 * le libellé réellement affiché au client.
 */
const LABELS: SeatLabels = {
  statuses: {
    free: 'Libre',
    selected: 'Sélectionné',
    held: 'Réservation en cours',
    reserved: 'Réservé',
    pending_payment: 'Paiement en attente',
    sold: 'Vendu',
    blocked: 'Bloqué',
    unavailable: 'Indisponible',
    aisle: 'Couloir',
    absent: 'Siège absent',
  },
  ariaTemplate: 'Siège {seat}, {status}',
};

function seats(spec: Array<[number, SeatInput['status']]>): SeatInput[] {
  return spec.map(([seat_number, status]) => ({ seat_number, status }));
}

describe('plan de sièges (#9628)', () => {
  it('découpe en rangées et insère un couloir', () => {
    const layout = buildSeatMapLayout(
      seats([
        [1, 'free'],
        [2, 'free'],
        [3, 'free'],
        [4, 'free'],
      ]),
      LABELS,
      {
        seatsPerRow: 4,
        aisleAfter: 2,
      },
    );

    expect(layout.rows).toHaveLength(1);
    expect(layout.columns).toBe(5);
    expect(layout.rows[0].cells.map((cell) => cell.status)).toEqual([
      'free',
      'free',
      'aisle',
      'free',
      'free',
    ]);
  });

  it('un siège absent occupe sa place réelle et n’est pas sélectionnable', () => {
    const layout = buildSeatMapLayout(
      seats([
        [1, 'free'],
        [3, 'free'],
      ]),
      LABELS,
      {
        seatsPerRow: 3,
        aisleAfter: null,
        absentSeats: [2],
      },
    );

    const cells = layout.rows[0].cells;

    expect(cells.map((cell) => cell.seatNumber)).toEqual([1, 2, 3]);
    expect(cells[1].status).toBe('absent');
    expect(isSeatSelectable('absent' as never)).toBe(false);
  });

  it('porte un symbole ET un libellé : l’état n’est jamais la couleur seule', () => {
    expect(seatStatusSymbol('sold')).not.toBe('');
    expect(seatStatusLabel('sold', LABELS)).toBe('Vendu');
    expect(seatAriaLabel(12, 'reserved', LABELS)).toBe('Siège 12, Réservé');
  });

  it('ne rend sélectionnables que les sièges explicitement libres', () => {
    const list = seats([[1, 'free'], [2, 'held'], [3, 'sold'], [4, 'free']]);

    expect(selectableSeatNumbers(list)).toEqual([1, 4]);
    expect(countSeatsByStatus(list)).toEqual({ free: 2, held: 1, sold: 1 });
  });

  it('déduit un gabarit quand la géométrie serveur est incohérente, et le signale', () => {
    const fallback = resolveSeatLayout(40, { seats_per_row: 4, aisle_after: 9 });

    expect(fallback.isConfigured).toBe(false);
    expect(fallback.seatsPerRow).toBe(4);

    const configured = resolveSeatLayout(40, { seats_per_row: 3, aisle_after: 1 });

    expect(configured.isConfigured).toBe(true);
    expect(configured.aisleAfter).toBe(1);
  });

  it('choisit 2+2 au-delà de 15 places, 2+1 en deçà', () => {
    expect(resolveSeatsPerRow(40)).toBe(4);
    expect(resolveSeatsPerRow(12)).toBe(3);
    expect(resolveSeatsPerRow(0)).toBe(4);
  });
});

describe('réconciliation de sélection (#9628)', () => {
  it('traite un statut inconnu comme indisponible (fail-closed)', () => {
    const unavailable = unavailableSeatNumbers(seats([[1, 'free'], [2, 'held']]));

    expect(unavailable.has(1)).toBe(false);
    expect(unavailable.has(2)).toBe(true);
  });

  it('retire un siège vendu entre deux rafraîchissements et le signale', () => {
    const passengers = [{ seat_number: 3 }, { seat_number: 5 }];
    const result = reconcileSeatSelection(passengers, seats([[3, 'sold'], [5, 'free']]));

    expect(result.changed).toBe(true);
    expect(result.lostSeats).toEqual([3]);
    expect(result.passengers[0].seat_number).toBeNull();
    expect(result.passengers[1].seat_number).toBe(5);
  });

  it('ne change rien quand tous les sièges sont encore libres', () => {
    const passengers = [{ seat_number: 1 }];
    const result = reconcileSeatSelection(passengers, seats([[1, 'free']]));

    expect(result.changed).toBe(false);
    expect(result.lostSeats).toEqual([]);
    expect(result.passengers).toBe(passengers);
  });

  it('ne transmet jamais un siège fantôme à la commande', () => {
    expect(submittableSeatNumbers([{ seat_number: 1 }, { seat_number: null }])).toEqual([1]);
  });
});
