<template>
  <div class="p-6">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 class="text-3xl font-bold text-slate-900 dark:text-white">
          {{ t('searchIntel.sea.title', 'SEA — campagnes payantes') }}
        </h1>
        <p class="mt-1 text-slate-500 dark:text-slate-400">
          {{ t('searchIntel.sea.subtitle', 'Campagnes, budgets et consommation reelle.') }}
        </p>
      </div>
      <button class="btn-secondary" :disabled="loading" @click="load()">
        {{ t('searchIntel.common.refresh', 'Rafraichir') }}
      </button>
    </div>

    <!-- Banniere de suivi manuel : le module ne collecte pas les depenses tout
         seul. Sans ce rappel, un budget a 0 % de consommation se lirait comme
         « rien depense » alors qu'aucun releve n'a ete saisi. -->
    <div
      v-if="trackingMode === 'manual'"
      class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-800 dark:border-amber-900/30 dark:bg-amber-950/20 dark:text-amber-400"
    >
      {{ t('searchIntel.sea.manualTracking', 'Suivi manuel : les depenses proviennent de releves saisis, pas d\'une API regie.') }}
    </div>

    <div
      v-if="error"
      class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700 dark:border-red-900/30 dark:bg-red-950/20 dark:text-red-400"
    >
      {{ error === 'forbidden'
        ? t('searchIntel.common.forbidden', 'Vous n\'avez pas la permission search_intel.view.')
        : t('searchIntel.common.loadError', 'Impossible de charger les donnees.') }}
      <button v-if="error !== 'forbidden'" class="ml-3 underline" @click="load()">
        {{ t('searchIntel.common.retry', 'Reessayer') }}
      </button>
    </div>

    <DataTable
      :columns="columns"
      :rows="data"
      :loading="loading"
      :empty-message="t('searchIntel.sea.empty', 'Aucune campagne.')"
      :search-placeholder="t('searchIntel.common.search', 'Rechercher...')"
    >
      <template #cell-status="{ row }">
        <StatusBadge :status="row.status" :map="statusMap" />
      </template>
      <template #cell-budget="{ row }">
        <div class="text-sm font-bold text-slate-800 dark:text-slate-200">
          {{ formatCents(row.spent_cents, row.currency) }}
          <span class="text-xs font-normal text-slate-500">
            / {{ formatCents(row.total_budget_cents, row.currency) }}
          </span>
        </div>
        <div class="mt-1 h-1.5 w-32 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
          <div
            class="h-full rounded-full"
            :class="consumptionClass(row.budget_consumption_ratio)"
            :style="{ width: `${Math.min(100, Math.round((row.budget_consumption_ratio ?? 0) * 100))}%` }"
          />
        </div>
      </template>
      <template #cell-measured_days="{ row }">
        <span class="text-xs text-slate-500">
          {{ row.measured_days ?? 0 }} {{ t('searchIntel.sea.days', 'j') }}
        </span>
      </template>
      <template #cell-actions="{ row }">
        <div class="flex flex-wrap gap-1">
          <RowActionButton
            v-for="next in row.allowed_transitions || []"
            :key="next"
            :label="statusLabel(next)"
            variant="secondary"
            @click="transition(row, next)"
          />
        </div>
      </template>
    </DataTable>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '@/services/api'
import DataTable from '@/components/common/DataTable.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import RowActionButton from '@/components/common/RowActionButton.vue'
import { useSearchIntelList, formatCents } from '@/composables/useSearchIntelList'
import { useLocaleStore } from '@/stores/locale'
import { translate } from '@/i18n/index.js'

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

/**
 * GSI-10 (#8521) — vue SEA.
 *
 * `tracking_mode` vient du backend : quand il vaut `manual`, la banniere le dit
 * explicitement. Un ecran qui affiche « 0 EUR depense » sans preciser qu'aucun
 * releve n'est branche est un ecran qui ment par omission.
 */
const { data, meta, loading, error, load } = useSearchIntelList('/search-intel/sea/campaigns')
const trackingMode = ref(null)

const columns = computed(() => [
  { key: 'name', label: t('searchIntel.sea.colName', 'Campagne') },
  { key: 'channel', label: t('searchIntel.sea.colChannel', 'Canal') },
  { key: 'status', label: t('searchIntel.sea.colStatus', 'Statut') },
  { key: 'budget', label: t('searchIntel.sea.colBudget', 'Depense / budget') },
  { key: 'measured_days', label: t('searchIntel.sea.colMeasured', 'Jours mesures') },
  { key: 'actions', label: t('searchIntel.common.actions', 'Actions') },
])

const statusMap = {
  draft: { label: t('searchIntel.seaStatus.draft', 'Brouillon'), color: 'gray' },
  pending_validation: { label: t('searchIntel.seaStatus.pending_validation', 'A valider'), color: 'yellow' },
  active: { label: t('searchIntel.seaStatus.active', 'Active'), color: 'green' },
  paused: { label: t('searchIntel.seaStatus.paused', 'En pause'), color: 'yellow' },
  completed: { label: t('searchIntel.seaStatus.completed', 'Terminee'), color: 'blue' },
  cancelled: { label: t('searchIntel.seaStatus.cancelled', 'Annulee'), color: 'gray' },
}

const statusLabel = (value) => statusMap[value]?.label ?? value

function consumptionClass(ratio) {
  const r = Number(ratio ?? 0)
  if (r >= 1) return 'bg-red-500'
  if (r >= 0.8) return 'bg-amber-500'
  return 'bg-emerald-500'
}

async function transition(row, status) {
  try {
    await api.post(`/platform/search-intel/sea/campaigns/${row.id}/transition`, { status })
  } catch {
    // ignore : le rechargement ci-dessous reflete l'etat reel
  }
  await load()
}

async function loadAll() {
  await load()
  trackingMode.value = meta.value?.tracking_mode ?? null
}

onMounted(loadAll)
</script>
