<template>
  <div class="p-6">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 class="text-3xl font-bold text-slate-900 dark:text-white">
          {{ t('searchIntel.aeo.title', 'AEO — questions & reponses') }}
        </h1>
        <p class="mt-1 text-slate-500 dark:text-slate-400">
          {{ t('searchIntel.aeo.subtitle', 'Questions ciblees, brouillons et publication.') }}
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
      :empty-message="t('searchIntel.aeo.empty', 'Aucune question.')"
      :search-placeholder="t('searchIntel.common.search', 'Rechercher...')"
    >
      <template #cell-question="{ row }">
        <div class="max-w-md">
          <p class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ row.question }}</p>
          <p v-if="row.answer_draft" class="mt-1 line-clamp-2 text-xs text-slate-500">
            {{ row.answer_draft }}
          </p>
        </div>
      </template>
      <template #cell-status="{ row }">
        <StatusBadge :status="row.status" :map="statusMap" />
      </template>
      <template #cell-locale="{ row }">
        <span class="text-xs font-bold uppercase text-slate-500">{{ row.locale }}</span>
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
import { computed, onMounted } from 'vue'
import api from '@/services/api'
import DataTable from '@/components/common/DataTable.vue'
import StatusBadge from '@/components/common/StatusBadge.vue'
import RowActionButton from '@/components/common/RowActionButton.vue'
import { useSearchIntelList } from '@/composables/useSearchIntelList'
import { useLocaleStore } from '@/stores/locale'
import { translate } from '@/i18n/index.js'

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

/**
 * GSI-10 (#8521) — vue AEO.
 *
 * Le cycle AEO n'est pas lineaire : `submit`, `approve`, `publish` et
 * `to-update` sont des endpoints distincts cote API. On les mappe sur les
 * transitions autorisees renvoyees par le backend, sans reinventer la machine
 * a etats cote front.
 */
const { data, loading, error, load } = useSearchIntelList('/search-intel/aeo/questions')

const columns = computed(() => [
  { key: 'question', label: t('searchIntel.aeo.colQuestion', 'Question') },
  { key: 'locale', label: t('searchIntel.aeo.colLocale', 'Langue') },
  { key: 'sector', label: t('searchIntel.aeo.colSector', 'Secteur') },
  { key: 'status', label: t('searchIntel.aeo.colStatus', 'Statut') },
  { key: 'actions', label: t('searchIntel.common.actions', 'Actions') },
])

const statusMap = {
  draft: { label: t('searchIntel.aeoStatus.draft', 'Brouillon'), color: 'gray' },
  in_review: { label: t('searchIntel.aeoStatus.in_review', 'En revue'), color: 'yellow' },
  approved: { label: t('searchIntel.aeoStatus.approved', 'Approuvee'), color: 'blue' },
  published: { label: t('searchIntel.aeoStatus.published', 'Publiee'), color: 'green' },
  to_update: { label: t('searchIntel.aeoStatus.to_update', 'A mettre a jour'), color: 'yellow' },
  archived: { label: t('searchIntel.aeoStatus.archived', 'Archivee'), color: 'gray' },
}

const statusLabel = (value) => statusMap[value]?.label ?? value

/** Endpoint dedie par transition : le contrat API ne les mutualise pas. */
const TRANSITION_ENDPOINTS = {
  in_review: 'submit',
  approved: 'approve',
  published: 'publish',
  to_update: 'to-update',
}

async function transition(row, status) {
  const action = TRANSITION_ENDPOINTS[status]
  if (!action) return
  try {
    await api.post(`/platform/search-intel/aeo/questions/${row.id}/${action}`)
  } catch {
    // ignore : le rechargement reflete l'etat reel
  }
  await load()
}

onMounted(() => load())
</script>
