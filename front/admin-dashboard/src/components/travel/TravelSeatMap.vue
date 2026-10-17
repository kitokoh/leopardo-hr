<template>
  <section class="seat-map" :aria-label="t('travel.seats.map.label')" data-testid="seat-map">
    <p v-if="showSummary" class="seat-map__summary">
      <strong>{{ freeSeats }}</strong> {{ t('travel.seats.summary.free') }}
      <strong>{{ seats.length }}</strong>
      <template v-if="selected.length > 0">
        — <strong>{{ selected.length }}</strong> {{ t('travel.seats.summary.selected') }}
      </template>
    </p>

    <div class="seat-map__scroll">
      <div
        class="seat-map__grid"
        :style="{ gridTemplateColumns: `repeat(${layout.columns}, minmax(0, 1fr))` }"
      >
        <template v-for="row in layout.rows" :key="row.rowNumber">
          <template v-for="(cell, index) in row.cells" :key="`${row.rowNumber}-${index}`">
            <span v-if="cell.seatNumber === null" class="seat-map__aisle" aria-hidden="true">│</span>
            <button
              v-else
              type="button"
              class="seat-map__seat"
              :class="[stateClass(cell.status), selectable(cell.status) ? 'is-clickable' : 'is-locked']"
              :disabled="!selectable(cell.status)"
              :aria-label="ariaLabel(cell.seatNumber, cell.status)"
              :aria-pressed="cell.status === 'selected' ? 'true' : 'false'"
              :title="cell.bookingReference ? `${cell.label} — ${cell.bookingReference}` : cell.label"
              :data-status="cell.status"
              @click="selectable(cell.status) && emit('toggle', cell.seatNumber)"
            >
              <span class="seat-map__number">{{ cell.seatNumber }}</span>
              <span v-if="cell.symbol" class="seat-map__symbol" aria-hidden="true">{{ cell.symbol }}</span>
            </button>
          </template>
        </template>
      </div>
    </div>

    <ul v-if="showLegend" class="seat-map__legend" :aria-label="t('travel.seats.legend.title')">
      <li v-for="status in legendOrder" :key="status" class="seat-map__legend-item">
        <span class="seat-map__legend-swatch" :class="stateClass(status)" aria-hidden="true">
          {{ status === 'free' ? '1' : '•' }}
        </span>
        <span>{{ label(status) }}</span>
      </li>
    </ul>
  </section>
</template>

<script setup>
/**
 * Mission Leopardo — Travel OS (#8602) : plan de sièges VISUEL du back-office
 * (responsable / agence / compagnie).
 *
 * Même source de vérité que le plan grand public (`seat-map.tsx`) : `seats`
 * provient de l'API du trajet (inventaire réel + réservations) et la disposition
 * est dérivée du véhicule affecté. Le responsable voit en plus la RÉFÉRENCE de
 * réservation rattachée au siège (`booking_reference`) via l'infobulle.
 *
 * Les états ne reposent jamais sur la seule couleur : symbole + libellé texte
 * + `aria-label`. Tous les libellés passent par le catalogue i18n
 * (`travel.seats.*`).
 *
 * Fichier en JavaScript : le back-office n'utilise aucun SFC TypeScript
 * (config ESLint partagée, parser Babel). On suit la convention du dépôt et de
 * ses vues travel existantes (`t(clé, repli)`).
 */
import { computed } from 'vue';

import { translate } from '@/i18n/index.js';
import { useLocaleStore } from '@/stores/locale.js';

const props = defineProps({
  /** Sièges réels renvoyés par l'API du trajet. */
  seats: { type: Array, required: true },
  /** Sièges sélectionnés localement (état de vue, non persisté). */
  selected: { type: Array, default: () => [] },
  /** Sièges par rangée (2+2 → 4, 2+1 → 3). */
  seatsPerRow: { type: Number, default: 4 },
  /**
   * #8646 — disposition renvoyée par l'API du trajet. Quand elle est fournie,
   * elle fait foi ([seatsPerRow] et [aisle] sont alors ignorés) : le plan
   * reflète le véhicule réel et non une déduction de capacité.
   */
  layout: { type: Object, default: null },
  /** Afficher la légende. */
  showLegend: { type: Boolean, default: true },
  /** Afficher le résumé des comptes par état. */
  showSummary: { type: Boolean, default: true },
  /** Couloir central (par défaut : dès 3 sièges par rangée). */
  aisle: { type: Boolean, default: undefined },
});

const emit = defineEmits(['toggle']);

const localeStore = useLocaleStore();
const t = (key, fallback = '') => translate(localeStore.current, key, fallback);

const legendOrder = [
  'free', 'selected', 'held', 'reserved', 'pending_payment', 'sold', 'blocked', 'unavailable',
];

/** Clé i18n par état — les libellés vivent dans le catalogue (`travel.seats.state.*`). */
const stateKeys = {
  free: 'travel.seats.state.free',
  selected: 'travel.seats.state.selected',
  held: 'travel.seats.state.held',
  reserved: 'travel.seats.state.reserved',
  pending_payment: 'travel.seats.state.pendingPayment',
  sold: 'travel.seats.state.sold',
  blocked: 'travel.seats.state.blocked',
  unavailable: 'travel.seats.state.unavailable',
  aisle: 'travel.seats.state.aisle',
};

/** Symboles non colorés par état — lisibles en niveaux de gris. */
const symbols = {
  free: '',
  selected: '✓✓',
  held: '◴',
  reserved: '●',
  pending_payment: '₿',
  sold: '✕',
  blocked: '✖',
  unavailable: '–',
  aisle: '',
};

/** Libellé localisé d'un état, résolu depuis le catalogue i18n. */
const label = (status) => t(stateKeys[status] ?? '', status);

const selectedSet = computed(() => new Set(props.selected));

const viewSeats = computed(() =>
  props.seats.map((seat) => ({
    ...seat,
    status: selectedSet.value.has(seat.seat_number) ? 'selected' : seat.status,
  })),
);

const useAisle = computed(() => {
  const serverAisle = props.layout?.aisle_after;
  if (serverAisle !== undefined && serverAisle !== null) return true;
  if (props.layout && serverAisle === null) return false;

  return props.aisle ?? serversSeatsPerRow() >= 3;
});

/**
 * #8646 — nombre de colonnes effectif : la géométrie serveur prime, sinon
 * [seatsPerRow] puis l'ancien comportement.
 */
function serversSeatsPerRow() {
  const server = Number(props.layout?.seats_per_row);
  return Number.isInteger(server) && server >= 1 && server <= 6 ? server : props.seatsPerRow;
}

const layout = computed(() => {
  const perRow = Math.max(1, Math.trunc(serversSeatsPerRow()));

  // #8646 — position du couloir : celle du serveur si elle est fournie, sinon
  // au milieu de la rangée (comportement historique).
  const serverAisle = props.layout ? props.layout.aisle_after : undefined;
  const aisleAfter =
    serverAisle !== undefined && serverAisle !== null
      ? Math.min(Math.max(1, Math.trunc(Number(serverAisle))), perRow - 1)
      : serverAisle === null
        ? null
        : useAisle.value
          ? Math.ceil(perRow / 2)
          : null;

  const sorted = [...viewSeats.value].sort((a, b) => a.seat_number - b.seat_number);

  const rows = [];
  for (let i = 0; i < sorted.length; i += perRow) {
    const chunk = sorted.slice(i, i + perRow);
    const cells = [];
    chunk.forEach((seat, index) => {
      cells.push({
        seatNumber: seat.seat_number,
        status: seat.status,
        symbol: symbols[seat.status] ?? '',
        label: label(seat.status),
        bookingReference: seat.booking_reference ?? null,
      });
      if (aisleAfter !== null && index + 1 === aisleAfter) {
        cells.push({ seatNumber: null, status: 'aisle', symbol: '', label: label('aisle'), bookingReference: null });
      }
    });
    rows.push({ rowNumber: rows.length + 1, cells });
  }

  return { rows, columns: perRow + (aisleAfter !== null ? 1 : 0), aisleColumn: aisleAfter };
});

const freeSeats = computed(() => props.seats.filter((s) => s.status === 'free').length);

const selectable = (status) => status === 'free' || status === 'selected';

const ariaLabel = (seatNumber, status) =>
  `${t('travel.seats.state.seat')} ${seatNumber}, ${label(status)}`;

function stateClass(status) {
  switch (status) {
    case 'free': return 'is-free';
    case 'selected': return 'is-selected';
    case 'held': return 'is-held';
    case 'reserved': return 'is-reserved';
    case 'pending_payment': return 'is-pending';
    case 'sold': return 'is-sold';
    case 'blocked': return 'is-blocked';
    case 'unavailable': return 'is-unavailable';
    default: return '';
  }
}
</script>

<style scoped>
.seat-map__summary { margin-bottom: 0.75rem; font-size: 0.875rem; color: #475569; }
.seat-map__scroll { overflow-x: auto; }
.seat-map__grid { display: inline-grid; gap: 0.375rem; margin: 0 auto; }
.seat-map__aisle { display: flex; width: 2.5rem; height: 2.5rem; align-items: center; justify-content: center; color: #cbd5e1; }
.seat-map__seat {
  position: relative; display: flex; flex-direction: column; align-items: center; justify-content: center;
  width: 2.5rem; height: 2.5rem; border-radius: 0.375rem; border: 1px solid #cbd5e1;
  font-size: 0.625rem; font-weight: 600; background: #fff; color: #334155;
}
.seat-map__seat.is-clickable { cursor: pointer; }
.seat-map__seat.is-locked { cursor: not-allowed; }
.seat-map__number { font-size: 0.75rem; line-height: 1; }
.seat-map__symbol { font-size: 0.625rem; line-height: 1; }
.is-free { border-color: #cbd5e1; background: #fff; color: #334155; }
.is-selected { border-color: #2563eb; background: #2563eb; color: #fff; box-shadow: 0 0 0 2px #bfdbfe; }
.is-held { border-color: #fbbf24; background: #fef3c7; color: #92400e; }
.is-reserved { border-color: #fb923c; background: #ffedd5; color: #9a3412; }
.is-pending { border-color: #a78bfa; background: #ede9fe; color: #5b21b6; }
.is-sold { border-color: #94a3b8; background: #64748b; color: #fff; }
.is-blocked { border-color: #f87171; background: #fee2e2; color: #991b1b; text-decoration: line-through; }
.is-unavailable { border-color: #e2e8f0; background: #f1f5f9; color: #94a3b8; }
.seat-map__legend { display: flex; flex-wrap: wrap; gap: 0.5rem 1rem; margin-top: 1rem; font-size: 0.75rem; color: #475569; list-style: none; padding: 0; }
.seat-map__legend-item { display: flex; align-items: center; gap: 0.375rem; }
.seat-map__legend-swatch { display: flex; width: 1.25rem; height: 1.25rem; align-items: center; justify-content: center; border-radius: 0.25rem; border: 1px solid #cbd5e1; font-size: 0.625rem; }
</style>
