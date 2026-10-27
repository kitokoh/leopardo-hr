<template>
  <div class="p-6">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 class="text-3xl font-bold text-slate-900 dark:text-white">
          {{ t('searchIntel.content.title', 'Contenu — production editoriale') }}
        </h1>
        <p class="mt-1 text-slate-500 dark:text-slate-400">
          {{ t('searchIntel.content.subtitle', 'Pieces planifiees, publiees et leur cycle de vie.') }}
        </p>
      </div>
      <div class="flex gap-2">
        <button class="btn-secondary" :disabled="loading" @click="load()">
          {{ t('searchIntel.common.refresh', 'Rafraichir') }}
        </button>
        <button class="btn-secondary" @click="exportCsv">
          {{ t('searchIntel.content.export', 'Exporter CSV') }}
        </button>
      </div>
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
      :empty-message="t('searchIntel.content.empty', 'Aucun contenu.')"
      :search-placeholder="t('searchIntel.common.search', 'Rechercher...')"
    >
      <template #cell-title="{ row }">
        <div class="max-w-sm">
          <p class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ row.title }}</p>
          <p v-if="row.target_url" class="truncate text-xs text-slate-500">{{ row.target_url }}</p>
        </div>
      </template>
      <template #cell-status="{ row }">
        <StatusBadge :status="row.status" :map="statusMap" />
      </template>
      <template #cell-scheduled_at="{ row }">
        <span class="text-xs text-slate-500">{{ formatDate(row.scheduled_at) }}</span>
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
import { useSearchIntelList, formatDate } from '@/composables/useSearchIntelList'
import { useLocaleStore } from '@/stores/locale'
import { translate } from '@/i18n/index.js'

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

/**
 * GSI-10 (#8521) — vue Contenu.
 *
 * L'export passe par l'endpoint dedie (`/content/export`) et non par une
 * serialisation CSV cote navigateur : le backend applique les memes filtres que
 * la liste, donc l'export ne peut pas diverger de ce que l'ecran affiche.
 */
const { data, loading, error, load } = useSearchIntelList('/search-intel/content')

const columns = computed(() => [
  { key: 'title', label: t('searchIntel.content.colTitle', 'Titre') },
  { key: 'kind', label: t('searchIntel.content.colKind', 'Type') },
  { key: 'locale', label: t('searchIntel.content.colLocale', 'Langue') },
  { key: 'status', label: t('searchIntel.content.colStatus', 'Statut') },
  { key: 'scheduled_at', label: t('searchIntel.content.colScheduled', 'Planifie le') },
  { key: 'actions', label: t('searchIntel.common.actions', 'Actions') },
])

const statusMap = {
  idea: { label: t('searchIntel.contentStatus.idea', 'Idee'), color: 'gray' },
  draft: { label: t('searchIntel.contentStatus.draft', 'Brouillon'), color: 'gray' },
  in_review: { label: t('searchIntel.contentStatus.in_review', 'En revue'), color: 'yellow' },
  approved: { label: t('searchIntel.contentStatus.approved', 'Approuve'), color: 'blue' },
  scheduled: { label: t('searchIntel.contentStatus.scheduled', 'Planifie'), color: 'blue' },
  published: { label: t('searchIntel.contentStatus.published', 'Publie'), color: 'green' },
  archived: { label: t('searchIntel.contentStatus.archived', 'Archive'), color: 'gray' },
}

const statusLabel = (value) => statusMap[value]?.label ?? value

async function transition(row, status) {
  try {
    await api.post(`/platform/search-intel/content/${row.id}/transition`, { status })
  } catch {
    // ignore : le rechargement reflete l'etat reel
  }
  await load()
}

async function exportCsv() {
  try {
    const response = await api.get('/platform/search-intel/content/export', {
      responseType: 'blob',
      _skipToast: true,
    })
    const url = URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.download = 'search-intel-content.csv'
    link.click()
    URL.revokeObjectURL(url)
  } catch {
    // L'erreur est deja visible via le bandeau de la liste si elle est bloquante.
  }
}

onMounted(() => load())
</script>
