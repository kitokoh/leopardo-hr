"use client";

import { useMemo } from "react";

import {
  buildSeatMapLayout,
  countSeatsByStatus,
  isSeatSelectable,
  LEGEND_ORDER,
  seatAriaLabel,
  seatStatusLabel,
  type SeatInput,
  type SeatStatusValue,
} from "@/lib/seat-layout";

/**
 * Mission Leopardo — Travel OS (#8602) : plan de sièges VISUEL du parcours
 * grand public.
 *
 * - Rendu à partir de données RÉELLES (`seats` renvoyés par l'API du trajet +
 *   `totalSeats` du véhicule affecté) — jamais d'illustration statique.
 * - Représente la disposition physique : rangées, couloir, sièges simples ou
 *   doubles (`seatsPerRow`), dernière rangée partielle.
 * - États distinguables AUTREMENT que par la couleur : chaque siège porte un
 *   symbole + un `aria-label` texte, et la légende est en icône + libellé.
 * - Le plan n'est JAMAIS la source de vérité : la disponibilité est revalidée
 *   côté serveur avant toute confirmation. Un siège occupé pendant la
 *   sélection est refusé (409) et le plan est rafraîchi.
 */

export interface SeatMapProps {
  /** Sièges réels renvoyés par l'API du trajet. */
  seats: SeatInput[];
  /** Sièges sélectionnés localement (état de vue, non persisté). */
  selected?: number[];
  /** Appelée quand l'utilisateur clique un siège. Absente = lecture seule. */
  onToggle?: (seatNumber: number) => void;
  /** Sièges par rangée (2+2 → 4, 2+1 → 3). Défaut : 4. */
  seatsPerRow?: number;
  /** Afficher la légende. Défaut : true. */
  showLegend?: boolean;
  /** Afficher le résumé des comptes par état. Défaut : true. */
  showSummary?: boolean;
  className?: string;
}

export function SeatMap({
  seats,
  selected = [],
  onToggle,
  seatsPerRow = 4,
  showLegend = true,
  showSummary = true,
  className,
}: SeatMapProps) {
  const selectedSet = useMemo(() => new Set(selected), [selected]);

  // Projette l'état persisté + la sélection locale en un état de vue.
  const viewSeats: SeatInput[] = useMemo(
    () =>
      seats.map((seat) => ({
        ...seat,
        status: selectedSet.has(seat.seat_number) ? "selected" : seat.status,
      })),
    [seats, selectedSet],
  );

  const layout = useMemo(
    () => buildSeatMapLayout(viewSeats, { seatsPerRow }),
    [viewSeats, seatsPerRow],
  );

  const counts = useMemo(() => countSeatsByStatus(seats), [seats]);
  const totalSeats = seats.length;
  const freeSeats = counts.free ?? 0;

  return (
    <section
      className={className}
      aria-label="Plan des sièges du véhicule"
      data-testid="seat-map"
    >
      {showSummary ? (
        <p className="mb-3 text-sm text-slate-600">
          <strong>{freeSeats}</strong> place{freeSeats > 1 ? "s" : ""} libre
          {freeSeats > 1 ? "s" : ""} sur <strong>{totalSeats}</strong>
          {selected.length > 0 ? (
            <>
              {" "}
              — <strong>{selected.length}</strong> sélectionnée
              {selected.length > 1 ? "s" : ""}
            </>
          ) : null}
        </p>
      ) : null}

      <div className="overflow-x-auto">
        <div
          className="mx-auto inline-grid gap-1.5"
          style={{ gridTemplateColumns: `repeat(${layout.columns}, minmax(0, 1fr))` }}
        >
          {layout.rows.flatMap((row) =>
            row.cells.map((cell, index) => {
              const key = `${row.rowNumber}-${index}`;

              if (cell.seatNumber === null) {
                return (
                  <span
                    key={key}
                    aria-hidden="true"
                    className="flex h-10 w-10 items-center justify-center text-xs text-slate-300"
                  >
                    │
                  </span>
                );
              }

              const status = cell.status as SeatStatusValue;
              const selectable = isSeatSelectable(status) && Boolean(onToggle);
              const isSelected = status === "selected";

              return (
                <button
                  key={key}
                  type="button"
                  disabled={!selectable}
                  onClick={selectable ? () => onToggle?.(cell.seatNumber as number) : undefined}
                  aria-label={seatAriaLabel(cell.seatNumber, status)}
                  aria-pressed={isSelected}
                  title={
                    cell.bookingReference
                      ? `${seatStatusLabel(status)} — ${cell.bookingReference}`
                      : seatStatusLabel(status)
                  }
                  className={[
                    "relative flex h-10 w-10 flex-col items-center justify-center rounded-md border text-[10px] font-semibold transition",
                    seatStateClasses(status),
                    selectable ? "cursor-pointer hover:brightness-95" : "cursor-not-allowed",
                  ].join(" ")}
                  data-status={status}
                >
                  <span className="text-xs leading-none">{cell.seatNumber}</span>
                  {cell.symbol ? (
                    <span aria-hidden="true" className="text-[10px] leading-none">
                      {cell.symbol}
                    </span>
                  ) : null}
                </button>
              );
            }),
          )}
        </div>
      </div>

      {showLegend ? <SeatMapLegend /> : null}
    </section>
  );
}

/** Classes par état — la couleur est un indice, jamais la seule information. */
function seatStateClasses(status: SeatStatusValue): string {
  switch (status) {
    case "free":
      return "border-slate-300 bg-white text-slate-700";
    case "selected":
      return "border-blue-600 bg-blue-600 text-white ring-2 ring-blue-300";
    case "held":
      return "border-amber-400 bg-amber-100 text-amber-800";
    case "reserved":
      return "border-orange-400 bg-orange-100 text-orange-800";
    case "pending_payment":
      return "border-violet-400 bg-violet-100 text-violet-800";
    case "sold":
      return "border-slate-400 bg-slate-500 text-white";
    case "blocked":
      return "border-red-400 bg-red-100 text-red-800 line-through";
    case "unavailable":
      return "border-slate-200 bg-slate-100 text-slate-400";
    default:
      return "border-slate-200 bg-white text-slate-500";
  }
}

/** Légende accessible : symbole + libellé texte pour chaque état. */
export function SeatMapLegend() {
  return (
    <ul
      className="mt-4 flex flex-wrap gap-x-4 gap-y-2 text-xs text-slate-600"
      aria-label="Légende des états de siège"
    >
      {LEGEND_ORDER.map((status) => (
        <li key={status} className="flex items-center gap-1.5">
          <span
            aria-hidden="true"
            className={[
              "flex h-5 w-5 items-center justify-center rounded border text-[10px]",
              seatStateClasses(status),
            ].join(" ")}
          >
            {status === "free" ? "1" : "•"}
          </span>
          <span>{seatStatusLabel(status)}</span>
        </li>
      ))}
    </ul>
  );
}
