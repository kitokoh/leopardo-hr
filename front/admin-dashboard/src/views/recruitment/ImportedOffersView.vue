<template>
  <div class="p-6">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 class="text-3xl font-bold text-slate-900 dark:text-white">
          {{ t('recruitment.offers.title', 'Offres d’emploi publiées') }}
        </h1>
        <p class="mt-1 text-slate-500 dark:text-slate-400">
          {{ t('recruitment.offers.subtitle', 'Offres des entreprises clientes et offres importées des agrégateurs.') }}
        </p>
      </div>
      <button class="btn-secondary" :disabled="loading" @click="load()">
        {{ t('recruitment.common.refresh', 'Rafraîchir') }}
      </button>
    </div>

    <div
      v-if="error"
      class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700 dark:border-red-900/30 dark:bg-red-950/20 dark:text-red-400"
    >
      {{ error === 'forbidden'
        ? t('recruitment.common.forbidden', 'Vous n’avez pas la permission recruitment.view.')
        : t('recruitment.common.loadError', 'Impossible de charger les données.') }}
    </div>

    <div v-if="loading" class="p-12 text-center text-sm text-slate-500">
      {{ t('recruitment.common.loading', 'Chargement…') }}
    </div>

    <div v-else-if="offers.length === 0" class="card p-8 text-center text-sm text-slate-500">
      {{ t('recruitment.offers.empty', 'Aucune offre importée pour le moment.') }}
    </div>

    <div v-else class="card overflow-hidden">
      <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-900">
          <tr>
            <th class="px-4 py-3">{{ t('recruitment.offers.job', 'Offre') }}</th>
            <th class="px-4 py-3">{{ t('recruitment.offers.company', 'Entreprise') }}</th>
            <th class="px-4 py-3">{{ t('recruitment.offers.location', 'Lieu') }}</th>
            <th class="px-4 py-3">{{ t('recruitment.offers.source', 'Source') }}</th>
            <th class="px-4 py-3">{{ t('recruitment.offers.published', 'Publiée le') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="offer in offers" :key="offer.id" class="border-t border-slate-100 dark:border-slate-800">
            <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">{{ offer.title }}</td>
            <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ offer.company_name }}</td>
            <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ offer.location || '—' }}</td>
            <td class="px-4 py-3 text-slate-600 dark:text-slate-400">
              {{ offer.source?.label || offer.source?.provider || '—' }}
            </td>
            <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ formatDate(offer.published_at) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import api from '@/services/api'
import { formatDate } from '@/composables/useSearchIntelList'
import { useLocaleStore } from '@/stores/locale'
import { translate } from '@/i18n/index.js'

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

/**
 * BC-10 (#9100) — offres importées des agrégateurs.
 *
 * Vue de LECTURE : elle montre ce que la plateforme a réellement importé, avec
 * sa provenance. C'est le contrôle qui permet de constater qu'un connecteur
 * « connecté » remonte bien des offres — un connecteur vert sans offre est un
 * mensonge silencieux, et cette table est ce qui le rend visible.
 */
const offers = ref([])
const loading = ref(false)
const error = ref(null)

async function load() {
  loading.value = true
  error.value = null
  try {
    const response = await api.get('/platform/recruitment/offers', { _skipToast: true })
    offers.value = response.data?.data ?? []
  } catch (e) {
    error.value = e?.response?.status === 403 ? 'forbidden' : 'error'
  } finally {
    loading.value = false
  }
}

onMounted(() => load())
</script>
