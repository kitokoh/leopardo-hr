<template>
  <div class="p-6">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 class="text-3xl font-bold text-slate-900 dark:text-white">
          {{ t('searchIntel.seo.title', 'SEO — problemes detectes') }}
        </h1>
        <p class="mt-1 text-slate-500 dark:text-slate-400">
          {{ t('searchIntel.seo.subtitle', 'Problemes issus du dernier scan, avec leur transition possible.') }}
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
      :empty-message="t('searchIntel.seo.empty', 'Aucun probleme detecte.')"
      :search-placeholder="t('searchIntel.common.search', 'Rechercher...')"
    >
      <template #cell-severity="{ row }">
        <StatusBadge :status="row.severity" :map="severityMap" />
      </template>
      <template #cell-status="{ row }">
        <StatusBadge :status="row.status" :map="statusMap" />
      </template>
      <template #cell-detected_at="{ row }">
        <span class="text-xs text-slate-500">{{ formatDate(row.detected_at) }}</span>
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
 * GSI-10 (#8521) — vue SEO.
 *
 * Les transitions proposees viennent du backend (`allowed_transitions`), pas
 * d'une table dupliquee cote front : proposer un bouton que l'API refuserait en
 * 422 serait une fausse promesse.
 */
const { data, loading, error, load } = useSearchIntelList('/search-intel/seo/issues')

const columns = computed(() => [
  { key: 'title', label: t('searchIntel.seo.colTitle', 'Probleme') },
  { key: 'type', label: t('searchIntel.seo.colType', 'Type') },
  { key: 'severity', label: t('searchIntel.seo.colSeverity', 'Gravite') },
  { key: 'status', label: t('searchIntel.seo.colStatus', 'Statut') },
  { key: 'url', label: t('searchIntel.seo.colUrl', 'Page') },
  { key: 'detected_at', label: t('searchIntel.seo.colDetected', 'Detecte le') },
  { key: 'actions', label: t('searchIntel.common.actions', 'Actions') },
])

const severityMap = {
  critical: { label: t('searchIntel.severity.critical', 'Critique'), color: 'red' },
  high: { label: t('searchIntel.severity.high', 'Haute'), color: 'red' },
  medium: { label: t('searchIntel.severity.medium', 'Moyenne'), color: 'yellow' },
  low: { label: t('searchIntel.severity.low', 'Basse'), color: 'gray' },
}

const statusMap = {
  open: { label: t('searchIntel.seoStatus.open', 'Ouvert'), color: 'red' },
  in_progress: { label: t('searchIntel.seoStatus.in_progress', 'En cours'), color: 'yellow' },
  applied: { label: t('searchIntel.seoStatus.applied', 'Applique'), color: 'blue' },
  verified: { label: t('searchIntel.seoStatus.verified', 'Verifie'), color: 'green' },
  ignored: { label: t('searchIntel.seoStatus.ignored', 'Ignore'), color: 'gray' },
}

const statusLabel = (value) => statusMap[value]?.label ?? value

async function transition(row, status) {
  try {
    await api.patch(`/platform/search-intel/seo/issues/${row.id}/transition`, { status })
    await load()
  } catch {
    // Le rechargement suffit : si l'etat a change entre l'affichage et le clic,
    // la liste reflete la realite au lieu de laisser un bouton mort.
    await load()
  }
}

onMounted(() => load())
</script>
