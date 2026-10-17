import { describe, expect, it } from "vitest";

import {
  buildSeatMapLayout,
  countSeatsByStatus,
  isSeatSelectable,
  resolveSeatLayout,
  resolveSeatsPerRow,
  seatAriaLabel,
  seatStatusLabel,
  selectableSeatNumbers,
  type SeatRowLayout,
  type SeatCell,
  type SeatInput,
  type SeatStatusValue,
} from "@/lib/seat-layout";

/**
 * Mission Leopardo — Travel OS (#8602) : verrouille la disposition du plan de
 * sièges. Ces tests garantissent que le plan est dérivé de données réelles et
 * que les états restent distinguables autrement que par la couleur.
 */

function seats(count: number, status: SeatStatusValue = "free"): SeatInput[] {
  return Array.from({ length: count }, (_, i) => ({ seat_number: i + 1, status }));
}

/** Récupère un élément par index (échoue explicitement si absent). */
function at<T>(items: T[], index: number): T {
  const item = items[index];
  if (item === undefined) throw new Error(`Élément ${index} introuvable`);
  return item;
}

/** Cellules de la n-ième rangée du plan. */
function row(rows: SeatRowLayout[], index: number): SeatCell[] {
  return at(rows, index).cells;
}

describe("buildSeatMapLayout", () => {
  it("reconstruit une rangée 2+2 avec couloir pour un bus de 40 places", () => {
    const layout = buildSeatMapLayout(seats(40), { seatsPerRow: 4, aisle: true });

    expect(layout.rows).toHaveLength(10);
    expect(layout.columns).toBe(5);
    expect(layout.aisleColumn).toBe(2);

    const firstRow = row(layout.rows, 0);
    expect(firstRow.map((c: SeatCell) => c.seatNumber)).toEqual([1, 2, null, 3, 4]);
    expect(at(firstRow, 2).status).toBe("aisle");
  });

  it("découpe les sièges par ordre croissant de numéro", () => {
    const layout = buildSeatMapLayout(
      [
        { seat_number: 3, status: "free" },
        { seat_number: 1, status: "free" },
        { seat_number: 2, status: "free" },
      ],
      { seatsPerRow: 2, aisle: true },
    );

    expect(row(layout.rows, 0).map((c: SeatCell) => c.seatNumber)).toEqual([1, null, 2]);
  });

  it("gère une dernière rangée partielle sans inventer de siège", () => {
    const layout = buildSeatMapLayout(seats(5), { seatsPerRow: 4, aisle: true });

    expect(layout.rows).toHaveLength(2);
    expect(row(layout.rows, 1).filter((c: SeatCell) => c.seatNumber !== null)).toHaveLength(1);
  });

  it("n'insère aucun couloir quand demandé (configuration 1+1 ou minibus)", () => {
    const layout = buildSeatMapLayout(seats(8), { seatsPerRow: 2, aisle: false });

    expect(layout.aisleColumn).toBeNull();
    expect(layout.columns).toBe(2);
    expect(row(layout.rows, 0).map((c: SeatCell) => c.seatNumber)).toEqual([1, 2]);
  });

  it("porte un symbole ET un libellé texte pour chaque état (non coloré)", () => {
    const layout = buildSeatMapLayout(
      [
        { seat_number: 1, status: "free" },
        { seat_number: 2, status: "held" },
        { seat_number: 3, status: "sold" },
        { seat_number: 4, status: "blocked" },
      ],
      { seatsPerRow: 4, aisle: false },
    );

    const cells = row(layout.rows, 0);
    expect(at(cells, 0).label).toBe("Libre");
    expect(at(cells, 1).label).toBe("Réservation en cours");
    expect(at(cells, 2).label).toBe("Vendu");
    expect(at(cells, 2).symbol).not.toBe("");
    expect(at(cells, 3).label).toBe("Bloqué");
    expect(at(cells, 3).symbol).not.toBe("");
  });

  it("conserve la référence de réservation fournie par l'API", () => {
    const layout = buildSeatMapLayout(
      [{ seat_number: 1, status: "sold", booking_reference: "BK-00042" }],
      { seatsPerRow: 1, aisle: false },
    );

    expect(at(row(layout.rows, 0), 0).bookingReference).toBe("BK-00042");
  });
});

describe("isSeatSelectable", () => {
  it("n'autorise que les sièges libres ou déjà sélectionnés", () => {
    expect(isSeatSelectable("free")).toBe(true);
    expect(isSeatSelectable("selected")).toBe(true);
    expect(isSeatSelectable("held")).toBe(false);
    expect(isSeatSelectable("reserved")).toBe(false);
    expect(isSeatSelectable("sold")).toBe(false);
    expect(isSeatSelectable("blocked")).toBe(false);
    expect(isSeatSelectable("unavailable")).toBe(false);
  });
});

describe("seatAriaLabel", () => {
  it("exprime l'état en texte, pas seulement par la couleur", () => {
    expect(seatAriaLabel(12, "sold")).toBe("Siège 12, Vendu");
    expect(seatAriaLabel(3, "free")).toBe("Siège 3, Libre");
  });
});

describe("countSeatsByStatus", () => {
  it("compte les places par état", () => {
    const counts = countSeatsByStatus([
      { seat_number: 1, status: "free" },
      { seat_number: 2, status: "free" },
      { seat_number: 3, status: "sold" },
    ]);

    expect(counts).toEqual({ free: 2, sold: 1 });
  });
});

describe("seatStatusLabel", () => {
  it("donne un libellé lisible à chaque état", () => {
    expect(seatStatusLabel("pending_payment")).toBe("Paiement en attente");
    expect(seatStatusLabel("unavailable")).toBe("Indisponible");
  });
});

describe("selectableSeatNumbers (#8620)", () => {
  it("ne retient que les sièges réellement libres de l'inventaire", () => {
    const seats: SeatInput[] = [
      { seat_number: 3, status: "free" },
      { seat_number: 1, status: "sold" },
      { seat_number: 2, status: "free" },
      { seat_number: 4, status: "blocked" },
      { seat_number: 5, status: "reserved" },
    ];

    expect(selectableSeatNumbers(seats)).toEqual([2, 3]);
  });

  it("ne fabrique aucun numéro au-delà de l'inventaire", () => {
    const seats: SeatInput[] = [{ seat_number: 1, status: "free" }];
    expect(selectableSeatNumbers(seats)).toEqual([1]);
  });
});

describe("resolveSeatsPerRow (#8620)", () => {
  it("déduit le gabarit de la capacité réelle", () => {
    expect(resolveSeatsPerRow(12)).toBe(3);
    expect(resolveSeatsPerRow(16)).toBe(4);
    expect(resolveSeatsPerRow(0)).toBe(4);
  });
});

describe("resolveSeatLayout (#8646)", () => {
  it("privilégie la disposition réelle du véhicule sur la déduction", () => {
    // Un 30 places réellement en 2+1 ne doit PAS être affiché en 2+2.
    const layout = resolveSeatLayout(30, {
      seats_per_row: 3,
      aisle_after: 1,
      is_configured: true,
    });

    expect(layout).toEqual({ seatsPerRow: 3, aisleAfter: 1, isConfigured: true });
  });

  it("retombe sur la déduction quand aucun véhicule n'est configuré", () => {
    expect(resolveSeatLayout(30, null)).toEqual({
      seatsPerRow: 4,
      aisleAfter: 2,
      isConfigured: false,
    });
    expect(resolveSeatLayout(12, null)).toEqual({
      seatsPerRow: 3,
      aisleAfter: 2,
      isConfigured: false,
    });
  });

  it("respecte un couloir absent explicitement demandé", () => {
    expect(
      resolveSeatLayout(30, { seats_per_row: 2, aisle_after: null, is_configured: true }),
    ).toEqual({ seatsPerRow: 2, aisleAfter: null, isConfigured: true });
  });

  it("refuse une géométrie serveur incohérente et retombe sur le repli", () => {
    // Couloir après la dernière colonne : plan impossible, on ne le rend pas.
    expect(
      resolveSeatLayout(30, { seats_per_row: 3, aisle_after: 3, is_configured: true }),
    ).toEqual({ seatsPerRow: 4, aisleAfter: 2, isConfigured: false });

    // Colonnes hors bornes.
    expect(
      resolveSeatLayout(30, { seats_per_row: 9, aisle_after: null, is_configured: true }),
    ).toEqual({ seatsPerRow: 4, aisleAfter: 2, isConfigured: false });
  });

  it("place le couloir là où le serveur l'indique dans la grille", () => {
    const layout = buildSeatMapLayout(
      Array.from({ length: 30 }, (_, i) => ({ seat_number: i + 1, status: "free" as const })),
      { seatsPerRow: 3, aisleAfter: 1 },
    );

    // 3 colonnes de sièges + 1 colonne de couloir.
    expect(layout.columns).toBe(4);
    expect(layout.aisleColumn).toBe(1);
    expect(layout.seatsPerRow).toBe(3);
    expect(layout.rows).toHaveLength(10);
  });
});
