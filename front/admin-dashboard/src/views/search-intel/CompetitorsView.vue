<template>
  <div class="p-6">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 class="text-3xl font-bold text-slate-900 dark:text-white">
          {{ t('searchIntel.competitors.title', 'Concurrents') }}
        </h1>
        <p class="mt-1 text-slate-500 dark:text-slate-400">
          {{ t('searchIntel.competitors.subtitle', 'Positionnement observe, sources et fraicheur de l\'information.') }}
        </p>
      </div>
      <button class="btn-secondary" :disabled="loading" @click="load()">
        {{ t('searchIntel.common.refresh', 'Rafraichir') }}
      </button>
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
      :empty-message="t('searchIntel.competitors.empty', 'Aucun concurrent suivi.')"
      :search-placeholder="t('searchIntel.common.search', 'Rechercher...')"
    >
      <template #cell-name="{ row }">
        <div>
          <p class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ row.name }}</p>
          <a
            v-if="row.website_url"
            :href="row.website_url"
            target="_blank"
            rel="noopener noreferrer"
            class="text-xs text-brand-600 hover:underline dark:text-brand-400"
          >{{ row.website_url }}</a>
        </div>
      </template>
      <template #cell-positioning="{ row }">
        <p class="max-w-sm text-xs text-slate-600 dark:text-slate-400">
          {{ row.positioning || '—' }}
        </p>
      </template>
      <template #cell-freshness="{ row }">
        <span
          :class="row.is_stale
            ? 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-400'
            : 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400'"
          class="rounded-full border px-2 py-0.5 text-[10px] font-black uppercase"
        >
          {{ row.is_stale
            ? t('searchIntel.competitors.stale', 'A revoir (> 90 j)')
            : t('searchIntel.competitors.fresh', 'A jour') }}
        </span>
        <p class="mt-1 text-[11px] text-slate-400">{{ formatDate(row.observed_at) }}</p>
      </template>
      <template #cell-source="{ row }">
        <a
          v-if="row.source_url"
          :href="row.source_url"
          target="_blank"
          rel="noopener noreferrer"
          class="text-xs text-brand-600 hover:underline dark:text-brand-400"
        >{{ row.source_label || row.source_url }}</a>
        <span v-else class="text-xs text-slate-400">—</span>
      </template>
    </DataTable>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import DataTable from '@/components/common/DataTable.vue'
import { useSearchIntelList, formatDate } from '@/composables/useSearchIntelList'
import { useLocaleStore } from '@/stores/locale'
import { translate } from '@/i18n/index.js'

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

/**
 * GSI-10 (#8521) — vue Concurrents.
 *
 * `is_stale` est calcule cote serveur (regle des 90 jours) : le front ne la
 * reimplemente pas, sinon les deux regles divergeraient au premier ajustement.
 * Chaque fiche porte sa source — une affirmation concurrentielle sans source
 * n'est pas exploitable.
 */
const { data, loading, error, load } = useSearchIntelList('/search-intel/competitors')

const columns = computed(() => [
  { key: 'name', label: t('searchIntel.competitors.colName', 'Concurrent') },
  { key: 'market', label: t('searchIntel.competitors.colMarket', 'Marche') },
  { key: 'positioning', label: t('searchIntel.competitors.colPositioning', 'Positionnement') },
  { key: 'freshness', label: t('searchIntel.competitors.colFreshness', 'Fraicheur') },
  { key: 'source', label: t('searchIntel.competitors.colSource', 'Source') },
])

onMounted(() => load())
</script>
