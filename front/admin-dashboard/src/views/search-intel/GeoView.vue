<template>
  <div class="p-6">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 class="text-3xl font-bold text-slate-900 dark:text-white">
          {{ t('searchIntel.geo.title', 'GEO — visibilite dans les moteurs generatifs') }}
        </h1>
        <p class="mt-1 text-slate-500 dark:text-slate-400">
          {{ t('searchIntel.geo.subtitle', 'Requetes suivies et preuves d\'apparition, avec leur niveau de preuve.') }}
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
      :empty-message="t('searchIntel.geo.empty', 'Aucune requete suivie.')"
      :search-placeholder="t('searchIntel.common.search', 'Rechercher...')"
    >
      <template #cell-query="{ row }">
        <span class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ row.query }}</span>
      </template>
      <template #cell-priority="{ row }">
        <StatusBadge :status="row.priority" :map="priorityMap" />
      </template>
      <template #cell-checks="{ row }">
        <span class="text-xs text-slate-500">
          {{ row.checks_count ?? 0 }} {{ t('searchIntel.geo.checks', 'verifications') }}
        </span>
      </template>
      <template #cell-actions="{ row }">
        <RowActionButton
          :label="t('searchIntel.geo.viewChecks', 'Voir les preuves')"
          variant="secondary"
          @click="openChecks(row)"
        />
      </template>
    </DataTable>

    <!-- Panneau de preuves : chaque verification porte son badge de methode.
         Un test ponctuel ne peut pas etre affiche sans son niveau de preuve
         (regle du backend, `method_badge` toujours present). -->
    <div v-if="selected" class="mt-6 card p-5">
      <div class="mb-4 flex items-start justify-between gap-4">
        <div>
          <h2 class="text-lg font-bold text-slate-900 dark:text-white">
            {{ t('searchIntel.geo.checksFor', 'Preuves pour') }} « {{ selected.query }} »
          </h2>
          <p class="text-xs text-slate-500">
            {{ t('searchIntel.geo.checksHint', 'Chaque ligne indique la methode d\'observation et sa portee.') }}
          </p>
        </div>
        <button class="text-sm font-bold text-slate-500 underline" @click="selected = null">
          {{ t('searchIntel.common.close', 'Fermer') }}
        </button>
      </div>

      <div v-if="checksLoading" class="py-6 text-center text-sm text-slate-500">
        {{ t('searchIntel.common.loading', 'Chargement...') }}
      </div>
      <p v-else-if="checks.length === 0" class="py-6 text-center text-sm text-slate-500">
        {{ t('searchIntel.geo.noChecks', 'Aucune verification enregistree pour cette requete.') }}
      </p>
      <div v-else class="divide-y divide-slate-200/60 dark:divide-slate-800/60">
        <div v-for="check in checks" :key="check.id" class="py-3">
          <div class="flex flex-wrap items-center gap-2">
            <span class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ check.engine }}</span>
            <span class="rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[10px] font-black uppercase text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300">
              {{ check.method_badge }}
            </span>
            <span
              :class="check.is_present ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400'"
              class="text-xs font-black uppercase"
            >
              {{ check.is_present
                ? t('searchIntel.geo.present', 'Present')
                : t('searchIntel.geo.absent', 'Absent') }}
            </span>
            <span v-if="check.position" class="text-xs text-slate-500">#{{ check.position }}</span>
          </div>
          <p v-if="check.method_description" class="mt-1 text-xs text-slate-500">
            {{ check.method_description }}
          </p>
          <p v-if="check.observed_text" class="mt-1 text-xs italic text-slate-600 dark:text-slate-400">
            « {{ check.observed_text }} »
          </p>
          <p class="mt-1 text-[11px] text-slate-400">
            {{ formatDate(check.observed_at) }}
            <span v-if="!check.supports_quantified_claim">
              — {{ t('searchIntel.geo.noQuantifiedClaim', 'ne permet pas une affirmation chiffree') }}
            </span>
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '@/services/api'
import DataTable from '@/components/common/DataTable.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import RowActionButton from '@/components/common/RowActionButton.vue'
import { useSearchIntelList, formatDate } from '@/composables/useSearchIntelList'
import { useLocaleStore } from '@/stores/locale'
import { translate } from '@/i18n/index.js'

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

/**
 * GSI-10 (#8521) — vue GEO.
 *
 * Le point sensible de GEO est la preuve : une apparition dans un moteur
 * generatif n'est pas une metrique stable. Le backend expose donc
 * `method_badge` / `supports_quantified_claim` sur chaque verification, et cet
 * ecran les affiche systematiquement — jamais un « present » nu.
 */
const { data, loading, error, load } = useSearchIntelList('/search-intel/geo/queries')

const selected = ref(null)
const checks = ref([])
const checksLoading = ref(false)

const columns = computed(() => [
  { key: 'query', label: t('searchIntel.geo.colQuery', 'Requete') },
  { key: 'priority', label: t('searchIntel.geo.colPriority', 'Priorite') },
  { key: 'checks', label: t('searchIntel.geo.colChecks', 'Verifications') },
  { key: 'actions', label: t('searchIntel.common.actions', 'Actions') },
])

const priorityMap = {
  high: { label: t('searchIntel.priority.high', 'Haute'), color: 'red' },
  medium: { label: t('searchIntel.priority.medium', 'Moyenne'), color: 'yellow' },
  low: { label: t('searchIntel.priority.low', 'Basse'), color: 'gray' },
}

async function openChecks(row) {
  selected.value = row
  checks.value = []
  checksLoading.value = true
  try {
    const response = await api.get(`/platform/search-intel/geo/queries/${row.id}/checks`, {
      _skipToast: true,
    })
    checks.value = response.data?.data ?? []
  } catch {
    checks.value = []
  } finally {
    checksLoading.value = false
  }
}

onMounted(() => load())
</script>
