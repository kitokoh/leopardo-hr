<template>
  <div class="p-6">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 class="text-3xl font-bold text-slate-900 dark:text-white">
          {{ t('searchIntel.overview.title', 'Growth & Search Intelligence') }}
        </h1>
        <p class="mt-1 text-slate-500 dark:text-slate-400">
          {{ t('searchIntel.overview.subtitle', 'Vue consolidee SEO, SEA, AEO, GEO, contenu et leads.') }}
        </p>
      </div>
      <div class="flex items-center gap-3">
        <label class="text-xs font-bold uppercase tracking-wider text-slate-500">
          {{ t('searchIntel.overview.window', 'Fenetre leads') }}
          <select
            v-model.number="leadWindowDays"
            class="ml-2 rounded-lg border border-slate-200 bg-white px-2 py-1 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
            @change="load"
          >
            <option :value="7">7 j</option>
            <option :value="30">30 j</option>
            <option :value="90">90 j</option>
          </select>
        </label>
        <button class="btn-secondary" :disabled="loading" @click="load">
          {{ t('searchIntel.common.refresh', 'Rafraichir') }}
        </button>
      </div>
    </div>

    <div v-if="loading" class="p-12 text-center text-sm text-slate-500">
      {{ t('searchIntel.common.loading', 'Chargement...') }}
    </div>

    <div
      v-else-if="error"
      class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700 dark:border-red-900/30 dark:bg-red-950/20 dark:text-red-400"
    >
      {{ error }}
      <button class="ml-3 underline" @click="load">{{ t('searchIntel.common.retry', 'Reessayer') }}</button>
    </div>

    <template v-else-if="data">
      <p class="mb-4 text-xs text-slate-400">
        {{ t('searchIntel.overview.generatedAt', 'Genere le') }} {{ formatDate(data.generated_at) }}
      </p>

      <section class="mb-8">
        <h2 class="mb-3 text-sm font-black uppercase tracking-wider text-slate-500">
          {{ t('searchIntel.overview.seo', 'SEO') }}
        </h2>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <MetricCard
            :label="t('searchIntel.overview.pagesTracked', 'Pages suivies')"
            :value="kpi(data.seo?.pages_tracked)"
            :reliability="rel(data.seo?.pages_tracked)"
          />
          <MetricCard
            :label="t('searchIntel.overview.pagesHealthy', 'Pages saines')"
            :value="kpi(data.seo?.pages_healthy)"
            :reliability="rel(data.seo?.pages_healthy)"
          />
          <MetricCard
            :label="t('searchIntel.overview.openIssues', 'Problemes ouverts')"
            :value="kpi(data.seo?.open_issues)"
            :reliability="rel(data.seo?.open_issues)"
          />
          <MetricCard
            :label="t('searchIntel.overview.verifiedIssues', 'Problemes verifies')"
            :value="kpi(data.seo?.verified_issues)"
            :reliability="rel(data.seo?.verified_issues)"
            :hint="t('searchIntel.overview.verifiedHint', 'Correction confirmee par un re-scan.')"
          />
        </div>
      </section>

      <section class="mb-8">
        <h2 class="mb-3 text-sm font-black uppercase tracking-wider text-slate-500">
          {{ t('searchIntel.overview.sea', 'SEA') }}
        </h2>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          <MetricCard
            :label="t('searchIntel.overview.campaignsTotal', 'Campagnes')"
            :value="kpi(data.sea?.campaigns_total)"
            :reliability="rel(data.sea?.campaigns_total)"
          />
          <MetricCard
            :label="t('searchIntel.overview.campaignsRunning', 'Campagnes actives')"
            :value="kpi(data.sea?.campaigns_running)"
            :reliability="rel(data.sea?.campaigns_running)"
          />
          <MetricCard
            :label="t('searchIntel.overview.spendMonth', 'Depense du mois')"
            :value="kpi(data.sea?.spend_month_cents)"
            :reliability="rel(data.sea?.spend_month_cents)"
            format="currency"
          />
        </div>
      </section>

      <section class="mb-8">
        <h2 class="mb-3 text-sm font-black uppercase tracking-wider text-slate-500">
          {{ t('searchIntel.overview.aeo', 'AEO') }}
        </h2>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          <MetricCard
            :label="t('searchIntel.overview.questionsTotal', 'Questions')"
            :value="kpi(data.aeo?.questions_total)"
            :reliability="rel(data.aeo?.questions_total)"
          />
          <MetricCard
            :label="t('searchIntel.overview.questionsPublished', 'Publiees')"
            :value="kpi(data.aeo?.questions_published)"
            :reliability="rel(data.aeo?.questions_published)"
          />
          <MetricCard
            :label="t('searchIntel.overview.questionsAwaiting', 'En attente de revue')"
            :value="kpi(data.aeo?.questions_awaiting_review)"
            :reliability="rel(data.aeo?.questions_awaiting_review)"
          />
        </div>
      </section>

      <section class="mb-8">
        <h2 class="mb-3 text-sm font-black uppercase tracking-wider text-slate-500">
          {{ t('searchIntel.overview.geo', 'GEO') }}
        </h2>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          <MetricCard
            :label="t('searchIntel.overview.geoQueriesActive', 'Requetes actives')"
            :value="kpi(data.geo?.queries_active)"
            :reliability="rel(data.geo?.queries_active)"
          />
        </div>
      </section>

      <section class="mb-8">
        <h2 class="mb-3 text-sm font-black uppercase tracking-wider text-slate-500">
          {{ t('searchIntel.overview.integrations', 'Integrations') }}
        </h2>
        <div class="card divide-y divide-slate-200/60 dark:divide-slate-800/60">
          <div
            v-for="(integration, provider) in data.integrations || {}"
            :key="provider"
            class="flex items-center justify-between px-5 py-3"
          >
            <span class="text-sm font-bold text-slate-700 dark:text-slate-200">{{ provider }}</span>
            <span
              :class="integration?.connected
                ? 'text-emerald-600 dark:text-emerald-400'
                : 'text-slate-400'"
              class="text-xs font-black uppercase tracking-tight"
            >
              {{ integration?.connected
                ? t('searchIntel.common.connected', 'Connecte')
                : t('searchIntel.common.notConnected', 'Non connecte') }}
            </span>
          </div>
          <p v-if="!hasIntegrations" class="px-5 py-6 text-center text-sm text-slate-500">
            {{ t('searchIntel.overview.noIntegrations', 'Aucune integration declaree.') }}
          </p>
        </div>
      </section>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '@/services/api'
import MetricCard from '@/components/common/MetricCard.vue'
import { useLocaleStore } from '@/stores/locale'
import { translate } from '@/i18n/index.js'

const localeStore = useLocaleStore()
const t = (key, fallback = '') => translate(localeStore.current, key, fallback)

/**
 * GSI-10 (#8521) — vue d'ensemble.
 *
 * Le backend renvoie chaque KPI sous la forme `{ value, source, period,
 * reliability }`. On lit `value` et `reliability` ; `null` reste `null` et
 * s'affiche « indisponible » (voir MetricCard), jamais `0`.
 */

const data = ref(null)
const loading = ref(false)
const error = ref('')
const leadWindowDays = ref(30)

const kpi = (entry) => (entry && typeof entry === 'object' ? entry.value : entry ?? null)
const rel = (entry) => (entry && typeof entry === 'object' ? entry.reliability ?? null : null)

const hasIntegrations = computed(() => {
  const integrations = data.value?.integrations
  return integrations && Object.keys(integrations).length > 0
})

function formatDate(value) {
  if (!value) return '—'
  const d = new Date(value)
  return Number.isNaN(d.getTime()) ? value : d.toLocaleString()
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const response = await api.get('/platform/search-intel/overview', {
      params: { lead_window_days: leadWindowDays.value },
      _skipToast: true,
    })
    data.value = response.data?.data ?? response.data ?? null
  } catch (e) {
    error.value = e?.response?.data?.message
      || t('searchIntel.common.loadError', 'Impossible de charger la vue d\'ensemble.')
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>
