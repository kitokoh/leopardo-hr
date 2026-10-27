<template>
  <div class="p-6">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 class="text-3xl font-bold text-slate-900 dark:text-white">
          {{ t('searchIntel.integrations.title', 'Integrations') }}
        </h1>
        <p class="mt-1 text-slate-500 dark:text-slate-400">
          {{ t('searchIntel.integrations.subtitle', 'Connecteurs GSC, GA4, Google Ads et Microsoft Ads.') }}
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

    <div v-if="loading" class="p-12 text-center text-sm text-slate-500">
      {{ t('searchIntel.common.loading', 'Chargement...') }}
    </div>

    <div v-else-if="data.length === 0" class="card p-8 text-center text-sm text-slate-500">
      {{ t('searchIntel.integrations.empty', 'Aucun connecteur declare.') }}
    </div>

    <div v-else class="grid gap-4 md:grid-cols-2">
      <div v-for="integration in data" :key="integration.provider" class="card p-5">
        <div class="flex items-start justify-between gap-3">
          <div>
            <h2 class="text-base font-bold text-slate-900 dark:text-white">
              {{ integration.label || integration.provider }}
            </h2>
            <p class="mt-1 text-xs text-slate-500">
              {{ integration.description || integration.provider }}
            </p>
          </div>
          <StatusBadge :status="integration.status" :map="statusMap" />
        </div>

        <dl class="mt-4 space-y-1 text-xs text-slate-500">
          <div class="flex justify-between gap-3">
            <dt>{{ t('searchIntel.integrations.lastSync', 'Derniere synchro') }}</dt>
            <dd class="font-semibold text-slate-700 dark:text-slate-300">
              {{ formatDate(integration.last_synced_at) }}
            </dd>
          </div>
          <div class="flex justify-between gap-3">
            <dt>{{ t('searchIntel.integrations.lastError', 'Derniere erreur') }}</dt>
            <dd class="max-w-[60%] truncate text-right font-semibold text-slate-700 dark:text-slate-300">
              {{ integration.last_error || '—' }}
            </dd>
          </div>
        </dl>

        <div class="mt-4 flex flex-wrap gap-2">
          <RowActionButton
            :label="t('searchIntel.integrations.test', 'Tester')"
            variant="secondary"
            :disabled="testing === integration.provider"
            @click="test(integration)"
          />
          <RowActionButton
            v-if="integration.status !== 'revoked'"
            :label="t('searchIntel.integrations.revoke', 'Revoquer')"
            variant="danger"
            @click="revoke(integration)"
          />
        </div>

        <p v-if="feedback[integration.provider]" class="mt-2 text-xs font-semibold text-slate-600 dark:text-slate-400">
          {{ feedback[integration.provider] }}
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import api from '@/services/api'
import StatusBadge from '@/components/common/StatusBadge.vue'
import RowActionButton from '@/components/common/RowActionButton.vue'
import { useSearchIntelList, formatDate } from '@/composables/useSearchIntelList'
import { useLocaleStore } from '@/stores/locale'
import { translate } from '@/i18n/index.js'

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

/**
 * GSI-10 (#8521) — vue Integrations.
 *
 * Un connecteur « connecte » qui n'a jamais rien remonte est un mensonge
 * silencieux : la carte affiche donc `last_synced_at` ET `last_error`. Le test
 * de connexion est explicite (bouton) plutot que lance au chargement — un test
 * declenche a chaque ouverture d'ecran consommerait le quota de l'API regie.
 */
const { data, loading, error, load } = useSearchIntelList('/search-intel/integrations')

const testing = ref(null)
const feedback = reactive({})

const statusMap = {
  connected: { label: t('searchIntel.integrationStatus.connected', 'Connecte'), color: 'green' },
  pending: { label: t('searchIntel.integrationStatus.pending', 'En attente'), color: 'yellow' },
  error: { label: t('searchIntel.integrationStatus.error', 'En erreur'), color: 'red' },
  revoked: { label: t('searchIntel.integrationStatus.revoked', 'Revoque'), color: 'gray' },
}

async function test(integration) {
  testing.value = integration.provider
  feedback[integration.provider] = ''
  try {
    const response = await api.post(
      `/platform/search-intel/integrations/${integration.provider}/test`,
      {},
      { _skipToast: true },
    )
    const ok = response.data?.data?.ok ?? response.data?.ok
    feedback[integration.provider] = ok
      ? t('searchIntel.integrations.testOk', 'Connexion verifiee.')
      : t('searchIntel.integrations.testKo', 'Connexion refusee par le fournisseur.')
  } catch (e) {
    feedback[integration.provider] = e?.response?.data?.message
      || t('searchIntel.integrations.testFailed', 'Le test a echoue.')
  } finally {
    testing.value = null
    await load()
  }
}

async function revoke(integration) {
  try {
    await api.delete(`/platform/search-intel/integrations/${integration.provider}`, {
      _skipToast: true,
    })
  } catch {
    // ignore : le rechargement reflete l'etat reel
  }
  await load()
}

onMounted(() => load())
</script>
