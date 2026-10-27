import { describe, expect, it } from "vitest";

import type { SeatInput } from "./seat-layout";
import {
  reconcileSeatSelection,
  submittableSeatNumbers,
  unavailableSeatNumbers,
  type SeatSelectionDraft,
} from "./seat-selection";

/**
 * #8676 — « siège devenu indisponible entre deux rafraîchissements ».
 *
 * L'issue demandait une spec Playwright. `front/travel-web` n'a AUCUNE
 * infrastructure Playwright (pas de config, pas de dépendance, pas de job CI) :
 * en ajouter une pour un seul scénario coûte plus cher que le bénéfice. Le
 * comportement est donc couvert ici en Vitest (infra déjà présente), sur une
 * logique extraite du composant pour être testable sans navigateur.
 *
 * Chaque test reprend un critère d'acceptation de l'issue.
 */

function seat(seat_number: number, status: SeatInput["status"]): SeatInput {
  return { seat_number, status };
}

const draft = (seat_number: number | null): SeatSelectionDraft => ({ seat_number });

describe("#8676 — sélection de siège après rafraîchissement d'inventaire", () => {
  it("retire de la sélection un siège devenu `sold` entre deux rafraîchissements", () => {
    // Avant : siège 12 libre, sélectionné. Après : vendu.
    const before = [seat(11, "free"), seat(12, "free"), seat(13, "free")];
    expect(unavailableSeatNumbers(before).has(12)).toBe(false);

    const passengers = [draft(12)];
    const after = [seat(11, "free"), seat(12, "sold"), seat(13, "free")];

    const result = reconcileSeatSelection(passengers, after);

    // Critère : le siège est désélectionné et remis à `null`.
    expect(result.passengers[0]?.seat_number).toBeNull();
    expect(result.lostSeats).toEqual([12]);
    expect(result.changed).toBe(true);
  });

  it("signale le retrait pour que le message `seatTakenDuringSelection` soit affiché", () => {
    const result = reconcileSeatSelection([draft(7)], [seat(7, "sold")]);

    // `changed` est le seul signal dont le composant a besoin : il pilote
    // l'affichage du message dans une région `role="status"`.
    expect(result.changed).toBe(true);
  });

  it("ne touche à rien quand aucun siège sélectionné n'est devenu indisponible", () => {
    const passengers = [draft(3)];
    const result = reconcileSeatSelection(passengers, [
      seat(3, "free"),
      seat(4, "sold"),
    ]);

    expect(result.changed).toBe(false);
    expect(result.lostSeats).toEqual([]);
    // Identité préservée : aucun re-render inutile.
    expect(result.passengers).toBe(passengers);
    expect(result.passengers[0]?.seat_number).toBe(3);
  });

  it("ne transmet aucun `seat_number` fantôme à la commande", () => {
    const result = reconcileSeatSelection([draft(12), draft(13)], [
      seat(12, "sold"),
      seat(13, "free"),
    ]);

    // Critère : la soumission reste possible, mais le siège perdu n'est plus
    // transmis.
    expect(submittableSeatNumbers(result.passengers)).toEqual([13]);
  });

  it("traite tout statut non `free` comme indisponible (fail-closed)", () => {
    // `held` = verrouillé par une réservation en cours : il ne doit PAS
    // réintégrer la sélection, même s'il n'est pas encore vendu.
    for (const status of [
      "held",
      "reserved",
      "pending_payment",
      "sold",
      "blocked",
      "unavailable",
    ] as const) {
      const result = reconcileSeatSelection([draft(5)], [seat(5, status)]);
      expect(result.passengers[0]?.seat_number, `statut ${status}`).toBeNull();
    }
  });

  it("retire plusieurs sièges à la fois et les renvoie triés, sans doublon", () => {
    const result = reconcileSeatSelection([draft(9), draft(2), draft(9), draft(4)], [
      seat(2, "sold"),
      seat(4, "held"),
      seat(9, "sold"),
      seat(10, "free"),
    ]);

    expect(result.lostSeats).toEqual([2, 4, 9]);
    expect(result.passengers.every((p) => p.seat_number === null)).toBe(true);
  });

  it("conserve les autres champs du passager lors du retrait", () => {
    const passengers = [
      { seat_number: 8, full_name: "Aminata", age_category: "adult" as const },
    ];

    const updated = reconcileSeatSelection(passengers, [seat(8, "sold")]).passengers[0];

    expect(updated?.seat_number).toBeNull();
    expect(updated?.full_name).toBe("Aminata");
    expect(updated?.age_category).toBe("adult");
  });

  it("ignore les passagers sans siège assigné", () => {
    const result = reconcileSeatSelection([draft(null)], [seat(1, "sold")]);

    expect(result.changed).toBe(false);
    expect(submittableSeatNumbers(result.passengers)).toEqual([]);
  });

  it("est robuste à un inventaire vide (plan non encore chargé)", () => {
    // Un inventaire vide ne doit pas désélectionner par erreur — il ne contient
    // simplement aucune information sur les sièges choisis.
    const result = reconcileSeatSelection([draft(4)], []);

    expect(result.changed).toBe(false);
    expect(result.passengers[0]?.seat_number).toBe(4);
  });

  it("ne mute jamais la sélection reçue", () => {
    const passengers = [draft(12)];
    reconcileSeatSelection(passengers, [seat(12, "sold")]);

    expect(passengers[0]?.seat_number).toBe(12);
  });
});
